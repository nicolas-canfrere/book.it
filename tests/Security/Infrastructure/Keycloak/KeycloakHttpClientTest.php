<?php

declare(strict_types=1);

namespace App\Tests\Security\Infrastructure\Keycloak;

use App\Security\Infrastructure\Keycloak\KeycloakHttpClient;
use App\Security\Infrastructure\Keycloak\KeycloakRetryPolicy;
use App\Security\Infrastructure\Keycloak\KeycloakUnavailableException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[Group('unit')]
final class KeycloakHttpClientTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    /** @var list<int> */
    private array $sleepCalls;
    private KeycloakHttpClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->sleepCalls = [];
        $sleeper = function (int $us): void {
            $this->sleepCalls[] = $us;
        };

        $this->client = new KeycloakHttpClient(
            httpClient: $this->httpClient,
            retryPolicy: new KeycloakRetryPolicy(maxAttempts: 3, baseDelayMs: 100),
            keycloakBaseUrl: 'https://keycloak.test',
            keycloakRealm: 'test-realm',
            keycloakClientId: 'client-id',
            keycloakClientSecret: 'client-secret',
            logger: new NullLogger(),
            sleeper: $sleeper,
        );
    }

    #[Test]
    public function itReturnsResponseOnFirstAttempt(): void
    {
        // token fetch (call 1) + user create (call 2)
        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeTokenResponse('token123'),
                $this->makeResponse(201),
            );

        $result = $this->client->createUser('test@example.com', 'password');

        self::assertSame(201, $result->getStatusCode());
        self::assertEmpty($this->sleepCalls);
    }

    #[Test]
    public function itRetriesOnServerErrorAndSucceeds(): void
    {
        // token fetch (call 1), 500 (call 2), 201 without re-fetching token (call 3)
        $this->httpClient->expects($this->exactly(3))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeTokenResponse('token123'),
                $this->makeResponse(500),
                $this->makeResponse(201),
            );

        $result = $this->client->createUser('test@example.com', 'password');

        self::assertSame(201, $result->getStatusCode());
        self::assertCount(1, $this->sleepCalls);
    }

    #[Test]
    public function itThrowsAfterMaxAttemptsOnServerError(): void
    {
        // token fetch (call 1) + 3× 500 (calls 2-4)
        $this->httpClient->expects($this->exactly(4))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeTokenResponse('token123'),
                $this->makeResponse(500),
                $this->makeResponse(500),
                $this->makeResponse(500),
            );

        $this->expectException(KeycloakUnavailableException::class);
        $this->client->createUser('test@example.com', 'password');
    }

    #[Test]
    public function itInvalidatesTokenOn401AndRetries(): void
    {
        // token fetch (call 1), 401 (call 2) → clear token, re-fetch (call 3), 201 (call 4)
        $this->httpClient->expects($this->exactly(4))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeTokenResponse('expired-token'),
                $this->makeResponse(401),
                $this->makeTokenResponse('new-token'),
                $this->makeResponse(201),
            );

        $result = $this->client->createUser('test@example.com', 'password');

        self::assertSame(201, $result->getStatusCode());
        self::assertEmpty($this->sleepCalls); // no sleep on 401
    }

    #[Test]
    public function itRespectsRetryAfterHeaderOn429(): void
    {
        // token fetch (call 1), 429 (call 2), 201 without re-fetching token (call 3)
        $this->httpClient->expects($this->exactly(3))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeTokenResponse('token'),
                $this->makeResponse(429, ['retry-after' => ['2']]),
                $this->makeResponse(201),
            );

        $result = $this->client->createUser('test@example.com', 'password');

        self::assertSame(201, $result->getStatusCode());
        self::assertSame([2_000_000], $this->sleepCalls); // 2 seconds in microseconds
    }

    #[Test]
    public function itThrowsImmediatelyOnNonRetriable4xx(): void
    {
        // token fetch (call 1), 400 (call 2) → throw immediately, no retry
        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeTokenResponse('token'),
                $this->makeResponse(400),
            );

        $this->expectException(KeycloakUnavailableException::class);
        $this->client->createUser('test@example.com', 'password');
    }

    #[Test]
    public function itRetriesOnNetworkError(): void
    {
        // call 1 (token fetch) → network exception; call 2 (token re-fetch) → ok; call 3 (user create) → 201
        $callCount = 0;
        $tokenResponse = $this->makeTokenResponse('token');
        $success = $this->makeResponse(201);

        $this->httpClient->method('request')
            ->willReturnCallback(function () use (&$callCount, $tokenResponse, $success) {
                ++$callCount;
                if (1 === $callCount) {
                    throw new \RuntimeException('Connection refused');
                }

                return 2 === $callCount ? $tokenResponse : $success;
            });

        $result = $this->client->createUser('test@example.com', 'password');

        self::assertSame(201, $result->getStatusCode());
        self::assertCount(1, $this->sleepCalls);
    }

    #[Test]
    public function itDeletesUser(): void
    {
        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makeTokenResponse('token'),
                $this->makeResponse(204),
            );

        $this->client->deleteUser('keycloak-user-id');
        self::assertEmpty($this->sleepCalls);
    }

    private function makeTokenResponse(string $token): ResponseInterface&MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['access_token' => $token]);
        $response->method('getHeaders')->willReturn([]);

        return $response;
    }

    private function makeResponse(int $status, array $headers = []): ResponseInterface&MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('toArray')->willReturn([]);
        $response->method('getHeaders')->willReturn($headers);

        return $response;
    }
}

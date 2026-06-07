<?php

declare(strict_types=1);

namespace App\Tests\Operator\UI\Http\Controller\RegisterOperator;

use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;
use App\Tests\Operator\Infrastructure\ExternalAccount\ThrowingExternalAccountRegistrar;
use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class RegisterOperatorControllerTest extends AuthenticatedWebTestCase
{
    private const array VALID_PAYLOAD = [
        'firstName' => 'Alice',
        'lastName' => 'Martin',
        'email' => 'alice.martin@hotel.com',
        'phone' => '+33612345678',
        'password' => 'SecurePass123!',
    ];

    #[Test]
    public function itRegistersAnOperatorAndReturns201(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/operators',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{id: string, firstName: string, lastName: string, email: string, phone: string, registeredAt: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['id']);
        self::assertSame('Alice', $body['firstName']);
        self::assertSame('Martin', $body['lastName']);
        self::assertSame('alice.martin@hotel.com', $body['email']);
        self::assertSame('+33612345678', $body['phone']);
        self::assertNotEmpty($body['registeredAt']);
    }

    #[Test]
    public function itReturns409WhenEmailAlreadyExists(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/operators',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'POST',
            uri: '/api/v1/operators',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/operator-already-exists', $body['type']);
        self::assertSame('Operator Already Exists', $body['title']);
        self::assertSame(Response::HTTP_CONFLICT, $body['status']);
    }

    #[Test]
    public function itReturns422AsAProblemDetailWithViolationsWhenFieldIsMissing(): void
    {
        $client = static::createAuthenticatedClient();

        $payload = self::VALID_PAYLOAD;
        unset($payload['email']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/operators',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{violations: list<array{field: string, message: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('email', $fields);
    }

    #[Test]
    public function itReturns422WhenEmailIsInvalid(): void
    {
        $client = static::createAuthenticatedClient();

        $payload = array_merge(self::VALID_PAYLOAD, ['email' => 'not-an-email']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/operators',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenPasswordIsTooShort(): void
    {
        $client = static::createAuthenticatedClient();

        $payload = array_merge(self::VALID_PAYLOAD, ['password' => 'short', 'email' => 'short-pw@example.com']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/operators',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        /** @var array{violations: list<array{field: string, message: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('password', $fields);
    }

    #[Test]
    public function itReturns500WhenExternalAccountCreationFails(): void
    {
        $client = static::createAuthenticatedClient();
        static::getContainer()->set(
            ExternalAccountRegistrarInterface::class,
            new ThrowingExternalAccountRegistrar(),
        );

        $payload = array_merge(self::VALID_PAYLOAD, ['email' => 'keycloak-fail@example.com']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/operators',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/external-account-creation-failed', $body['type']);
        self::assertSame('External Account Creation Failed', $body['title']);
        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $body['status']);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Security\Functional;

use App\Security\Infrastructure\Keycloak\KeycloakJwksProviderInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class BearerAuthTest extends WebTestCase
{
    private string $privateKey = '';
    private string $publicKey = '';

    protected function setUp(): void
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $this->privateKey);
        $this->publicKey = openssl_pkey_get_details($resource)['key'];
    }

    #[Test]
    public function itReturns401WhenNoTokenProvided(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/hotels');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itAllowsAccessWithValidToken(): void
    {
        $client = static::createClient();

        $publicKey = $this->publicKey;
        static::getContainer()->set(
            KeycloakJwksProviderInterface::class,
            new class($publicKey) implements KeycloakJwksProviderInterface {
                public function __construct(private string $key)
                {
                }

                public function getPublicKeys(): array
                {
                    return ['default' => new Key($this->key, 'RS256')];
                }
            }
        );

        $client->request('GET', '/api/v1/hotels', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->makeToken(),
        ]);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns401WithExpiredToken(): void
    {
        $client = static::createClient();

        $publicKey = $this->publicKey;
        static::getContainer()->set(
            KeycloakJwksProviderInterface::class,
            new class($publicKey) implements KeycloakJwksProviderInterface {
                public function __construct(private string $key)
                {
                }

                public function getPublicKeys(): array
                {
                    return ['default' => new Key($this->key, 'RS256')];
                }
            }
        );

        $client->request('GET', '/api/v1/hotels', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->makeToken(['exp' => time() - 10]),
        ]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    private function makeToken(array $overrides = []): string
    {
        $payload = array_merge([
            'sub' => 'operator-uuid-test',
            'iss' => 'http://keycloack:8080/realms/bookit',
            'iat' => time(),
            'exp' => time() + 3600,
        ], $overrides);

        return JWT::encode($payload, $this->privateKey, 'RS256');
    }
}

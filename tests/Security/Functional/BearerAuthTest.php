<?php

declare(strict_types=1);

namespace App\Tests\Security\Functional;

use App\Operator\Application\Contract\OperatorFinderInterface;
use App\Operator\Application\Contract\OperatorView;
use App\Security\Infrastructure\Keycloak\KeycloakJwksProviderInterface;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
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
        \assert($resource instanceof \OpenSSLAsymmetricKey);
        $privateKey = '';
        openssl_pkey_export($resource, $privateKey);
        \assert(\is_string($privateKey));
        $this->privateKey = $privateKey;
        $details = openssl_pkey_get_details($resource);
        \assert(\is_array($details) && \is_string($details['key']));
        $this->publicKey = $details['key'];
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

        static::getContainer()->set(
            IdentityMappingRepository::class,
            new class extends IdentityMappingRepository {
                public function __construct()
                {
                }

                public function findInternalId(string $externalId, string $context): string
                {
                    return 'test-internal-operator-id';
                }
            }
        );

        static::getContainer()->set(
            OperatorFinderInterface::class,
            new class implements OperatorFinderInterface {
                public function find(string $operatorId): OperatorView
                {
                    return new OperatorView($operatorId, 'test-operator@example.com');
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

    /** @param array<string, mixed> $overrides */
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

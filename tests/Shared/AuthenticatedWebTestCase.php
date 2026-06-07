<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Security\Infrastructure\Keycloak\KeycloakJwksProviderInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AuthenticatedWebTestCase extends WebTestCase
{
    private static string $privateKey = '';
    private static string $publicKey = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        \assert($resource instanceof \OpenSSLAsymmetricKey);
        $privateKey = '';
        openssl_pkey_export($resource, $privateKey);
        \assert(\is_string($privateKey));
        self::$privateKey = $privateKey;
        $details = openssl_pkey_get_details($resource);
        \assert(\is_array($details));
        \assert(\is_string($details['key']));
        self::$publicKey = $details['key'];
    }

    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = static::createClient();
        $client->disableReboot();

        $publicKey = self::$publicKey;
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

        $token = JWT::encode([
            'sub' => 'test-operator',
            'iss' => 'http://keycloack:8080/realms/bookit',
            'iat' => time(),
            'exp' => time() + 3600,
        ], self::$privateKey, 'RS256');

        $client->setServerParameter('HTTP_AUTHORIZATION', "Bearer {$token}");

        return $client;
    }
}

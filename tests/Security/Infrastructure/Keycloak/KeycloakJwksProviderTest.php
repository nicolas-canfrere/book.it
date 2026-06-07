<?php

declare(strict_types=1);

namespace App\Tests\Security\Infrastructure\Keycloak;

use App\Security\Infrastructure\Keycloak\KeycloakJwksProvider;
use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[Group('integration')]
final class KeycloakJwksProviderTest extends KernelTestCase
{
    private const JWKS_FIXTURE = [
        'keys' => [
            [
                'kid' => 'test-key-id',
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'n' => 'sGPOWRIBn0KkLOIBnxHSmUPIFGTFgFdMM3l5D38TtajRMhsj1jCRb4WL7E4JjJuXi5fJZEZ56-g_wPR89_jD8GXy3f3KMTkH3DqxVyGcQ4yGNJFMidLmvPxNEWkYgcHkz0fQoFu0Vu7KPNfXBsocT50N0HFTgq0jkHhcxk7pMH3oqb4RRMvqVP7MKFJQtTlxRBx5UhVlVHvT1e2p8zV4N1u6L2a8r5-m5Kq4z-wYrX3j1V9zIj7qKFJQjkVwuQ',
                'e' => 'AQAB',
            ],
        ],
    ];

    #[Test]
    public function itParsesPublicKeysFromJwks(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode(self::JWKS_FIXTURE)),
        ]);
        $cache = new ArrayAdapter(storeSerialized: true);

        $provider = new KeycloakJwksProvider($httpClient, $cache, 'http://keycloak:8080', 'bookit');

        $keys = $provider->getPublicKeys();

        self::assertNotEmpty($keys);
        self::assertContainsOnlyInstancesOf(Key::class, $keys);
    }

    #[Test]
    public function itCachesJwksAndDoesNotCallHttpClientTwice(): void
    {
        $callCount = 0;
        $httpClient = new MockHttpClient(function () use (&$callCount): MockResponse {
            ++$callCount;

            return new MockResponse((string) json_encode(self::JWKS_FIXTURE));
        });
        $cache = new ArrayAdapter(storeSerialized: true);

        $provider = new KeycloakJwksProvider($httpClient, $cache, 'http://keycloak:8080', 'bookit');

        $provider->getPublicKeys();
        $provider->getPublicKeys();

        self::assertSame(1, $callCount, 'JWKS endpoint should be called only once due to cache');
    }
}

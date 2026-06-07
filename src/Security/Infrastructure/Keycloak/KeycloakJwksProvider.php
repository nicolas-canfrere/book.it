<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class KeycloakJwksProvider implements KeycloakJwksProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly string $keycloakBaseUrl,
        private readonly string $keycloakRealm,
    ) {
    }

    public function getPublicKeys(): array
    {
        /** @var array<string, Key> */
        return $this->cache->get('keycloak_jwks', function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            $url = "{$this->keycloakBaseUrl}/realms/{$this->keycloakRealm}/protocol/openid-connect/certs";
            $response = $this->httpClient->request('GET', $url);

            /** @var array{keys: list<array<string, string>>} $jwks */
            $jwks = $response->toArray();

            return JWK::parseKeySet($jwks);
        });
    }
}

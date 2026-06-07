<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Symfony\Contracts\Cache\CacheInterface;
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
        throw new \RuntimeException('Not implemented');
    }
}

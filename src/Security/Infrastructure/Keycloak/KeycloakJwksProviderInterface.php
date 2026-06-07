<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Firebase\JWT\Key;

interface KeycloakJwksProviderInterface
{
    /** @return array<string, Key> */
    public function getPublicKeys(): array;
}

<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

final readonly class KeycloakRetryPolicy
{
    public function __construct(
        public int $maxAttempts,
        public int $baseDelayMs,
    ) {}
}

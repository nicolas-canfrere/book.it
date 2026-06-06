<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Symfony\Contracts\HttpClient\ResponseInterface;

interface KeycloakHttpClientInterface
{
    public function createUser(string $email, string $password): ResponseInterface;

    public function deleteUser(string $keycloakId): void;
}

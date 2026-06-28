<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Symfony\Contracts\HttpClient\ResponseInterface;

interface KeycloakHttpClientInterface
{
    public function createUser(string $email, string $password): ResponseInterface;

    public function deleteUser(string $keycloakId): void;

    public function assignRealmRole(string $keycloakId, string $roleName): void;

    public function setUserAttribute(string $keycloakId, string $attribute, string $value): void;

    public function disableUser(string $keycloakId): void;

    public function revokeUserSessions(string $keycloakId): void;
}

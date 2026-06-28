<?php

declare(strict_types=1);

namespace App\Tests\Security\Infrastructure;

use App\Security\Application\Contract\AccountRegistrarInterface;

final class NullAccountRegistrar implements AccountRegistrarInterface
{
    public function register(string $internalId, string $context, string $email, string $password): void
    {
    }

    public function unregister(string $internalId, string $context): void
    {
    }

    public function assignRole(string $internalId, string $context, string $roleName): void
    {
    }

    public function setOrganizationId(string $internalId, string $context, string $organizationId): void
    {
    }
}

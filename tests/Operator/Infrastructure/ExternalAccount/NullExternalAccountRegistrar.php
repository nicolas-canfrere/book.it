<?php

declare(strict_types=1);

namespace App\Tests\Operator\Infrastructure\ExternalAccount;

use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;

final class NullExternalAccountRegistrar implements ExternalAccountRegistrarInterface
{
    public function register(string $operatorId, string $email, string $password): void
    {
    }

    public function unregister(string $operatorId): void
    {
    }

    public function assignAdminRole(string $operatorId): void
    {
    }
}

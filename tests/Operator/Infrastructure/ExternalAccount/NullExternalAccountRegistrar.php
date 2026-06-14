<?php

declare(strict_types=1);

namespace App\Tests\Operator\Infrastructure\ExternalAccount;

use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;
use App\Shared\Domain\ValueObject\OperatorId;

final class NullExternalAccountRegistrar implements ExternalAccountRegistrarInterface
{
    public function register(OperatorId $operatorId, string $email, string $password): void
    {
    }

    public function unregister(OperatorId $operatorId): void
    {
    }

    public function assignAdminRole(OperatorId $operatorId): void
    {
    }
}

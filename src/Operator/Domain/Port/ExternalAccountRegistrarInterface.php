<?php

declare(strict_types=1);

namespace App\Operator\Domain\Port;

use App\Shared\Domain\ValueObject\OperatorId;

interface ExternalAccountRegistrarInterface
{
    public function register(OperatorId $operatorId, string $email, string $password): void;

    public function unregister(OperatorId $operatorId): void;

    public function assignAdminRole(OperatorId $operatorId): void;
}

<?php

declare(strict_types=1);

namespace App\Operator\Domain\Port;

interface ExternalAccountRegistrarInterface
{
    public function register(string $operatorId, string $email, string $password): void;

    public function unregister(string $operatorId): void;

    public function assignAdminRole(string $operatorId): void;
}

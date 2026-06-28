<?php

declare(strict_types=1);

namespace App\Onboarding\Application\Port;

interface OwnerOperatorRegistrarInterface
{
    public function registerOwner(
        string $operatorId,
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $password,
        string $organizationId,
        \DateTimeImmutable $registeredAt,
    ): void;
}

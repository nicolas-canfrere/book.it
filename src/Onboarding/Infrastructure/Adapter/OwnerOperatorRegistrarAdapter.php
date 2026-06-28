<?php

declare(strict_types=1);

namespace App\Onboarding\Infrastructure\Adapter;

use App\Onboarding\Application\Port\OwnerOperatorRegistrarInterface;
use App\Operator\Application\Contract\OwnerOperatorRegistrarInterface as OperatorContract;

final readonly class OwnerOperatorRegistrarAdapter implements OwnerOperatorRegistrarInterface
{
    public function __construct(private OperatorContract $contract)
    {
    }

    public function registerOwner(
        string $operatorId,
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $password,
        string $organizationId,
        \DateTimeImmutable $registeredAt,
    ): void {
        $this->contract->registerOwner(
            $operatorId, $firstName, $lastName, $email, $phone,
            $password, $organizationId, $registeredAt,
        );
    }
}

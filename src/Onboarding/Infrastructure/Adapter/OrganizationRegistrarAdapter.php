<?php

declare(strict_types=1);

namespace App\Onboarding\Infrastructure\Adapter;

use App\Onboarding\Application\Port\OrganizationRegistrarInterface;
use App\Organization\Application\Contract\OrganizationRegistrarInterface as OrganizationContract;

final readonly class OrganizationRegistrarAdapter implements OrganizationRegistrarInterface
{
    public function __construct(private OrganizationContract $contract)
    {
    }

    public function register(
        string $organizationId,
        string $name,
        string $contactEmail,
        \DateTimeImmutable $registeredAt,
    ): void {
        $this->contract->register($organizationId, $name, $contactEmail, $registeredAt);
    }

    public function removeOrganization(string $organizationId): void
    {
        $this->contract->remove($organizationId);
    }
}

<?php

declare(strict_types=1);

namespace App\Organization\Application\Contract;

interface OrganizationRegistrarInterface
{
    public function register(
        string $organizationId,
        string $name,
        string $contactEmail,
        \DateTimeImmutable $registeredAt,
    ): void;
}

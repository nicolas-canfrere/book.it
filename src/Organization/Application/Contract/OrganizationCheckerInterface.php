<?php

declare(strict_types=1);

namespace App\Organization\Application\Contract;

interface OrganizationCheckerInterface
{
    public function exists(string $organizationId): bool;

    public function isActive(string $organizationId): bool;
}

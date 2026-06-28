<?php

declare(strict_types=1);

namespace App\Organization\Domain\Port;

use App\Organization\Domain\Model\Organization;
use App\Shared\Domain\ValueObject\OrganizationId;

interface OrganizationRepositoryInterface
{
    public function add(Organization $organization): void;

    public function save(Organization $organization): void;

    public function get(OrganizationId $id): ?Organization;

    public function existsByContactEmail(string $email): bool;
}

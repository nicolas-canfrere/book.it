<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure\Persistence\InMemory;

use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Port\OrganizationRepositoryInterface;
use App\Shared\Domain\ValueObject\OrganizationId;

final class InMemoryOrganizationRepository implements OrganizationRepositoryInterface
{
    /** @var array<string, Organization> */
    private array $store = [];

    public function add(Organization $organization): void
    {
        $this->store[$organization->id->value] = $organization;
    }

    public function save(Organization $organization): void
    {
        $this->store[$organization->id->value] = $organization;
    }

    public function get(OrganizationId $id): ?Organization
    {
        return $this->store[$id->value] ?? null;
    }

    public function existsByContactEmail(string $email): bool
    {
        foreach ($this->store as $org) {
            if ($org->contactEmail->value === $email) {
                return true;
            }
        }

        return false;
    }

    public function remove(OrganizationId $id): void
    {
        unset($this->store[$id->value]);
    }
}

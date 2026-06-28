<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Domain\Exception\TenantContextNotInitializedException;
use App\Shared\Domain\ValueObject\OrganizationId;

final class TenantContext
{
    private ?OrganizationId $organizationId = null;

    public function set(OrganizationId $id): void
    {
        $this->organizationId = $id;
    }

    public function getOrganizationId(): OrganizationId
    {
        if (null === $this->organizationId) {
            throw new TenantContextNotInitializedException();
        }

        return $this->organizationId;
    }

    public function isInitialized(): bool
    {
        return null !== $this->organizationId;
    }
}

<?php

declare(strict_types=1);

namespace App\Organization\Domain\Port;

use App\Shared\Domain\ValueObject\OrganizationId;

interface OrganizationIdGeneratorInterface
{
    public function generate(): OrganizationId;
}

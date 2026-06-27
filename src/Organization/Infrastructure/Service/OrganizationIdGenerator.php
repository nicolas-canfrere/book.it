<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Service;

use App\Organization\Domain\Port\OrganizationIdGeneratorInterface;
use App\Shared\Domain\ValueObject\OrganizationId;
use Symfony\Component\Uid\Uuid;

final readonly class OrganizationIdGenerator implements OrganizationIdGeneratorInterface
{
    public function generate(): OrganizationId
    {
        return new OrganizationId(Uuid::v4()->toRfc4122());
    }
}

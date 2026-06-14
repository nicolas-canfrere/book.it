<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\Service;

use App\Availability\Domain\Port\BlockedPeriodIdGeneratorInterface;
use App\Shared\Domain\ValueObject\BlockedPeriodId;
use Symfony\Component\Uid\Uuid;

final class BlockedPeriodIdGenerator implements BlockedPeriodIdGeneratorInterface
{
    public function generate(): BlockedPeriodId
    {
        return new BlockedPeriodId(Uuid::v4()->toString());
    }
}

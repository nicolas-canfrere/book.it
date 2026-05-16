<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\Service;

use App\Availability\Application\Service\BlockedPeriodIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class BlockedPeriodIdGenerator implements BlockedPeriodIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}

<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Service;

use App\Pricing\Domain\Port\RatePeriodIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class UuidRatePeriodIdGenerator implements RatePeriodIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}

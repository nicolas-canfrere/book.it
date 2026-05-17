<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetRatePeriods;

use App\Shared\Application\Bus\SyncQueryInterface;

final readonly class GetRatePeriodsQuery implements SyncQueryInterface
{
    public function __construct(
        public string $roomId,
    ) {
    }
}

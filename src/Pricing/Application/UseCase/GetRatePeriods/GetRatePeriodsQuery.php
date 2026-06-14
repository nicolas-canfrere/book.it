<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetRatePeriods;

use App\Pricing\Domain\Model\RatePeriod;
use App\Shared\Application\Bus\SyncQueryInterface;
use App\Shared\Domain\ValueObject\RoomId;

/** @implements SyncQueryInterface<list<RatePeriod>> */
final readonly class GetRatePeriodsQuery implements SyncQueryInterface
{
    public function __construct(
        public RoomId $roomId,
    ) {
    }
}

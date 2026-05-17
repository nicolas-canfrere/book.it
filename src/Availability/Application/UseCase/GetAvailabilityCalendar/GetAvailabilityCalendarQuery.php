<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\GetAvailabilityCalendar;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Shared\Application\Bus\SyncQueryInterface;

/** @implements SyncQueryInterface<list<BlockedPeriod>> */
final readonly class GetAvailabilityCalendarQuery implements SyncQueryInterface
{
    public function __construct(public string $roomId)
    {
    }
}

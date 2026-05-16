<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\GetAvailabilityCalendar;

use App\Shared\Application\Bus\SyncQueryInterface;

final readonly class GetAvailabilityCalendarQuery implements SyncQueryInterface
{
    public function __construct(public string $roomId)
    {
    }
}

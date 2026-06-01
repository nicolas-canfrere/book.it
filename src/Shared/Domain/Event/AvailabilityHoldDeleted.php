<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class AvailabilityHoldDeleted
{
    public function __construct(
        public string $reservationId,
    ) {
    }
}

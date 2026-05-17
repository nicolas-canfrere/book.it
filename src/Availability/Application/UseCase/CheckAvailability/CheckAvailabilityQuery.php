<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\CheckAvailability;

use App\Shared\Application\Bus\SyncQueryInterface;

/** @implements SyncQueryInterface<bool> */
final readonly class CheckAvailabilityQuery implements SyncQueryInterface
{
    public function __construct(
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}

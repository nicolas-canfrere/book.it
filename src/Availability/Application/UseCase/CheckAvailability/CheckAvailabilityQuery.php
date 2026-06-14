<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\CheckAvailability;

use App\Shared\Application\Bus\SyncQueryInterface;
use App\Shared\Domain\ValueObject\RoomId;

/** @implements SyncQueryInterface<bool> */
final readonly class CheckAvailabilityQuery implements SyncQueryInterface
{
    public function __construct(
        public RoomId $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}

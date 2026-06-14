<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Shared\Domain\ValueObject\RoomId;

interface RoomAvailabilityCheckerInterface
{
    public function isAvailable(RoomId $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool;
}

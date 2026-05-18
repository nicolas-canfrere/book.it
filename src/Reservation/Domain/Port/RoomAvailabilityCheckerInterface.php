<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

interface RoomAvailabilityCheckerInterface
{
    public function isAvailable(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool;
}

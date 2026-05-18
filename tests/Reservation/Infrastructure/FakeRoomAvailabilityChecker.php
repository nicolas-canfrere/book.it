<?php
declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;

final class FakeRoomAvailabilityChecker implements RoomAvailabilityCheckerInterface
{
    private bool $available = true;

    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    public function isAvailable(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        return $this->available;
    }
}

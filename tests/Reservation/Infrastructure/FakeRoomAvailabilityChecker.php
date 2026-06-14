<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;
use App\Shared\Domain\ValueObject\RoomId;

final class FakeRoomAvailabilityChecker implements RoomAvailabilityCheckerInterface
{
    private bool $available = true;

    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    public function isAvailable(RoomId $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        return $this->available;
    }
}

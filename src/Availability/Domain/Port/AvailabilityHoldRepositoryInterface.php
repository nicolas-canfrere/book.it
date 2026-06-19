<?php

declare(strict_types=1);

namespace App\Availability\Domain\Port;

use App\Availability\Domain\Model\AvailabilityHold;
use App\Shared\Domain\ValueObject\ReservationId;
use App\Shared\Domain\ValueObject\RoomId;

interface AvailabilityHoldRepositoryInterface
{
    public function add(AvailabilityHold $hold): void;

    public function deleteByReservationId(ReservationId $reservationId): void;

    public function hasActiveOverlap(RoomId $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool;
}

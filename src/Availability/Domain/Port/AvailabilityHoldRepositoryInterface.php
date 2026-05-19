<?php

declare(strict_types=1);

namespace App\Availability\Domain\Port;

use App\Availability\Domain\Model\AvailabilityHold;

interface AvailabilityHoldRepositoryInterface
{
    public function add(AvailabilityHold $hold): void;

    public function deleteByReservationId(string $reservationId): void;

    public function hasActiveOverlap(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool;
}

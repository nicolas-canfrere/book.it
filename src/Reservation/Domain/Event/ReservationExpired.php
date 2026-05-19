<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Event;

final readonly class ReservationExpired
{
    public function __construct(
        public string $reservationId,
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}

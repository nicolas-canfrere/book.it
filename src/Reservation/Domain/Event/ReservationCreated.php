<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Event;

final readonly class ReservationCreated
{
    public function __construct(
        public string $reservationId,
        public string $roomId,
        public string $bookerId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $totalPrice,
    ) {
    }
}

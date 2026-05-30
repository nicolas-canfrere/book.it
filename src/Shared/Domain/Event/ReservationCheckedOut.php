<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class ReservationCheckedOut
{
    public function __construct(
        public string $reservationId,
        public string $roomId,
        public string $bookerId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public \DateTimeImmutable $actualDepartureDate,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Reservation\Application\Contract;

final readonly class ReservationView
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $totalPriceCents,
    ) {
    }
}

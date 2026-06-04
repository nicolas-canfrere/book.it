<?php

declare(strict_types=1);

namespace App\Reservation\Application\Contract;

// Intentionally minimal: current consumer (ReservationDetailsFetcher) needs checkIn, checkOut, totalPriceCents. Extend when a consumer requires more fields.
final readonly class ReservationView
{
    public function __construct(
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $totalPriceCents,
    ) {
    }
}

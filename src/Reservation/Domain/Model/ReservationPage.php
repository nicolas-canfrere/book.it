<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

final readonly class ReservationPage
{
    /** @param list<Reservation> $reservations */
    public function __construct(
        public array $reservations,
        public int $total,
    ) {
    }
}

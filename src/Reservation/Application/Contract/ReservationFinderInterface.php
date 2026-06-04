<?php

declare(strict_types=1);

namespace App\Reservation\Application\Contract;

interface ReservationFinderInterface
{
    public function find(string $reservationId): ?ReservationView;
}

<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Reservation\Domain\Model\Reservation;

interface ReservationRepositoryInterface
{
    public function add(Reservation $reservation): void;

    public function save(Reservation $reservation): void;

    public function get(string $id): ?Reservation;
}

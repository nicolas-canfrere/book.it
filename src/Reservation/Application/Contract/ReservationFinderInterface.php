<?php

declare(strict_types=1);

namespace App\Reservation\Application\Contract;

use App\Shared\Domain\ValueObject\ReservationId;

interface ReservationFinderInterface
{
    public function find(ReservationId $reservationId): ?ReservationView;
}

<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Shared\Domain\ValueObject\ReservationId;

interface ReservationIdGeneratorInterface
{
    public function generate(): ReservationId;
}

<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

interface ReservationIdGeneratorInterface
{
    public function generate(): string;
}

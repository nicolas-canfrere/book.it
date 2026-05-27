<?php

declare(strict_types=1);

namespace App\Notification\Domain\Port;

use App\Notification\Domain\ReadModel\ReservationDetails;

interface ReservationDetailsFetcherInterface
{
    public function fetch(string $reservationId): ?ReservationDetails;
}

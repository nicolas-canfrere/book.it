<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\ExpireReservation;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class ExpireReservationCommand implements AsyncCommandInterface
{
    public function __construct(public string $reservationId)
    {
    }
}

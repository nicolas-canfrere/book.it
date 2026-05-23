<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\ConfirmReservation;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class ConfirmReservationCommand implements SyncCommandInterface
{
    public function __construct(public string $reservationId)
    {
    }
}

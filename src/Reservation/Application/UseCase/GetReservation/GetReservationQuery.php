<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\GetReservation;

use App\Reservation\Domain\Model\Reservation;
use App\Shared\Application\Bus\SyncQueryInterface;

/** @implements SyncQueryInterface<?Reservation> */
final readonly class GetReservationQuery implements SyncQueryInterface
{
    public function __construct(public string $id)
    {
    }
}

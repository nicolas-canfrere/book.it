<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\ListBookerReservations;

use App\Reservation\Domain\Model\ReservationPage;
use App\Shared\Application\Bus\SyncQueryInterface;
use App\Shared\Domain\ValueObject\BookerId;

/** @implements SyncQueryInterface<ReservationPage> */
final readonly class ListBookerReservationsQuery implements SyncQueryInterface
{
    public function __construct(
        public BookerId $bookerId,
        public int $page = 1,
        public int $limit = 20,
    ) {
    }
}

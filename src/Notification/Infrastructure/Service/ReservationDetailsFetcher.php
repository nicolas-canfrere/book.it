<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Service;

use App\Notification\Domain\Port\ReservationDetailsFetcherInterface;
use App\Notification\Domain\ReadModel\ReservationDetails;
use App\Reservation\Application\UseCase\GetReservation\GetReservationQuery;
use App\Reservation\Domain\Model\Reservation;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class ReservationDetailsFetcher implements ReservationDetailsFetcherInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function fetch(string $reservationId): ?ReservationDetails
    {
        /** @var Reservation|null $reservation */
        $reservation = $this->queryBus->ask(new GetReservationQuery($reservationId));

        if (null === $reservation) {
            return null;
        }

        return new ReservationDetails(
            checkIn: $reservation->period->checkIn,
            checkOut: $reservation->period->checkOut,
            totalPriceCents: $reservation->totalPrice,
        );
    }
}

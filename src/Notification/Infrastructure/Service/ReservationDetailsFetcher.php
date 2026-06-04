<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Service;

use App\Notification\Domain\Port\ReservationDetailsFetcherInterface;
use App\Notification\Domain\ReadModel\ReservationDetails;
use App\Reservation\Application\Contract\ReservationFinderInterface;

final readonly class ReservationDetailsFetcher implements ReservationDetailsFetcherInterface
{
    public function __construct(private ReservationFinderInterface $reservations)
    {
    }

    public function fetch(string $reservationId): ?ReservationDetails
    {
        $view = $this->reservations->find($reservationId);

        if (null === $view) {
            return null;
        }

        return new ReservationDetails(
            checkIn: $view->checkIn,
            checkOut: $view->checkOut,
            totalPriceCents: $view->totalPriceCents,
        );
    }
}

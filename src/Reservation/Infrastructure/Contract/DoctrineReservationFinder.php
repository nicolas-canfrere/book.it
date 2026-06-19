<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Contract;

use App\Reservation\Application\Contract\ReservationFinderInterface;
use App\Reservation\Application\Contract\ReservationView;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Domain\ValueObject\ReservationId;

final readonly class DoctrineReservationFinder implements ReservationFinderInterface
{
    public function __construct(private ReservationRepositoryInterface $reservationRepository)
    {
    }

    public function find(ReservationId $reservationId): ?ReservationView
    {
        $reservation = $this->reservationRepository->get($reservationId);

        if (null === $reservation) {
            return null;
        }

        return new ReservationView(
            id: $reservation->id->value,
            checkIn: $reservation->period->checkIn,
            checkOut: $reservation->period->checkOut,
            totalPriceCents: $reservation->totalPrice,
        );
    }
}

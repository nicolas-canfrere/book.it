<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\ExpireReservation;

use App\Reservation\Domain\Event\ReservationExpired;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class ExpireReservationCommandHandler
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ExpireReservationCommand $command): void
    {
        $reservation = $this->repository->get($command->reservationId);

        if (null === $reservation || ReservationStatus::Pending !== $reservation->status) {
            return;
        }

        $reservation->expire();
        $this->repository->add($reservation);

        $this->eventDispatcher->dispatch(new ReservationExpired(
            reservationId: $reservation->id,
            roomId: $reservation->roomId,
            checkIn: $reservation->period->checkIn,
            checkOut: $reservation->period->checkOut,
        ));
    }
}

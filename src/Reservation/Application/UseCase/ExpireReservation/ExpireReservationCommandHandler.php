<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\ExpireReservation;

use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;
use App\Shared\Domain\Event\ReservationExpired;
use App\Shared\Domain\ValueObject\ReservationId;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class ExpireReservationCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ExpireReservationCommand $command): void
    {
        $reservation = $this->repository->get(new ReservationId($command->reservationId));

        if (null === $reservation || ReservationStatus::Pending !== $reservation->status) {
            return;
        }

        $reservation->expire();
        $this->repository->save($reservation);

        $this->eventDispatcher->dispatch(new ReservationExpired(
            reservationId: $reservation->id->value,
            roomId: $reservation->roomId->value,
            checkIn: $reservation->period->checkIn,
            checkOut: $reservation->period->checkOut,
        ));
    }
}

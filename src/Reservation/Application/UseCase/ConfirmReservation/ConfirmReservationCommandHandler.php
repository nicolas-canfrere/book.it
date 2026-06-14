<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\ConfirmReservation;

use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\ReservationConfirmed;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class ConfirmReservationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ConfirmReservationCommand $command): void
    {
        $reservation = $this->repository->get($command->reservationId);

        if (null === $reservation || ReservationStatus::Pending !== $reservation->status) {
            return;
        }

        $reservation->confirm();
        $this->repository->save($reservation);

        $this->eventDispatcher->dispatch(new ReservationConfirmed(
            reservationId: $reservation->id,
            roomId: $reservation->roomId->value,
            bookerId: $reservation->bookerId,
            checkIn: $reservation->period->checkIn,
            checkOut: $reservation->period->checkOut,
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CheckOut;

use App\Reservation\Domain\Exception\ReservationNotFoundException;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\ReservationCheckedOut;
use App\Shared\Domain\ValueObject\ReservationId;
use Psr\EventDispatcher\EventDispatcherInterface;

final class CheckOutCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservations,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(CheckOutCommand $command): void
    {
        $reservation = $this->reservations->get(new ReservationId($command->reservationId));

        if (null === $reservation) {
            throw new ReservationNotFoundException($command->reservationId);
        }

        $reservation->checkOut($command->actualDepartureDate);

        $this->reservations->save($reservation);

        $this->eventDispatcher->dispatch(new ReservationCheckedOut(
            reservationId: $reservation->id->value,
            roomId: $reservation->roomId->value,
            bookerId: $reservation->bookerId,
            checkIn: $reservation->period->checkIn,
            checkOut: $reservation->period->checkOut,
            actualDepartureDate: $command->actualDepartureDate,
        ));
    }
}

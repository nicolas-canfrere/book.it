<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CreateReservation;

use App\Reservation\Domain\Event\ReservationCreated;
use App\Reservation\Domain\Exception\BookerNotFoundException;
use App\Reservation\Domain\Exception\RoomNotAvailableException;
use App\Reservation\Domain\Exception\RoomNotFoundException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Port\BookerExistsInterface;
use App\Reservation\Domain\Port\PriceCalculatorInterface;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;
use App\Reservation\Domain\Port\RoomExistsInterface;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Shared\Application\Bus\DomainEventBusInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class CreateReservationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private RoomExistsInterface $roomExists,
        private BookerExistsInterface $bookerExists,
        private RoomAvailabilityCheckerInterface $availabilityChecker,
        private PriceCalculatorInterface $priceCalculator,
        private DomainEventBusInterface $eventBus,
    ) {
    }

    public function __invoke(CreateReservationCommand $command): void
    {
        if (!$this->roomExists->exists($command->roomId)) {
            throw new RoomNotFoundException($command->roomId);
        }

        if (!$this->bookerExists->exists($command->bookerId)) {
            throw new BookerNotFoundException($command->bookerId);
        }

        if (!$this->availabilityChecker->isAvailable($command->roomId, $command->checkIn, $command->checkOut)) {
            throw new RoomNotAvailableException($command->roomId);
        }

        $totalPrice = $this->priceCalculator->calculate($command->roomId, $command->checkIn, $command->checkOut);

        $reservation = new Reservation(
            id: $command->id,
            roomId: $command->roomId,
            bookerId: $command->bookerId,
            period: new DatePeriod($command->checkIn, $command->checkOut),
            totalPrice: $totalPrice,
            createdAt: $command->createdAt,
        );

        $this->repository->add($reservation);

        $this->eventBus->dispatch(new ReservationCreated(
            reservationId: $reservation->id,
            roomId: $reservation->roomId,
            bookerId: $reservation->bookerId,
            checkIn: $reservation->period->checkIn,
            checkOut: $reservation->period->checkOut,
            totalPrice: $reservation->totalPrice,
        ));
    }
}

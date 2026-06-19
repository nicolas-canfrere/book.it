<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CancelReservation;

use App\Reservation\Domain\Exception\ReservationNotFoundException;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\ReservationCancelled;
use App\Shared\Domain\ValueObject\ReservationId;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class CancelReservationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(CancelReservationCommand $command): void
    {
        $reservation = $this->repository->get(new ReservationId($command->reservationId));

        if (null === $reservation) {
            throw new ReservationNotFoundException($command->reservationId);
        }

        $reservation->cancelByBooker($command->today);
        $this->repository->save($reservation);

        $refundAmountCents = $reservation->cancellationTerms->isRefundable(
            $command->today,
            $reservation->period->checkIn,
        ) ? $reservation->totalPrice : 0;

        $this->eventDispatcher->dispatch(new ReservationCancelled(
            reservationId: $reservation->id->value,
            roomId: $reservation->roomId->value,
            bookerId: $reservation->bookerId->value,
            refundAmountCents: $refundAmountCents,
            checkIn: $reservation->period->checkIn,
            checkOut: $reservation->period->checkOut,
        ));
    }
}

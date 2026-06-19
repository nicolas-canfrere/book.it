<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CancelPendingReservation;

use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\ReservationPaymentCancelled;
use App\Shared\Domain\ValueObject\ReservationId;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class CancelPendingReservationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(CancelPendingReservationCommand $command): void
    {
        $reservation = $this->repository->get(new ReservationId($command->reservationId));

        if (null === $reservation || ReservationStatus::Pending !== $reservation->status) {
            return;
        }

        $reservation->cancelPending();
        $this->repository->save($reservation);

        $this->eventDispatcher->dispatch(new ReservationPaymentCancelled(
            reservationId: $reservation->id->value,
        ));
    }
}

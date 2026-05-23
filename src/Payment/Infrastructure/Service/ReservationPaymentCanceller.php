<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Service;

use App\Payment\Domain\Port\ReservationPaymentCancellerInterface;
use App\Reservation\Application\UseCase\CancelPendingReservation\CancelPendingReservationCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;

final readonly class ReservationPaymentCanceller implements ReservationPaymentCancellerInterface
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    public function cancel(string $reservationId): void
    {
        $this->commandBus->execute(new CancelPendingReservationCommand($reservationId));
    }
}

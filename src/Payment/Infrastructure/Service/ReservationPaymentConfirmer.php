<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Service;

use App\Payment\Domain\Port\ReservationPaymentConfirmerInterface;
use App\Reservation\Application\UseCase\ConfirmReservation\ConfirmReservationCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;

final readonly class ReservationPaymentConfirmer implements ReservationPaymentConfirmerInterface
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    public function confirm(string $reservationId): void
    {
        $this->commandBus->execute(new ConfirmReservationCommand($reservationId));
    }
}

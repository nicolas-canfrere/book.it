<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\EventListener;

use App\Reservation\Application\UseCase\ConfirmReservation\ConfirmReservationCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Domain\Event\PaymentConfirmed;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PaymentConfirmed::class)]
final readonly class PaymentConfirmedListener
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    public function __invoke(PaymentConfirmed $event): void
    {
        $this->commandBus->execute(new ConfirmReservationCommand($event->reservationId));
    }
}

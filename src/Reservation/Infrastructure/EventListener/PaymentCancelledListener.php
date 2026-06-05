<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\EventListener;

use App\Reservation\Application\UseCase\CancelPendingReservation\CancelPendingReservationCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Domain\Event\PaymentCancelled;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PaymentCancelled::class)]
final readonly class PaymentCancelledListener
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    public function __invoke(PaymentCancelled $event): void
    {
        $this->commandBus->execute(new CancelPendingReservationCommand($event->reservationId));
    }
}

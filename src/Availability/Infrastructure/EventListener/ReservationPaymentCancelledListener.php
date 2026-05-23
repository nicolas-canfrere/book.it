<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\EventListener;

use App\Availability\Application\UseCase\DeleteAvailabilityHold\DeleteAvailabilityHoldCommand;
use App\Reservation\Domain\Event\ReservationPaymentCancelled;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationPaymentCancelled::class)]
final readonly class ReservationPaymentCancelledListener
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    public function __invoke(ReservationPaymentCancelled $event): void
    {
        $this->commandBus->execute(new DeleteAvailabilityHoldCommand(
            reservationId: $event->reservationId,
        ));
    }
}

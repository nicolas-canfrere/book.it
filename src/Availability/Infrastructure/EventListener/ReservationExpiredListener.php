<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\EventListener;

use App\Availability\Application\UseCase\DeleteAvailabilityHold\DeleteAvailabilityHoldCommand;
use App\Shared\Domain\Event\ReservationExpired;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationExpired::class)]
final readonly class ReservationExpiredListener
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    public function __invoke(ReservationExpired $event): void
    {
        $this->commandBus->execute(new DeleteAvailabilityHoldCommand(
            reservationId: $event->reservationId,
        ));
    }
}

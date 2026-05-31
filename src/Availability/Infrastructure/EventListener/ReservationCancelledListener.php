<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\EventListener;

use App\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod\DeleteBlockedPeriodByRoomAndPeriodCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Domain\Event\ReservationCancelled;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationCancelled::class)]
final readonly class ReservationCancelledListener
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(ReservationCancelled $event): void
    {
        $this->commandBus->execute(new DeleteBlockedPeriodByRoomAndPeriodCommand(
            roomId: $event->roomId,
            checkIn: $event->checkIn,
            checkOut: $event->checkOut,
        ));
    }
}

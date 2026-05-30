<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\EventListener;

use App\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod\DeleteBlockedPeriodByRoomAndPeriodCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Domain\Event\ReservationCheckedOut;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationCheckedOut::class)]
final readonly class ReservationCheckedOutListener
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(ReservationCheckedOut $event): void
    {
        $this->commandBus->execute(new DeleteBlockedPeriodByRoomAndPeriodCommand(
            roomId: $event->roomId,
            checkIn: $event->checkIn,
            checkOut: $event->checkOut,
        ));
    }
}

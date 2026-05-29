<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\EventListener;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Application\UseCase\DeleteAvailabilityHold\DeleteAvailabilityHoldCommand;
use App\Availability\Domain\Port\BlockedPeriodIdGeneratorInterface;
use App\Shared\Domain\Event\ReservationConfirmed;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationConfirmed::class)]
final readonly class ReservationConfirmedListener
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
        private BlockedPeriodIdGeneratorInterface $idGenerator,
    ) {
    }

    public function __invoke(ReservationConfirmed $event): void
    {
        $this->commandBus->execute(new DeleteAvailabilityHoldCommand(
            reservationId: $event->reservationId,
        ));

        $this->commandBus->execute(new BlockPeriodCommand(
            id: $this->idGenerator->generate(),
            roomId: $event->roomId,
            checkIn: $event->checkIn,
            checkOut: $event->checkOut,
            createdAt: new \DateTimeImmutable(),
        ));
    }
}

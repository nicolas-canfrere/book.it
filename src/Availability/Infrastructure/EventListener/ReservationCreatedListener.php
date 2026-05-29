<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\EventListener;

use App\Availability\Application\UseCase\CreateAvailabilityHold\CreateAvailabilityHoldCommand;
use App\Availability\Domain\Port\AvailabilityHoldIdGeneratorInterface;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Domain\Event\ReservationCreated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationCreated::class)]
final readonly class ReservationCreatedListener
{
    private const int HOLD_TTL_SECONDS = 900;

    public function __construct(
        private SyncCommandBusInterface $commandBus,
        private AvailabilityHoldIdGeneratorInterface $idGenerator,
    ) {
    }

    public function __invoke(ReservationCreated $event): void
    {
        $this->commandBus->execute(new CreateAvailabilityHoldCommand(
            id: $this->idGenerator->generate(),
            roomId: $event->roomId,
            reservationId: $event->reservationId,
            checkIn: $event->checkIn,
            checkOut: $event->checkOut,
            expiresAt: new \DateTimeImmutable(sprintf('+%d seconds', self::HOLD_TTL_SECONDS)),
            createdAt: new \DateTimeImmutable(),
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\AddSearchUnavailablePeriod\AddSearchUnavailablePeriodCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\AvailabilityHoldCreated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: AvailabilityHoldCreated::class)]
final readonly class AvailabilityHoldCreatedListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(AvailabilityHoldCreated $event): void
    {
        $this->commandDispatcher->dispatch(new AddSearchUnavailablePeriodCommand(
            sourceId: $event->reservationId,
            roomId: $event->roomId,
            checkIn: $event->checkIn,
            checkOut: $event->checkOut,
        ));
    }
}

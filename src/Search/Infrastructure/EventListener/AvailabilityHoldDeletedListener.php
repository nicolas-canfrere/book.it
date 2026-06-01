<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\RemoveSearchUnavailablePeriodBySource\RemoveSearchUnavailablePeriodBySourceCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\AvailabilityHoldDeleted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: AvailabilityHoldDeleted::class)]
final readonly class AvailabilityHoldDeletedListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(AvailabilityHoldDeleted $event): void
    {
        $this->commandDispatcher->dispatch(new RemoveSearchUnavailablePeriodBySourceCommand(
            sourceId: $event->reservationId,
        ));
    }
}

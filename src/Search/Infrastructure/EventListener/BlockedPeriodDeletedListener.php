<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\RemoveSearchUnavailablePeriodByPeriod\RemoveSearchUnavailablePeriodByPeriodCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\BlockedPeriodDeleted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BlockedPeriodDeleted::class)]
final readonly class BlockedPeriodDeletedListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(BlockedPeriodDeleted $event): void
    {
        $this->commandDispatcher->dispatch(new RemoveSearchUnavailablePeriodByPeriodCommand(
            roomId: $event->roomId,
            checkIn: $event->checkIn,
            checkOut: $event->checkOut,
        ));
    }
}

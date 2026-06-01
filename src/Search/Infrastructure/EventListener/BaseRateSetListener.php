<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\UpdateSearchBaseRate\UpdateSearchBaseRateCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\BaseRateSet;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BaseRateSet::class)]
final readonly class BaseRateSetListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(BaseRateSet $event): void
    {
        $this->commandDispatcher->dispatch(new UpdateSearchBaseRateCommand(
            roomId: $event->roomId,
            amountCents: $event->amountCents,
        ));
    }
}

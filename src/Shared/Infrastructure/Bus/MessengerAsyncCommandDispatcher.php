<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Application\Bus\AsyncCommandInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final readonly class MessengerAsyncCommandDispatcher implements AsyncCommandDispatcherInterface
{
    public function __construct(private MessageBusInterface $defaultBus)
    {
    }

    public function dispatch(AsyncCommandInterface $command, int $delayMs = 0): void
    {
        $stamps = $delayMs > 0 ? [new DelayStamp($delayMs)] : [];
        $this->defaultBus->dispatch($command, $stamps);
    }
}

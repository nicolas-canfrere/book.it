<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\DomainEventBusInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerDomainEventBus implements DomainEventBusInterface
{
    public function __construct(private MessageBusInterface $domainEventBus)
    {
    }

    public function dispatch(object $event): void
    {
        $this->domainEventBus->dispatch($event);
    }
}

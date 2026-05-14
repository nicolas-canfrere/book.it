<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Application\Bus\SyncCommandInterface;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class SyncMessengerCommandBus implements SyncCommandBusInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $syncCommandBus,
    ) {
        $this->messageBus = $syncCommandBus;
    }

    public function execute(SyncCommandInterface $command): void
    {
        $this->handle($command);
    }
}

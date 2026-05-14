<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Shared\Application\Bus\SyncQueryInterface;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class SyncMessengerQueryBus implements SyncQueryBusInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $syncQueryBus,
    ) {
        $this->messageBus = $syncQueryBus;
    }

    public function ask(SyncQueryInterface $query): mixed
    {
        return $this->handle($query);
    }
}

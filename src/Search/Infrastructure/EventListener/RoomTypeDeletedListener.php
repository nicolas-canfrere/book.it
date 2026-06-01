<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\DeleteSearchRoomType\DeleteSearchRoomTypeCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\RoomTypeDeleted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeDeleted::class)]
final readonly class RoomTypeDeletedListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(RoomTypeDeleted $event): void
    {
        $this->commandDispatcher->dispatch(new DeleteSearchRoomTypeCommand(
            roomTypeId: $event->roomTypeId,
        ));
    }
}

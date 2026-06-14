<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\UpdateSearchRoomType\UpdateSearchRoomTypeCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\RoomTypeUpdated;
use App\Shared\Domain\ValueObject\RoomTypeId;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeUpdated::class)]
final readonly class RoomTypeUpdatedListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(RoomTypeUpdated $event): void
    {
        $this->commandDispatcher->dispatch(new UpdateSearchRoomTypeCommand(
            roomTypeId: new RoomTypeId($event->roomTypeId),
            name: $event->name,
            guestCapacity: $event->guestCapacity,
            bedComposition: $event->bedComposition,
        ));
    }
}

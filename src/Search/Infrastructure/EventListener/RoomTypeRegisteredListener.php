<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\RegisterSearchRoomType\RegisterSearchRoomTypeCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\RoomTypeRegistered;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeRegistered::class)]
final readonly class RoomTypeRegisteredListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(RoomTypeRegistered $event): void
    {
        $this->commandDispatcher->dispatch(new RegisterSearchRoomTypeCommand(
            roomTypeId: $event->roomTypeId,
            hotelId: $event->hotelId,
            name: $event->name,
            guestCapacity: $event->guestCapacity,
            bedComposition: $event->bedComposition,
        ));
    }
}

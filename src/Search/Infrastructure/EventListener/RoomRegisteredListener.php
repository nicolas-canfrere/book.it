<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\RegisterSearchRoom\RegisterSearchRoomCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\RoomRegistered;
use App\Shared\Domain\ValueObject\HotelId;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomRegistered::class)]
final readonly class RoomRegisteredListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(RoomRegistered $event): void
    {
        $this->commandDispatcher->dispatch(new RegisterSearchRoomCommand(
            roomId: $event->roomId,
            hotelId: new HotelId($event->hotelId),
            roomTypeId: $event->roomTypeId,
        ));
    }
}

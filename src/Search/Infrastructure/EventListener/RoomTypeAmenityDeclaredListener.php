<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\UpdateSearchRoomTypeAmenities\UpdateSearchRoomTypeAmenitiesCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\RoomTypeAmenityDeclared;
use App\Shared\Domain\ValueObject\RoomTypeId;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeAmenityDeclared::class)]
final readonly class RoomTypeAmenityDeclaredListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(RoomTypeAmenityDeclared $event): void
    {
        $this->commandDispatcher->dispatch(new UpdateSearchRoomTypeAmenitiesCommand(
            roomTypeId: new RoomTypeId($event->roomTypeId),
            amenities: $event->amenities,
        ));
    }
}

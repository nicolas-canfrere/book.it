<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\DeleteRoomType;

use App\Room\Domain\Exception\RoomTypeHasRoomsException;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Room\Domain\Port\RoomTypeHasRoomsInterface;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class DeleteRoomTypeCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomTypeRepositoryInterface $roomTypeRepository,
        private RoomTypeHasRoomsInterface $roomTypeHasRooms,
    ) {
    }

    public function __invoke(DeleteRoomTypeCommand $command): void
    {
        $roomType = $this->roomTypeRepository->get($command->id);
        if (null === $roomType) {
            throw new RoomTypeNotFoundException($command->id);
        }

        if ($this->roomTypeHasRooms->hasRooms($command->id)) {
            throw new RoomTypeHasRoomsException($command->id);
        }

        $this->roomTypeRepository->delete($command->id);
    }
}

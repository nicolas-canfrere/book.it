<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\RegisterRoom;

use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomAlreadyExistsException;
use App\Room\Domain\Model\Room;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class RegisterRoomCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
        private HotelExistsInterface $hotelExists,
    ) {
    }

    public function __invoke(RegisterRoomCommand $command): void
    {
        if (!$this->hotelExists->exists($command->hotelId)) {
            throw new HotelNotFoundException($command->hotelId);
        }

        if ($this->roomRepository->existsByHotelIdAndNumber($command->hotelId, $command->number)) {
            throw new RoomAlreadyExistsException($command->number, $command->hotelId);
        }

        $this->roomRepository->add(new Room(
            $command->id,
            $command->hotelId,
            new RoomNumber($command->number),
            new RoomFloor($command->floor),
            $command->createdAt,
        ));
    }
}

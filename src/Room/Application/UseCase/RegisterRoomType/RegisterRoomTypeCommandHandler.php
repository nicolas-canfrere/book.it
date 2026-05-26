<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\RegisterRoomType;

use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomTypeAlreadyExistsException;
use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\BedComposition;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class RegisterRoomTypeCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomTypeRepositoryInterface $roomTypeRepository,
        private HotelExistsInterface $hotelExists,
    ) {
    }

    public function __invoke(RegisterRoomTypeCommand $command): void
    {
        if (!$this->hotelExists->exists($command->hotelId)) {
            throw new HotelNotFoundException($command->hotelId);
        }

        if ($this->roomTypeRepository->existsByHotelIdAndName($command->hotelId, $command->name)) {
            throw new RoomTypeAlreadyExistsException($command->name, $command->hotelId);
        }

        $this->roomTypeRepository->add(new RoomType(
            $command->id,
            $command->hotelId,
            $command->name,
            $command->livingSpaceCount,
            $command->surfaceM2,
            $command->guestCapacity,
            $command->isAccessible,
            BedComposition::fromArray($command->bedEntries),
            $command->createdAt,
        ));
    }
}

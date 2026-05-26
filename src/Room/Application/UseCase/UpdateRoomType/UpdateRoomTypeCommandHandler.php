<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\UpdateRoomType;

use App\Room\Domain\Exception\RoomTypeAlreadyExistsException;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\BedComposition;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class UpdateRoomTypeCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private RoomTypeRepositoryInterface $roomTypeRepository)
    {
    }

    public function __invoke(UpdateRoomTypeCommand $command): void
    {
        $roomType = $this->roomTypeRepository->get($command->id);
        if (null === $roomType) {
            throw new RoomTypeNotFoundException($command->id);
        }

        if ($roomType->name !== $command->name
            && $this->roomTypeRepository->existsByHotelIdAndName($roomType->hotelId, $command->name)
        ) {
            throw new RoomTypeAlreadyExistsException($command->name, $roomType->hotelId);
        }

        $this->roomTypeRepository->update(new RoomType(
            $roomType->id,
            $roomType->hotelId,
            $command->name,
            $command->livingSpaceCount,
            $command->surfaceM2,
            $command->guestCapacity,
            $command->isAccessible,
            BedComposition::fromArray($command->bedEntries),
            $roomType->createdAt,
        ));
    }
}

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
use App\Shared\Domain\Event\RoomTypeRegistered;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class RegisterRoomTypeCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomTypeRepositoryInterface $roomTypeRepository,
        private HotelExistsInterface $hotelExists,
        private EventDispatcherInterface $eventDispatcher,
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

        $roomType = new RoomType(
            $command->id,
            $command->hotelId,
            $command->name,
            $command->livingSpaceCount,
            $command->surfaceM2,
            $command->guestCapacity,
            $command->isAccessible,
            BedComposition::fromArray($command->bedEntries),
            $command->createdAt,
        );

        $this->roomTypeRepository->add($roomType);

        $this->eventDispatcher->dispatch(new RoomTypeRegistered(
            roomTypeId: $roomType->id->value,
            hotelId: $roomType->hotelId,
            name: $roomType->name,
            guestCapacity: $roomType->guestCapacity,
            bedComposition: $roomType->bedComposition->toArray(),
            createdAt: $roomType->createdAt,
        ));
    }
}

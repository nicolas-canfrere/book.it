<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\DeclareRoomTypeAmenities;

use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\RoomAmenity;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class DeclareRoomTypeAmenitiesCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomTypeRepositoryInterface $roomTypeRepository,
    ) {
    }

    public function __invoke(DeclareRoomTypeAmenitiesCommand $command): void
    {
        $roomType = $this->roomTypeRepository->get($command->roomTypeId);

        if (null === $roomType) {
            throw new RoomTypeNotFoundException($command->roomTypeId);
        }

        $amenities = array_map(RoomAmenity::from(...), $command->amenities);

        $this->roomTypeRepository->save($roomType->withAmenities($amenities));
    }
}

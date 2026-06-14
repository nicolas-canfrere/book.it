<?php

declare(strict_types=1);

namespace App\Room\Application\Service;

use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Domain\Port\RoomTypeIdGeneratorInterface;
use App\Shared\Domain\ValueObject\HotelId;
use Psr\Clock\ClockInterface;

final readonly class RegisterRoomTypeCommandFactory
{
    public function __construct(
        private RoomTypeIdGeneratorInterface $roomTypeIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    /** @param list<array{type: string, count: int}> $bedEntries */
    public function create(
        string $hotelId,
        string $name,
        int $livingSpaceCount,
        ?int $surfaceM2,
        int $guestCapacity,
        bool $isAccessible,
        array $bedEntries,
    ): RegisterRoomTypeCommand {
        return new RegisterRoomTypeCommand(
            id: $this->roomTypeIdGenerator->generate(),
            hotelId: new HotelId($hotelId),
            name: $name,
            livingSpaceCount: $livingSpaceCount,
            surfaceM2: $surfaceM2,
            guestCapacity: $guestCapacity,
            isAccessible: $isAccessible,
            bedEntries: $bedEntries,
            createdAt: $this->clock->now(),
        );
    }
}

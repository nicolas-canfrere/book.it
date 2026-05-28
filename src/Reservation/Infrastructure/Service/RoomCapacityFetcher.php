<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\RoomCapacityFetcherInterface;
use App\Room\Application\UseCase\GetRoom\GetRoomQuery;
use App\Room\Application\UseCase\GetRoomType\GetRoomTypeQuery;
use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomType;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class RoomCapacityFetcher implements RoomCapacityFetcherInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function fetchCapacity(string $roomId): int
    {
        /** @var Room|null $room */
        $room = $this->queryBus->ask(new GetRoomQuery($roomId));

        if (null === $room) {
            return 0;
        }

        /** @var RoomType|null $roomType */
        $roomType = $this->queryBus->ask(new GetRoomTypeQuery($room->roomTypeId));

        if (null === $roomType) {
            return 0;
        }

        return $roomType->guestCapacity;
    }
}

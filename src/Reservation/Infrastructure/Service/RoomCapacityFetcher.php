<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\RoomCapacityFetcherInterface;
use App\Room\Application\UseCase\GetRoomCapacity\GetRoomCapacityQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class RoomCapacityFetcher implements RoomCapacityFetcherInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function fetchCapacity(string $roomId): int
    {
        /** @var int $capacity */
        $capacity = $this->queryBus->ask(new GetRoomCapacityQuery($roomId));

        return $capacity;
    }
}

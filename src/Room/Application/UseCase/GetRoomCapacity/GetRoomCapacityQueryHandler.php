<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\GetRoomCapacity;

use App\Room\Domain\Port\RoomCapacityFinderInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetRoomCapacityQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private RoomCapacityFinderInterface $roomCapacityFinder,
    ) {
    }

    public function __invoke(GetRoomCapacityQuery $query): int
    {
        return $this->roomCapacityFinder->findCapacity($query->roomId);
    }
}

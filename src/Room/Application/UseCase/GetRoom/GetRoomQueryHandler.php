<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\GetRoom;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetRoomQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
    ) {
    }

    public function __invoke(GetRoomQuery $query): ?Room
    {
        return $this->roomRepository->get($query->roomId);
    }
}

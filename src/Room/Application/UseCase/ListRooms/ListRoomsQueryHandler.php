<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\ListRooms;

use App\Room\Domain\Model\RoomPage;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class ListRoomsQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
    ) {
    }

    public function __invoke(ListRoomsQuery $query): RoomPage
    {
        return $this->roomRepository->list($query->hotelId, $query->page, $query->limit);
    }
}

<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\ListRoomTypes;

use App\Room\Domain\Model\RoomTypePage;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class ListRoomTypesQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private RoomTypeRepositoryInterface $roomTypeRepository) {}

    public function __invoke(ListRoomTypesQuery $query): RoomTypePage
    {
        return $this->roomTypeRepository->list($query->hotelId, $query->page, $query->limit);
    }
}

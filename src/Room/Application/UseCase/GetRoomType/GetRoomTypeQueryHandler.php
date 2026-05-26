<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\GetRoomType;

use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetRoomTypeQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private RoomTypeRepositoryInterface $roomTypeRepository)
    {
    }

    public function __invoke(GetRoomTypeQuery $query): ?RoomType
    {
        return $this->roomTypeRepository->get($query->id);
    }
}

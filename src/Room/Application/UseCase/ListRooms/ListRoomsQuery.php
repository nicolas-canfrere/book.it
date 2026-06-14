<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\ListRooms;

use App\Room\Domain\Model\RoomPage;
use App\Shared\Application\Bus\SyncQueryInterface;
use App\Shared\Domain\ValueObject\HotelId;

/**
 * @implements SyncQueryInterface<RoomPage>
 */
final readonly class ListRoomsQuery implements SyncQueryInterface
{
    public function __construct(
        public HotelId $hotelId,
        public int $page = 1,
        public int $limit = 20,
    ) {
    }
}

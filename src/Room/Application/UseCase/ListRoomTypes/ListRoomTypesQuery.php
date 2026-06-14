<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\ListRoomTypes;

use App\Room\Domain\Model\RoomTypePage;
use App\Shared\Application\Bus\SyncQueryInterface;
use App\Shared\Domain\ValueObject\HotelId;

/**
 * @implements SyncQueryInterface<RoomTypePage>
 */
final readonly class ListRoomTypesQuery implements SyncQueryInterface
{
    public function __construct(
        public HotelId $hotelId,
        public int $page,
        public int $limit,
    ) {
    }
}

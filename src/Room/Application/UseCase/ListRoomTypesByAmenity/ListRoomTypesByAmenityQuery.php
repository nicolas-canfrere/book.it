<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\ListRoomTypesByAmenity;

use App\Room\Domain\Model\RoomTypePage;
use App\Shared\Application\Bus\SyncQueryInterface;

/**
 * @implements SyncQueryInterface<RoomTypePage>
 */
final readonly class ListRoomTypesByAmenityQuery implements SyncQueryInterface
{
    /** @param string[] $amenities */
    public function __construct(
        public string $hotelId,
        public array $amenities,
        public int $page,
        public int $limit,
    ) {
    }
}

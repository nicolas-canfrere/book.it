<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\DeclareRoomTypeAmenities;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\RoomTypeId;

final readonly class DeclareRoomTypeAmenitiesCommand implements SyncCommandInterface
{
    /**
     * @param string[] $amenities
     */
    public function __construct(
        public RoomTypeId $roomTypeId,
        public array $amenities,
    ) {
    }
}

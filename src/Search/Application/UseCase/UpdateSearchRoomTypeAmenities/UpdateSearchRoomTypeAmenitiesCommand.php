<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchRoomTypeAmenities;

use App\Shared\Application\Bus\AsyncCommandInterface;
use App\Shared\Domain\ValueObject\RoomTypeId;

/** @param string[] $amenities */
final readonly class UpdateSearchRoomTypeAmenitiesCommand implements AsyncCommandInterface
{
    /** @param string[] $amenities */
    public function __construct(
        public RoomTypeId $roomTypeId,
        public array $amenities,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchRoomTypeAmenities;

use App\Shared\Application\Bus\AsyncCommandInterface;

/** @param string[] $amenities */
final readonly class UpdateSearchRoomTypeAmenitiesCommand implements AsyncCommandInterface
{
    /** @param string[] $amenities */
    public function __construct(
        public string $roomTypeId,
        public array $amenities,
    ) {
    }
}

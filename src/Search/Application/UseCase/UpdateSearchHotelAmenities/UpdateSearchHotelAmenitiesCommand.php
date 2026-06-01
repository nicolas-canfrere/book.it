<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchHotelAmenities;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class UpdateSearchHotelAmenitiesCommand implements AsyncCommandInterface
{
    /** @param string[] $amenities */
    public function __construct(
        public string $hotelId,
        public array $amenities,
    ) {
    }
}

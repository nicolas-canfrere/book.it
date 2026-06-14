<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchHotelAmenities;

use App\Shared\Application\Bus\AsyncCommandInterface;
use App\Shared\Domain\ValueObject\HotelId;

final readonly class UpdateSearchHotelAmenitiesCommand implements AsyncCommandInterface
{
    /** @param string[] $amenities */
    public function __construct(
        public HotelId $hotelId,
        public array $amenities,
    ) {
    }
}

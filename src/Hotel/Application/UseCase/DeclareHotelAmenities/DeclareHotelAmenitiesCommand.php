<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\DeclareHotelAmenities;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class DeclareHotelAmenitiesCommand implements SyncCommandInterface
{
    /**
     * @param string[] $amenities
     */
    public function __construct(
        public string $hotelId,
        public array $amenities,
    ) {
    }
}

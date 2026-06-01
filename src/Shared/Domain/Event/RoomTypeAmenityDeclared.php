<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class RoomTypeAmenityDeclared
{
    /** @param string[] $amenities */
    public function __construct(
        public string $roomTypeId,
        public string $hotelId,
        public array $amenities,
    ) {
    }
}

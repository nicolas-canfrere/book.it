<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class HotelAmenityDeclared
{
    /** @param string[] $amenities */
    public function __construct(
        public string $hotelId,
        public array $amenities,
    ) {
    }
}

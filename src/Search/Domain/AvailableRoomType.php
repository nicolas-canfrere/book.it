<?php

declare(strict_types=1);

namespace App\Search\Domain;

final readonly class AvailableRoomType
{
    /**
     * @param list<string>         $hotelAmenities
     * @param array<string, mixed> $bedComposition
     * @param list<string>         $roomAmenities
     */
    public function __construct(
        public string $hotelId,
        public string $hotelName,
        public string $city,
        public string $country,
        public ?int $starRating,
        public array $hotelAmenities,
        public string $roomTypeId,
        public string $roomTypeName,
        public int $guestCapacity,
        public array $bedComposition,
        public array $roomAmenities,
        public ?int $basePriceCents,
    ) {
    }
}

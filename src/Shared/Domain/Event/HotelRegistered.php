<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class HotelRegistered
{
    public function __construct(
        public string $hotelId,
        public string $name,
        public string $city,
        public string $country,
        public ?int $starRating,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

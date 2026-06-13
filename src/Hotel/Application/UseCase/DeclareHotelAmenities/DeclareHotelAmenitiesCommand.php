<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\DeclareHotelAmenities;

use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\HotelId;

final readonly class DeclareHotelAmenitiesCommand implements SyncCommandInterface
{
    /**
     * @param list<HotelAmenity> $amenities
     */
    public function __construct(
        public HotelId $hotelId,
        public array $amenities,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

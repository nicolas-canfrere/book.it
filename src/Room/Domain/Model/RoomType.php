<?php

declare(strict_types=1);

namespace App\Room\Domain\Model;

use App\Room\Domain\ValueObject\BedComposition;
use App\Room\Domain\ValueObject\RoomAmenity;

final readonly class RoomType
{
    /**
     * @param array<RoomAmenity> $amenities
     */
    public function __construct(
        public string $id,
        public string $hotelId,
        public string $name,
        public int $livingSpaceCount,
        public ?int $surfaceM2,
        public int $guestCapacity,
        public bool $isAccessible,
        public BedComposition $bedComposition,
        public \DateTimeImmutable $createdAt,
        public array $amenities = [],
    ) {
    }

    /**
     * @param array<RoomAmenity> $amenities
     */
    public function withAmenities(array $amenities): self
    {
        return new self(
            id: $this->id,
            hotelId: $this->hotelId,
            name: $this->name,
            livingSpaceCount: $this->livingSpaceCount,
            surfaceM2: $this->surfaceM2,
            guestCapacity: $this->guestCapacity,
            isAccessible: $this->isAccessible,
            bedComposition: $this->bedComposition,
            createdAt: $this->createdAt,
            amenities: $amenities,
        );
    }
}

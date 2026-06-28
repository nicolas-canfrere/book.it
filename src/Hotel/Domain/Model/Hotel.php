<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Model;

use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\OrganizationId;

final readonly class Hotel
{
    /**
     * @param array<HotelAmenity> $amenities
     */
    public function __construct(
        public HotelId $id,
        public string $name,
        public Address $address,
        public \DateTimeImmutable $createdAt,
        public OrganizationId $organizationId,
        public ?StarRating $starRating = null,
        public array $amenities = [],
    ) {
    }

    public function withStarRating(?StarRating $starRating): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            address: $this->address,
            createdAt: $this->createdAt,
            organizationId: $this->organizationId,
            starRating: $starRating,
            amenities: $this->amenities,
        );
    }

    /**
     * @param array<HotelAmenity> $amenities
     */
    public function withAmenities(array $amenities): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            address: $this->address,
            createdAt: $this->createdAt,
            organizationId: $this->organizationId,
            starRating: $this->starRating,
            amenities: $amenities,
        );
    }
}

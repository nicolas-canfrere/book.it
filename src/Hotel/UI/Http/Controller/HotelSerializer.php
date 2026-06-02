<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller;

use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\ValueObject\HotelAmenity;

final class HotelSerializer
{
    /**
     * @return array{id: string, name: string, streetAddress: string, postalCode: string, city: string, country: string, createdAt: string, starRating: array{stars: int, superior: bool}|null, amenities: string[]}
     */
    public function serialize(Hotel $hotel): array
    {
        return [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'streetAddress' => $hotel->address->streetAddress,
            'postalCode' => $hotel->address->postalCode,
            'city' => $hotel->address->city,
            'country' => $hotel->address->country,
            'createdAt' => $hotel->createdAt->format(\DateTimeInterface::ATOM),
            'starRating' => null !== $hotel->starRating
                ? ['stars' => $hotel->starRating->stars, 'superior' => $hotel->starRating->superior]
                : null,
            'amenities' => array_map(static fn(HotelAmenity $a) => $a->value, $hotel->amenities),
        ];
    }
}

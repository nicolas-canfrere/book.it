<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\RegisterHotel;

use App\Hotel\Domain\Model\Hotel;

final class RegisteredHotelSerializer
{
    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     streetAddress: string,
     *     postalCode: string,
     *     city: string,
     *     country: string,
     *     createdAt: int
     * }
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
            'createdAt' => $hotel->createdAt->getTimestamp(),
        ];
    }
}

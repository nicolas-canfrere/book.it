<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\ListHotels;

use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;

final class HotelCatalogueSerializer
{
    /**
     * @return array{
     *     data: list<array{id: string, name: string, streetAddress: string, postalCode: string, city: string, country: string, createdAt: int}>,
     *     meta: array{page: int, limit: int, total: int, totalPages: int}
     * }
     */
    public function serialize(HotelPage $hotelPage, int $page, int $limit): array
    {
        return [
            'data' => array_map(
                static fn(Hotel $hotel) => [
                    'id' => $hotel->id,
                    'name' => $hotel->name,
                    'streetAddress' => $hotel->address->streetAddress,
                    'postalCode' => $hotel->address->postalCode,
                    'city' => $hotel->address->city,
                    'country' => $hotel->address->country,
                    'createdAt' => $hotel->createdAt->getTimestamp(),
                ],
                $hotelPage->hotels,
            ),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $hotelPage->total,
                'totalPages' => (int) ceil($hotelPage->total / $limit),
            ],
        ];
    }
}

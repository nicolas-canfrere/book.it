<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\ListHotels;

use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\UI\Http\Controller\HotelSerializer;

final class HotelCatalogueSerializer
{
    public function __construct(
        private HotelSerializer $hotelSerializer,
    ) {
    }

    /**
     * @return array{
     *     data: list<array{id: string, name: string, streetAddress: string, postalCode: string, city: string, country: string, createdAt: int}>,
     *     meta: array{page: int, limit: int, total: int, totalPages: int}
     * }
     */
    public function serialize(HotelPage $hotelPage, int $page, int $limit): array
    {
        return [
            'data' => array_map($this->hotelSerializer->serialize(...), $hotelPage->hotels),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $hotelPage->total,
                'totalPages' => (int) ceil($hotelPage->total / $limit),
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\ListRooms;

use App\Room\Domain\Model\RoomPage;
use App\Room\UI\Http\Controller\RoomSerializer;

final class RoomCatalogueSerializer
{
    public function __construct(private RoomSerializer $roomSerializer)
    {
    }

    /**
     * @return array{
     *     data: list<array{id: string, hotelId: string, number: string, floor: int, roomTypeId: string, createdAt: string}>,
     *     meta: array{page: int, limit: int, total: int, totalPages: int}
     * }
     */
    public function serialize(RoomPage $roomPage, int $page, int $limit): array
    {
        return [
            'data' => array_map($this->roomSerializer->serialize(...), $roomPage->rooms),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $roomPage->total,
                'totalPages' => (int) ceil($roomPage->total / $limit),
            ],
        ];
    }
}

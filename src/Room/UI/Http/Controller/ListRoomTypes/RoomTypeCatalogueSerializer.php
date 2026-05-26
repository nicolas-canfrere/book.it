<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\ListRoomTypes;

use App\Room\Domain\Model\RoomTypePage;
use App\Room\UI\Http\Controller\RoomTypeSerializer;

final class RoomTypeCatalogueSerializer
{
    public function __construct(private RoomTypeSerializer $roomTypeSerializer)
    {
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, limit: int, total: int, totalPages: int}}
     */
    public function serialize(RoomTypePage $page, int $pageNum, int $limit): array
    {
        return [
            'data' => array_map($this->roomTypeSerializer->serialize(...), $page->roomTypes),
            'meta' => [
                'page' => $pageNum,
                'limit' => $limit,
                'total' => $page->total,
                'totalPages' => (int) ceil($page->total / $limit),
            ],
        ];
    }
}

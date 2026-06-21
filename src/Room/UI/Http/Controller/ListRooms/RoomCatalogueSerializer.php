<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\ListRooms;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomPage;
use App\Room\UI\Http\Controller\RoomSerializer;

final class RoomCatalogueSerializer
{
    public function __construct(private RoomSerializer $roomSerializer)
    {
    }

    /**
     * @param array<string, int> $baseRateAmountCentsByRoomId
     *
     * @return array{
     *     data: list<array{id: string, hotelId: string, number: string, floor: int, roomTypeId: string, createdAt: string, baseRateAmountCents: ?int}>,
     *     meta: array{page: int, limit: int, total: int, totalPages: int}
     * }
     */
    public function serialize(RoomPage $roomPage, array $baseRateAmountCentsByRoomId, int $page, int $limit): array
    {
        return [
            'data' => array_map(
                fn(Room $room) => [
                    ...$this->roomSerializer->serialize($room),
                    'baseRateAmountCents' => $baseRateAmountCentsByRoomId[$room->id->value] ?? null,
                ],
                $roomPage->rooms,
            ),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $roomPage->total,
                'totalPages' => (int) ceil($roomPage->total / $limit),
            ],
        ];
    }
}

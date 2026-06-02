<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller;

use App\Room\Domain\Model\Room;

final class RoomSerializer
{
    /**
     * @return array{id: string, hotelId: string, number: string, floor: int, roomTypeId: string, createdAt: string}
     */
    public function serialize(Room $room): array
    {
        return [
            'id' => $room->id,
            'hotelId' => $room->hotelId,
            'number' => $room->number->value,
            'floor' => $room->floor->value,
            'roomTypeId' => $room->roomTypeId,
            'createdAt' => $room->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

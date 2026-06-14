<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller;

use App\Room\Domain\Model\RoomType;

final class RoomTypeSerializer
{
    /**
     * @return array{id: string, hotelId: string, name: string, livingSpaceCount: int, surfaceM2: int|null, guestCapacity: int, isAccessible: bool, bedComposition: list<array{type: string, count: int}>, amenities: list<string>, createdAt: string}
     */
    public function serialize(RoomType $roomType): array
    {
        return [
            'id' => $roomType->id->value,
            'hotelId' => $roomType->hotelId,
            'name' => $roomType->name,
            'livingSpaceCount' => $roomType->livingSpaceCount,
            'surfaceM2' => $roomType->surfaceM2,
            'guestCapacity' => $roomType->guestCapacity,
            'isAccessible' => $roomType->isAccessible,
            'bedComposition' => $roomType->bedComposition->toArray(),
            'amenities' => array_values(array_map(static fn($a) => $a->value, $roomType->amenities)),
            'createdAt' => $roomType->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

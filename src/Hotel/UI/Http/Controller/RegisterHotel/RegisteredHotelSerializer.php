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
     *     createdAt: int
     * }
     */
    public function serialize(Hotel $hotel): array
    {
        return [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'createdAt' => $hotel->createdAt->getTimestamp(),
        ];
    }
}

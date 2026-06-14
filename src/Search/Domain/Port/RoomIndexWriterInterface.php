<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

use App\Shared\Domain\ValueObject\HotelId;

interface RoomIndexWriterInterface
{
    public function upsert(string $roomId, string $roomTypeId, HotelId $hotelId): void;
}

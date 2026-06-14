<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomId;

interface RoomIndexWriterInterface
{
    public function upsert(RoomId $roomId, string $roomTypeId, HotelId $hotelId): void;
}

<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;

interface RoomIndexWriterInterface
{
    public function upsert(RoomId $roomId, RoomTypeId $roomTypeId, HotelId $hotelId): void;
}

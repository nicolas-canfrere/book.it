<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

interface RoomIndexWriterInterface
{
    public function upsert(string $roomId, string $roomTypeId, string $hotelId): void;
}

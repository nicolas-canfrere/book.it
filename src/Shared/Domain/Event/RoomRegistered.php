<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class RoomRegistered
{
    public function __construct(
        public string $roomId,
        public string $hotelId,
        public string $roomTypeId,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class RoomTypeDeleted
{
    public function __construct(
        public string $roomTypeId,
        public string $hotelId,
    ) {
    }
}

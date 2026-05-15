<?php

declare(strict_types=1);

namespace App\Room\Domain\Model;

final readonly class RoomPage
{
    /** @param list<Room> $rooms */
    public function __construct(
        public array $rooms,
        public int $total,
    ) {
    }
}

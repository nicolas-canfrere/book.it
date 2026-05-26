<?php

declare(strict_types=1);

namespace App\Room\Domain\Model;

final readonly class RoomTypePage
{
    /** @param list<RoomType> $roomTypes */
    public function __construct(
        public array $roomTypes,
        public int $total,
    ) {
    }
}

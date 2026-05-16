<?php

declare(strict_types=1);

namespace App\Room\Application\Service;

final readonly class RoomCsvRow
{
    public function __construct(
        public string $number,
        public int $floor,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Room\Application\Contract;

// Intentionally exposes only id + capacity: current consumers need existence checks and capacity. Extend when a consumer requires more fields.
final readonly class RoomView
{
    public function __construct(
        public string $id,
        public int $capacity,
    ) {
    }
}

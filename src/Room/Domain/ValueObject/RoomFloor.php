<?php

declare(strict_types=1);

namespace App\Room\Domain\ValueObject;

final readonly class RoomFloor
{
    public int $value;

    public function __construct(int $value)
    {
        if ($value < -20 || $value > 300) {
            throw new \InvalidArgumentException('Room floor must be between -20 and 300.');
        }
        $this->value = $value;
    }
}

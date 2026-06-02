<?php

declare(strict_types=1);

namespace App\Room\Domain\ValueObject;

final readonly class RoomFloor
{
    public const int MIN_FLOOR = -20;
    public const int MAX_FLOOR = 300;

    public int $value;

    public function __construct(int $value)
    {
        if ($value < self::MIN_FLOOR || $value > self::MAX_FLOOR) {
            throw new \InvalidArgumentException('Room floor must be between -20 and 300.');
        }
        $this->value = $value;
    }
}

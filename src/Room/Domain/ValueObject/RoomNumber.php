<?php

declare(strict_types=1);

namespace App\Room\Domain\ValueObject;

final readonly class RoomNumber
{
    public const int MAX_LENGTH = 50;

    public string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Room number must not be blank.');
        }
        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException('Room number must not exceed 50 characters.');
        }
        $this->value = $trimmed;
    }
}

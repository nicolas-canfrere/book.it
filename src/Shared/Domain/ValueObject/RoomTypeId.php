<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class RoomTypeId
{
    public function __construct(public string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

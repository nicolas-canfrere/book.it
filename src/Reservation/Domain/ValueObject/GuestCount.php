<?php

declare(strict_types=1);

namespace App\Reservation\Domain\ValueObject;

final readonly class GuestCount
{
    public function __construct(public int $value)
    {
        if ($value < 1 || $value > 20) {
            throw new \InvalidArgumentException(
                sprintf('Guest count must be between 1 and 20, got %d.', $value)
            );
        }
    }
}

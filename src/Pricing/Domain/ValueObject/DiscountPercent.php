<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

final class DiscountPercent
{
    public readonly int $value;

    public function __construct(int $value)
    {
        if ($value < 1 || $value > 99) {
            throw new \InvalidArgumentException(
                sprintf('Discount percent must be between 1 and 99, %d given.', $value)
            );
        }
        $this->value = $value;
    }
}

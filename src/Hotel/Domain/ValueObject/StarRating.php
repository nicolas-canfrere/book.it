<?php

declare(strict_types=1);

namespace App\Hotel\Domain\ValueObject;

final readonly class StarRating
{
    public function __construct(
        public int $stars,
        public bool $superior,
    ) {
        if ($stars < 1 || $stars > 5) {
            throw new \InvalidArgumentException(
                sprintf('Stars must be between 1 and 5, %d given.', $stars)
            );
        }
    }
}

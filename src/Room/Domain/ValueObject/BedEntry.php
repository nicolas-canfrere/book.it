<?php

declare(strict_types=1);

namespace App\Room\Domain\ValueObject;

final readonly class BedEntry
{
    public function __construct(
        public BedType $type,
        public int $count,
    ) {
        if ($count < 1 || $count > 10) {
            throw new \InvalidArgumentException(sprintf('Bed count must be between 1 and 10, got %d.', $count));
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class BaseRateSet
{
    public function __construct(
        public string $roomId,
        public int $amountCents,
    ) {
    }
}

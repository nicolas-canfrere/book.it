<?php

declare(strict_types=1);

namespace App\Reservation\Domain\ValueObject;

final readonly class NightPrice
{
    public function __construct(
        public string $date,
        public int $rateAmountCents,
        public ?int $discountPercent,
        public int $effectiveAmountCents,
    ) {
    }
}

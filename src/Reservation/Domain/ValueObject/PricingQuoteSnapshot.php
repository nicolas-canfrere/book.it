<?php

declare(strict_types=1);

namespace App\Reservation\Domain\ValueObject;

final readonly class PricingQuoteSnapshot
{
    public function __construct(
        public int $totalAmountCents,
        public PriceBreakdown $breakdown,
    ) {
    }
}

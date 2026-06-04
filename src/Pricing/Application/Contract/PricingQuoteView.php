<?php

declare(strict_types=1);

namespace App\Pricing\Application\Contract;

final readonly class PricingQuoteView
{
    /**
     * @param list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}> $nights
     */
    public function __construct(
        public int $totalAmountCents,
        public array $nights,
    ) {
    }
}

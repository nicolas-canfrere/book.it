<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\PricingQuoteCalculatorInterface;
use App\Pricing\Application\Contract\PricingQuoteFinderInterface;
use App\Pricing\Application\Contract\PricingQuoteView;

final readonly class DoctrinePricingQuoteFinder implements PricingQuoteFinderInterface
{
    public function __construct(private PricingQuoteCalculatorInterface $calculator)
    {
    }

    public function fetch(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteView
    {
        $result = $this->calculator->calculate($roomId, $checkIn, $checkOut);

        return new PricingQuoteView(
            totalAmountCents: $result['totalAmountCents'],
            nights: $result['nights'],
        );
    }
}

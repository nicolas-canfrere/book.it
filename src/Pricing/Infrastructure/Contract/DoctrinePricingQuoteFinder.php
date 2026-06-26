<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\PricingQuoteFinderInterface;
use App\Pricing\Application\Contract\PricingQuoteView;
use App\Pricing\Domain\Service\PricingQuoteCalculatorInterface;
use App\Pricing\Domain\ValueObject\NightPricingDetail;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class DoctrinePricingQuoteFinder implements PricingQuoteFinderInterface
{
    public function __construct(private PricingQuoteCalculatorInterface $calculator)
    {
    }

    public function fetch(RoomId $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteView
    {
        $quote = $this->calculator->calculate($roomId, $checkIn, $checkOut);

        return new PricingQuoteView(
            totalAmountCents: $quote->totalAmountCents,
            nights: array_map(
                static fn(NightPricingDetail $n) => [
                    'date' => $n->date->format('Y-m-d'),
                    'rateAmountCents' => $n->rateAmountCents,
                    'discountPercent' => $n->discountPercent,
                    'effectiveAmountCents' => $n->effectiveAmountCents,
                ],
                $quote->nights,
            ),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Pricing\Application\Contract;

interface PricingQuoteCalculatorInterface
{
    /**
     * @return array{roomId: string, checkIn: string, checkOut: string, totalAmountCents: int, nights: list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}>}
     *
     * @throws \DomainException if room does not exist or has no base rate
     */
    public function calculate(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): array;
}

<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Service;

use App\Pricing\Domain\ValueObject\PricingQuote;
use App\Shared\Domain\ValueObject\RoomId;

interface PricingQuoteCalculatorInterface
{
    /**
     * @throws \DomainException if room does not exist or has no base rate
     */
    public function calculate(RoomId $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuote;
}

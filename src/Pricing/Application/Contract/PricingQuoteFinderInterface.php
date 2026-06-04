<?php

declare(strict_types=1);

namespace App\Pricing\Application\Contract;

interface PricingQuoteFinderInterface
{
    /**
     * @throws \DomainException if the room has no base rate or does not exist
     */
    public function fetch(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteView;
}

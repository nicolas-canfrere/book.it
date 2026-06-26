<?php

declare(strict_types=1);

namespace App\Pricing\Application\Contract;

use App\Shared\Domain\ValueObject\RoomId;

interface PricingQuoteFinderInterface
{
    /**
     * @throws \DomainException if the room has no base rate or does not exist
     */
    public function fetch(RoomId $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteView;
}

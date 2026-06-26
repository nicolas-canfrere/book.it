<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

use App\Shared\Domain\ValueObject\RoomId;

final readonly class PricingQuote
{
    /**
     * @param list<NightPricingDetail> $nights
     */
    public function __construct(
        public RoomId $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $totalAmountCents,
        public array $nights,
    ) {
    }
}

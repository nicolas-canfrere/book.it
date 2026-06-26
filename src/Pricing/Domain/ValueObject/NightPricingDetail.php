<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

final readonly class NightPricingDetail
{
    public function __construct(
        public \DateTimeImmutable $date,
        public int $rateAmountCents,
        public ?int $discountPercent,
        public int $effectiveAmountCents,
    ) {
    }
}

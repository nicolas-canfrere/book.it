<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetPricingQuote;

use App\Shared\Application\Bus\SyncQueryInterface;

/**
 * @implements SyncQueryInterface<array{
 *     roomId: string,
 *     checkIn: string,
 *     checkOut: string,
 *     totalAmountCents: int,
 *     nights: list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}>
 * }>
 */
final readonly class GetPricingQuoteQuery implements SyncQueryInterface
{
    public function __construct(
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}

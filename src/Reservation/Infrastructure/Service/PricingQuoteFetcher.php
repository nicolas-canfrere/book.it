<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Pricing\Application\UseCase\GetPricingQuote\GetPricingQuoteQuery;
use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Port\PricingQuoteFetcherInterface;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Reservation\Domain\ValueObject\PricingQuoteSnapshot;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class PricingQuoteFetcher implements PricingQuoteFetcherInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function fetch(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteSnapshot
    {
        try {
            /** @var array{totalAmountCents: int, nights: list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}>} $result */
            $result = $this->queryBus->ask(new GetPricingQuoteQuery($roomId, $checkIn, $checkOut));

            return new PricingQuoteSnapshot(
                $result['totalAmountCents'],
                PriceBreakdown::fromArray($result['nights']),
            );
        } catch (\DomainException) {
            throw new RoomNotBookableException($roomId);
        }
    }
}

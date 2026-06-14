<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Port\PricingQuoteFetcherInterface;
use App\Reservation\Domain\ValueObject\NightPrice;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Reservation\Domain\ValueObject\PricingQuoteSnapshot;
use App\Shared\Domain\ValueObject\RoomId;

final class FakePricingQuoteFetcher implements PricingQuoteFetcherInterface
{
    private ?PricingQuoteSnapshot $snapshot;

    public function __construct()
    {
        $this->snapshot = new PricingQuoteSnapshot(
            42000,
            new PriceBreakdown([
                new NightPrice('2026-06-01', 10500, null, 10500),
                new NightPrice('2026-06-02', 10500, null, 10500),
                new NightPrice('2026-06-03', 10500, null, 10500),
                new NightPrice('2026-06-04', 10500, null, 10500),
            ]),
        );
    }

    public function setSnapshot(?PricingQuoteSnapshot $snapshot): void
    {
        $this->snapshot = $snapshot;
    }

    public function fetch(RoomId $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteSnapshot
    {
        if (null === $this->snapshot) {
            throw new RoomNotBookableException($roomId);
        }

        return $this->snapshot;
    }
}

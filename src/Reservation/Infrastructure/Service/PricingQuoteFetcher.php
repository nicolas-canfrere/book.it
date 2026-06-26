<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Pricing\Application\Contract\PricingQuoteFinderInterface;
use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Port\PricingQuoteFetcherInterface;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Reservation\Domain\ValueObject\PricingQuoteSnapshot;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class PricingQuoteFetcher implements PricingQuoteFetcherInterface
{
    public function __construct(private PricingQuoteFinderInterface $pricingFinder)
    {
    }

    public function fetch(RoomId $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteSnapshot
    {
        try {
            $view = $this->pricingFinder->fetch($roomId, $checkIn, $checkOut);

            return new PricingQuoteSnapshot(
                $view->totalAmountCents,
                PriceBreakdown::fromArray($view->nights),
            );
        } catch (\DomainException) {
            throw new RoomNotBookableException($roomId);
        }
    }
}

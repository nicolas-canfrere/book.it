<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Pricing\Application\UseCase\GetPricingQuote\GetPricingQuoteQuery;
use App\Pricing\Domain\Exception\RoomHasNoBaseRateException;
use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Port\PriceCalculatorInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class PricingCalculator implements PriceCalculatorInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function calculate(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): int
    {
        try {
            /** @var array{totalAmountCents: int} $result */
            $result = $this->queryBus->ask(new GetPricingQuoteQuery($roomId, $checkIn, $checkOut));

            return $result['totalAmountCents'];
        } catch (RoomHasNoBaseRateException) {
            throw new RoomNotBookableException($roomId);
        }
    }
}

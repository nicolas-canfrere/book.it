<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetPricingQuote;

use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Pricing\Domain\Service\PricingCalculator;
use App\Pricing\Domain\ValueObject\PricingQuote;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetPricingQuoteQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private RoomExistsInterface $roomExists,
        private PricingCalculator $calculator,
    ) {
    }

    public function __invoke(GetPricingQuoteQuery $query): PricingQuote
    {
        if (!$this->roomExists->exists($query->roomId)) {
            throw new RoomNotFoundException($query->roomId);
        }

        return $this->calculator->calculate($query->roomId, $query->checkIn, $query->checkOut);
    }
}

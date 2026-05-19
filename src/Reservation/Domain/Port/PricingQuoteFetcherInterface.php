<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\ValueObject\PricingQuoteSnapshot;

interface PricingQuoteFetcherInterface
{
    /**
     * @throws RoomNotBookableException when no base rate is configured for the room
     */
    public function fetch(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteSnapshot;
}

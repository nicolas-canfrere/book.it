<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Event;

use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\PriceBreakdown;

final readonly class ReservationCreated
{
    public function __construct(
        public string $reservationId,
        public string $roomId,
        public string $bookerId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $totalPrice,
        public CancellationTerms $cancellationTerms,
        public PriceBreakdown $priceBreakdown,
    ) {
    }
}

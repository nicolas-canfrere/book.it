<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class ReservationCreated
{
    /**
     * @param list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}> $priceBreakdown
     */
    public function __construct(
        public string $reservationId,
        public string $roomId,
        public string $bookerId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $totalPrice,
        public ?int $cancellationTermsDaysThreshold,
        public array $priceBreakdown,
    ) {
    }
}

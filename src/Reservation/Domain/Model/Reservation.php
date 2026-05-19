<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

use App\Reservation\Domain\Exception\InvalidReservationTransitionException;
use App\Reservation\Domain\ValueObject\DatePeriod;

final class Reservation
{
    public ReservationStatus $status;

    public function __construct(
        public readonly string $id,
        public readonly string $roomId,
        public readonly string $bookerId,
        public readonly DatePeriod $period,
        public readonly int $totalPrice,
        public readonly \DateTimeImmutable $createdAt,
    ) {
        $this->status = ReservationStatus::Pending;
    }

    public function expire(): void
    {
        if (ReservationStatus::Pending !== $this->status) {
            throw new InvalidReservationTransitionException($this->status, ReservationStatus::Expired);
        }

        $this->status = ReservationStatus::Expired;
    }
}

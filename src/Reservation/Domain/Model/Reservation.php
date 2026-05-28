<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

use App\Reservation\Domain\Exception\CheckInNotAllowedException;
use App\Reservation\Domain\Exception\GuestPreRegistrationNotAllowedException;
use App\Reservation\Domain\Exception\InvalidReservationTransitionException;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;

final class Reservation
{
    public ReservationStatus $status;

    /** @var Guest[] */
    public array $guests = [];

    public function __construct(
        public readonly string $id,
        public readonly string $roomId,
        public readonly string $bookerId,
        public readonly DatePeriod $period,
        public readonly int $totalPrice,
        public readonly CancellationTerms $cancellationTerms,
        public readonly PriceBreakdown $priceBreakdown,
        public readonly GuestCount $guestCount,
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

    public function confirm(): void
    {
        if (ReservationStatus::Pending !== $this->status) {
            throw new InvalidReservationTransitionException($this->status, ReservationStatus::Confirmed);
        }

        $this->status = ReservationStatus::Confirmed;
    }

    public function cancelPending(): void
    {
        if (ReservationStatus::Pending !== $this->status) {
            throw new InvalidReservationTransitionException($this->status, ReservationStatus::Cancelled);
        }

        $this->status = ReservationStatus::Cancelled;
    }

    /**
     * @param Guest[] $guests
     */
    public function preRegisterGuests(array $guests, \DateTimeImmutable $today): void
    {
        if (!in_array($this->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
            throw GuestPreRegistrationNotAllowedException::dueToStatus($this->status);
        }

        if ($today >= $this->period->checkIn) {
            throw GuestPreRegistrationNotAllowedException::dueToDate($today, $this->period->checkIn);
        }

        $this->guests = $guests;
    }

    /**
     * @param Guest[] $guests
     */
    public function checkIn(array $guests, \DateTimeImmutable $today): void
    {
        if (ReservationStatus::Confirmed !== $this->status) {
            throw CheckInNotAllowedException::wrongStatus($this->status);
        }

        if ($today < $this->period->checkIn) {
            throw CheckInNotAllowedException::tooEarly($this->period->checkIn, $today);
        }

        $this->guests = $guests;
        $this->status = ReservationStatus::CheckedIn;
    }
}

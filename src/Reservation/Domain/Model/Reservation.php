<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

use App\Reservation\Domain\Exception\CancellationNotAllowedException;
use App\Reservation\Domain\Exception\CheckInNotAllowedException;
use App\Reservation\Domain\Exception\CheckOutNotAllowedException;
use App\Reservation\Domain\Exception\GuestPreRegistrationNotAllowedException;
use App\Reservation\Domain\Exception\InvalidReservationTransitionException;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Shared\Domain\ValueObject\BookerId;
use App\Shared\Domain\ValueObject\ReservationId;
use App\Shared\Domain\ValueObject\RoomId;

final class Reservation
{
    private ReservationStatus $status;

    /** @var Guest[] */
    private array $guests = [];

    private ?\DateTimeImmutable $actualDepartureDate = null;
    private ?\DateTimeImmutable $cancelledAt = null;
    private ?string $cancelledBy = null;

    public function __construct(
        public readonly ReservationId $id,
        public readonly RoomId $roomId,
        public readonly BookerId $bookerId,
        public readonly DatePeriod $period,
        public readonly int $totalPrice,
        public readonly CancellationTerms $cancellationTerms,
        public readonly PriceBreakdown $priceBreakdown,
        public readonly GuestCount $guestCount,
        public readonly \DateTimeImmutable $createdAt,
    ) {
        $this->status = ReservationStatus::Pending;
    }

    /**
     * @param Guest[] $guests
     */
    public static function reconstitute(
        ReservationId $id,
        RoomId $roomId,
        BookerId $bookerId,
        DatePeriod $period,
        int $totalPrice,
        CancellationTerms $cancellationTerms,
        PriceBreakdown $priceBreakdown,
        GuestCount $guestCount,
        \DateTimeImmutable $createdAt,
        ReservationStatus $status,
        array $guests = [],
        ?\DateTimeImmutable $actualDepartureDate = null,
        ?\DateTimeImmutable $cancelledAt = null,
        ?string $cancelledBy = null,
    ): self {
        $self = new self($id, $roomId, $bookerId, $period, $totalPrice, $cancellationTerms, $priceBreakdown, $guestCount, $createdAt);
        $self->status = $status;
        $self->guests = $guests;
        $self->actualDepartureDate = $actualDepartureDate;
        $self->cancelledAt = $cancelledAt;
        $self->cancelledBy = $cancelledBy;

        return $self;
    }

    public function status(): ReservationStatus
    {
        return $this->status;
    }

    /** @return Guest[] */
    public function guests(): array
    {
        return $this->guests;
    }

    public function actualDepartureDate(): ?\DateTimeImmutable
    {
        return $this->actualDepartureDate;
    }

    public function cancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function cancelledBy(): ?string
    {
        return $this->cancelledBy;
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

    public function cancelByBooker(\DateTimeImmutable $today): void
    {
        if (ReservationStatus::Confirmed !== $this->status) {
            throw new InvalidReservationTransitionException($this->status, ReservationStatus::Cancelled);
        }

        if ($today >= $this->period->checkIn) {
            throw CancellationNotAllowedException::afterCheckIn($this->period->checkIn, $today);
        }

        $this->status = ReservationStatus::Cancelled;
        $this->cancelledAt = $today;
        $this->cancelledBy = 'booker';
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

    public function checkOut(\DateTimeImmutable $actualDepartureDate): void
    {
        if (ReservationStatus::CheckedIn !== $this->status) {
            throw CheckOutNotAllowedException::wrongStatus($this->status);
        }

        if ($actualDepartureDate < $this->period->checkIn) {
            throw CheckOutNotAllowedException::beforeCheckInDate($this->period->checkIn, $actualDepartureDate);
        }

        if ($actualDepartureDate > $this->period->checkOut) {
            throw CheckOutNotAllowedException::afterCheckOutDate($this->period->checkOut, $actualDepartureDate);
        }

        $this->actualDepartureDate = $actualDepartureDate;
        $this->status = ReservationStatus::CheckedOut;
    }
}

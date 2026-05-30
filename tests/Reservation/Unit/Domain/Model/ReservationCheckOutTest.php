<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Unit\Domain\Model;

use App\Reservation\Domain\Exception\CheckOutNotAllowedException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationCheckOutTest extends TestCase
{
    #[Test]
    public function it_transitions_to_checked_out_on_valid_departure(): void
    {
        $checkIn = new \DateTimeImmutable('2025-06-10');
        $checkOut = new \DateTimeImmutable('2025-06-15');
        $reservation = $this->makeCheckedInReservation('r1', 'room-1', 'booker-1', $checkIn, $checkOut);
        $reservation->checkOut(new \DateTimeImmutable('2025-06-13'));
        self::assertSame(ReservationStatus::CheckedOut, $reservation->status);
        self::assertEquals(new \DateTimeImmutable('2025-06-13'), $reservation->actualDepartureDate);
    }

    #[Test]
    public function it_accepts_departure_on_check_in_date(): void
    {
        $checkIn = new \DateTimeImmutable('2025-06-10');
        $checkOut = new \DateTimeImmutable('2025-06-15');
        $reservation = $this->makeCheckedInReservation('r1', 'room-1', 'booker-1', $checkIn, $checkOut);
        $reservation->checkOut($checkIn);
        self::assertSame(ReservationStatus::CheckedOut, $reservation->status);
    }

    #[Test]
    public function it_accepts_departure_on_check_out_date(): void
    {
        $checkIn = new \DateTimeImmutable('2025-06-10');
        $checkOut = new \DateTimeImmutable('2025-06-15');
        $reservation = $this->makeCheckedInReservation('r1', 'room-1', 'booker-1', $checkIn, $checkOut);
        $reservation->checkOut($checkOut);
        self::assertSame(ReservationStatus::CheckedOut, $reservation->status);
    }

    #[Test]
    public function it_rejects_checkout_when_status_is_not_checked_in(): void
    {
        $checkIn = new \DateTimeImmutable('2025-06-10');
        $checkOut = new \DateTimeImmutable('2025-06-15');
        $reservation = $this->makeCheckedInReservation('r1', 'room-1', 'booker-1', $checkIn, $checkOut);
        $reservation->checkOut($checkIn); // first checkout
        $this->expectException(CheckOutNotAllowedException::class);
        $reservation->checkOut($checkIn); // second checkout must fail
    }

    #[Test]
    public function it_rejects_departure_after_check_out_date(): void
    {
        $checkIn = new \DateTimeImmutable('2025-06-10');
        $checkOut = new \DateTimeImmutable('2025-06-15');
        $reservation = $this->makeCheckedInReservation('r1', 'room-1', 'booker-1', $checkIn, $checkOut);
        $this->expectException(CheckOutNotAllowedException::class);
        $reservation->checkOut(new \DateTimeImmutable('2025-06-16'));
    }

    #[Test]
    public function it_rejects_departure_before_check_in_date(): void
    {
        $checkIn = new \DateTimeImmutable('2025-06-10');
        $checkOut = new \DateTimeImmutable('2025-06-15');
        $reservation = $this->makeCheckedInReservation('r1', 'room-1', 'booker-1', $checkIn, $checkOut);
        $this->expectException(CheckOutNotAllowedException::class);
        $reservation->checkOut(new \DateTimeImmutable('2025-06-09'));
    }

    private function makeCheckedInReservation(
        string $id,
        string $roomId,
        string $bookerId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): Reservation {
        $reservation = new Reservation(
            id: $id,
            roomId: $roomId,
            bookerId: $bookerId,
            period: new DatePeriod($checkIn, $checkOut),
            totalPrice: 10000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: PriceBreakdown::fromArray([
                ['date' => $checkIn->format('Y-m-d'), 'rateAmountCents' => 5000, 'discountPercent' => null, 'effectiveAmountCents' => 5000],
                ['date' => $checkIn->modify('+1 day')->format('Y-m-d'), 'rateAmountCents' => 5000, 'discountPercent' => null, 'effectiveAmountCents' => 5000],
            ]),
            guestCount: new GuestCount(2),
            createdAt: new \DateTimeImmutable('2025-01-01'),
        );
        $reservation->confirm();
        $reservation->checkIn([], $checkIn);

        return $reservation;
    }
}

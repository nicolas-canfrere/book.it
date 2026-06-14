<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\Model;

use App\Reservation\Domain\Exception\CancellationNotAllowedException;
use App\Reservation\Domain\Exception\InvalidReservationTransitionException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationCancelByBookerTest extends TestCase
{
    private const string ID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private const string ROOM_ID = '550e8400-e29b-41d4-a716-446655440001';
    private const string BOOKER_ID = '550e8400-e29b-41d4-a716-446655440002';

    #[Test]
    public function itCancelsConfirmedReservationBeforeCheckIn(): void
    {
        $reservation = $this->makeConfirmedReservation(checkIn: new \DateTimeImmutable('2026-06-15'));
        $today = new \DateTimeImmutable('2026-06-10');

        $reservation->cancelByBooker($today);

        self::assertSame(ReservationStatus::Cancelled, $reservation->status);
        self::assertSame('2026-06-10', $reservation->cancelledAt?->format('Y-m-d'));
        self::assertSame('booker', $reservation->cancelledBy);
    }

    #[Test]
    public function itThrowsWhenCheckInDateIsToday(): void
    {
        $checkIn = new \DateTimeImmutable('2026-06-15');
        $reservation = $this->makeConfirmedReservation(checkIn: $checkIn);

        $this->expectException(CancellationNotAllowedException::class);

        $reservation->cancelByBooker($checkIn);
    }

    #[Test]
    public function itThrowsWhenCheckInDateIsInThePast(): void
    {
        $checkIn = new \DateTimeImmutable('2026-06-10');
        $reservation = $this->makeConfirmedReservation(checkIn: $checkIn);

        $this->expectException(CancellationNotAllowedException::class);

        $reservation->cancelByBooker(new \DateTimeImmutable('2026-06-15'));
    }

    #[Test]
    public function itThrowsInvalidTransitionWhenReservationIsPending(): void
    {
        $reservation = $this->makePendingReservation(checkIn: new \DateTimeImmutable('2026-06-15'));

        $this->expectException(InvalidReservationTransitionException::class);

        $reservation->cancelByBooker(new \DateTimeImmutable('2026-06-10'));
    }

    #[Test]
    public function itThrowsInvalidTransitionWhenReservationIsCheckedIn(): void
    {
        $reservation = $this->makeConfirmedReservation(checkIn: new \DateTimeImmutable('2026-06-15'));
        $reservation->status = ReservationStatus::CheckedIn;

        $this->expectException(InvalidReservationTransitionException::class);

        $reservation->cancelByBooker(new \DateTimeImmutable('2026-06-10'));
    }

    #[Test]
    public function itIncludesCheckInDateInExceptionMessage(): void
    {
        $checkIn = new \DateTimeImmutable('2026-06-15');
        $reservation = $this->makeConfirmedReservation(checkIn: $checkIn);

        $this->expectException(CancellationNotAllowedException::class);
        $this->expectExceptionMessage('2026-06-15');

        $reservation->cancelByBooker($checkIn);
    }

    private function makePendingReservation(\DateTimeImmutable $checkIn): Reservation
    {
        return new Reservation(
            id: self::ID,
            roomId: new RoomId(self::ROOM_ID),
            bookerId: self::BOOKER_ID,
            period: new DatePeriod($checkIn, $checkIn->modify('+3 days')),
            totalPrice: 30000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: new PriceBreakdown([]),
            guestCount: new GuestCount(1),
            createdAt: new \DateTimeImmutable(),
        );
    }

    private function makeConfirmedReservation(\DateTimeImmutable $checkIn): Reservation
    {
        $reservation = $this->makePendingReservation($checkIn);
        $reservation->status = ReservationStatus::Confirmed;

        return $reservation;
    }
}

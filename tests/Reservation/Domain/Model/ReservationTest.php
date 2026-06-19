<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\Model;

use App\Reservation\Domain\Exception\InvalidReservationTransitionException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Shared\Domain\ValueObject\ReservationId;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationTest extends TestCase
{
    private const string ID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private const string ROOM_ID = '550e8400-e29b-41d4-a716-446655440001';
    private const string BOOKER_ID = '550e8400-e29b-41d4-a716-446655440002';

    #[Test]
    public function itInitializesWithPendingStatus(): void
    {
        $reservation = new Reservation(
            id: new ReservationId(self::ID),
            roomId: new RoomId(self::ROOM_ID),
            bookerId: self::BOOKER_ID,
            period: new DatePeriod(
                new \DateTimeImmutable('2026-06-01'),
                new \DateTimeImmutable('2026-06-05'),
            ),
            totalPrice: 42000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: new PriceBreakdown([]),
            guestCount: new GuestCount(1),
            createdAt: new \DateTimeImmutable('2026-05-18T10:00:00Z'),
        );

        self::assertSame(self::ID, $reservation->id->value);
        self::assertSame(self::ROOM_ID, $reservation->roomId->value);
        self::assertSame(self::BOOKER_ID, $reservation->bookerId);
        self::assertSame('2026-06-01', $reservation->period->checkIn->format('Y-m-d'));
        self::assertSame('2026-06-05', $reservation->period->checkOut->format('Y-m-d'));
        self::assertSame(42000, $reservation->totalPrice);
        self::assertSame(ReservationStatus::Pending, $reservation->status);
        self::assertSame('2026-05-18T10:00:00+00:00', $reservation->createdAt->format(\DateTimeInterface::ATOM));
    }

    #[Test]
    public function itExpiresPendingReservation(): void
    {
        $reservation = $this->makeReservation();

        $reservation->expire();

        self::assertSame(ReservationStatus::Expired, $reservation->status);
    }

    #[Test]
    public function itThrowsWhenExpiringConfirmedReservation(): void
    {
        $reservation = $this->makeReservation();
        $reservation->status = ReservationStatus::Confirmed;

        $this->expectException(InvalidReservationTransitionException::class);

        $reservation->expire();
    }

    #[Test]
    public function itThrowsWhenExpiringCancelledReservation(): void
    {
        $reservation = $this->makeReservation();
        $reservation->status = ReservationStatus::Cancelled;

        $this->expectException(InvalidReservationTransitionException::class);

        $reservation->expire();
    }

    #[Test]
    public function itConfirmsPendingReservation(): void
    {
        $reservation = $this->makeReservation();
        $reservation->confirm();
        self::assertSame(ReservationStatus::Confirmed, $reservation->status);
    }

    #[Test]
    public function itThrowsWhenConfirmingExpiredReservation(): void
    {
        $reservation = $this->makeReservation();
        $reservation->expire();
        $this->expectException(InvalidReservationTransitionException::class);
        $reservation->confirm();
    }

    #[Test]
    public function itCancelsPendingReservation(): void
    {
        $reservation = $this->makeReservation();
        $reservation->cancelPending();
        self::assertSame(ReservationStatus::Cancelled, $reservation->status);
    }

    #[Test]
    public function itThrowsWhenCancellingExpiredReservation(): void
    {
        $reservation = $this->makeReservation();
        $reservation->expire();
        $this->expectException(InvalidReservationTransitionException::class);
        $reservation->cancelPending();
    }

    #[Test]
    public function itAllowsZeroPrice(): void
    {
        $reservation = new Reservation(
            id: new ReservationId(self::ID),
            roomId: new RoomId(self::ROOM_ID),
            bookerId: self::BOOKER_ID,
            period: new DatePeriod(
                new \DateTimeImmutable('2026-06-01'),
                new \DateTimeImmutable('2026-06-05'),
            ),
            totalPrice: 0,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: new PriceBreakdown([]),
            guestCount: new GuestCount(1),
            createdAt: new \DateTimeImmutable(),
        );

        self::assertSame(0, $reservation->totalPrice);
        self::assertSame(ReservationStatus::Pending, $reservation->status);
    }

    private function makeReservation(): Reservation
    {
        return new Reservation(
            id: new ReservationId(self::ID),
            roomId: new RoomId(self::ROOM_ID),
            bookerId: self::BOOKER_ID,
            period: new DatePeriod(
                new \DateTimeImmutable('2030-06-01'),
                new \DateTimeImmutable('2030-06-05'),
            ),
            totalPrice: 40000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: new PriceBreakdown([]),
            guestCount: new GuestCount(1),
            createdAt: new \DateTimeImmutable(),
        );
    }
}

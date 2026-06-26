<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Unit\Domain\Model;

use App\Reservation\Domain\Exception\CheckInNotAllowedException;
use App\Reservation\Domain\Exception\GuestPreRegistrationNotAllowedException;
use App\Reservation\Domain\Model\Guest;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Shared\Domain\ValueObject\BookerId;
use App\Shared\Domain\ValueObject\GuestId;
use App\Shared\Domain\ValueObject\ReservationId;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationGuestTest extends TestCase
{
    // --- preRegisterGuests ---

    #[Test]
    public function itPreRegisterGuestsSetsGuestsWhenConfirmed(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Confirmed);
        $guest = $this->makeGuest();

        $reservation->preRegisterGuests([$guest], new \DateTimeImmutable('2026-06-15'));

        self::assertSame([$guest], $reservation->guests());
    }

    #[Test]
    public function itPreRegisterGuestsSetsGuestsWhenPending(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Pending);
        $guest = $this->makeGuest();

        $reservation->preRegisterGuests([$guest], new \DateTimeImmutable('2026-06-15'));

        self::assertSame([$guest], $reservation->guests());
    }

    #[Test]
    public function itPreRegisterGuestsReplacesExistingGuests(): void
    {
        $reservation = $this->makeReservation();
        $reservation->preRegisterGuests([$this->makeGuest('g-1')], new \DateTimeImmutable('2026-06-10'));

        $newGuest = $this->makeGuest('g-2');
        $reservation->preRegisterGuests([$newGuest], new \DateTimeImmutable('2026-06-15'));

        self::assertCount(1, $reservation->guests());
        self::assertSame($newGuest, $reservation->guests()[0]);
    }

    #[Test]
    public function itPreRegisterGuestsAllowsEmptyList(): void
    {
        $reservation = $this->makeReservation();
        $reservation->preRegisterGuests([$this->makeGuest()], new \DateTimeImmutable('2026-06-10'));
        $reservation->preRegisterGuests([], new \DateTimeImmutable('2026-06-15'));

        self::assertSame([], $reservation->guests());
    }

    #[Test]
    public function itPreRegisterGuestsThrowsWhenCheckedIn(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::CheckedIn);

        $this->expectException(GuestPreRegistrationNotAllowedException::class);
        $reservation->preRegisterGuests([], new \DateTimeImmutable('2026-06-15'));
    }

    #[Test]
    public function itPreRegisterGuestsThrowsWhenCancelled(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Cancelled);

        $this->expectException(GuestPreRegistrationNotAllowedException::class);
        $reservation->preRegisterGuests([], new \DateTimeImmutable('2026-06-15'));
    }

    #[Test]
    public function itPreRegisterGuestsThrowsWhenExpired(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Expired);

        $this->expectException(GuestPreRegistrationNotAllowedException::class);
        $reservation->preRegisterGuests([], new \DateTimeImmutable('2026-06-15'));
    }

    #[Test]
    public function itPreRegisterGuestsThrowsOnCheckInDate(): void
    {
        $reservation = $this->makeReservation();

        $this->expectException(GuestPreRegistrationNotAllowedException::class);
        $reservation->preRegisterGuests([], new \DateTimeImmutable('2026-07-01')); // same as check-in
    }

    #[Test]
    public function itPreRegisterGuestsThrowsAfterCheckInDate(): void
    {
        $reservation = $this->makeReservation();

        $this->expectException(GuestPreRegistrationNotAllowedException::class);
        $reservation->preRegisterGuests([], new \DateTimeImmutable('2026-07-02')); // during stay
    }

    // --- checkIn ---

    #[Test]
    public function itCheckInTransitionsStatusToCheckedIn(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Confirmed);
        $guest = $this->makeGuest();

        $reservation->checkIn([$guest], new \DateTimeImmutable('2026-07-01'));

        self::assertSame(ReservationStatus::CheckedIn, $reservation->status());
    }

    #[Test]
    public function itCheckInSetsGuests(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Confirmed);
        $guest = $this->makeGuest();

        $reservation->checkIn([$guest], new \DateTimeImmutable('2026-07-01'));

        self::assertSame([$guest], $reservation->guests());
    }

    #[Test]
    public function itCheckInReplacesPreRegisteredGuests(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Confirmed);
        $reservation->preRegisterGuests([$this->makeGuest('g-1')], new \DateTimeImmutable('2026-06-15'));
        $finalGuest = $this->makeGuest('g-2');

        $reservation->checkIn([$finalGuest], new \DateTimeImmutable('2026-07-01'));

        self::assertCount(1, $reservation->guests());
        self::assertSame($finalGuest, $reservation->guests()[0]);
    }

    #[Test]
    public function itCheckInAllowedAfterCheckInDate(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Confirmed);

        $reservation->checkIn([], new \DateTimeImmutable('2026-07-02')); // day after check-in

        self::assertSame(ReservationStatus::CheckedIn, $reservation->status());
    }

    #[Test]
    public function itCheckInThrowsWhenNotConfirmed(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Pending);

        $this->expectException(CheckInNotAllowedException::class);
        $reservation->checkIn([], new \DateTimeImmutable('2026-07-01'));
    }

    #[Test]
    public function itCheckInThrowsWhenCancelled(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Cancelled);

        $this->expectException(CheckInNotAllowedException::class);
        $reservation->checkIn([], new \DateTimeImmutable('2026-07-01'));
    }

    #[Test]
    public function itCheckInThrowsWhenExpired(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Expired);

        $this->expectException(CheckInNotAllowedException::class);
        $reservation->checkIn([], new \DateTimeImmutable('2026-07-01'));
    }

    #[Test]
    public function itCheckInThrowsBeforeCheckInDate(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Confirmed);

        $this->expectException(CheckInNotAllowedException::class);
        $reservation->checkIn([], new \DateTimeImmutable('2026-06-30')); // day before
    }

    private function makeReservation(ReservationStatus $status = ReservationStatus::Confirmed): Reservation
    {
        return Reservation::reconstitute(
            id: new ReservationId('res-uuid-1'),
            roomId: new RoomId('room-uuid-1'),
            bookerId: new BookerId('booker-uuid-1'),
            period: new DatePeriod(
                new \DateTimeImmutable('2026-07-01'),
                new \DateTimeImmutable('2026-07-03'),
            ),
            totalPrice: 10000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: PriceBreakdown::fromArray([
                ['date' => '2026-07-01', 'rateAmountCents' => 5000, 'discountPercent' => null, 'effectiveAmountCents' => 5000],
                ['date' => '2026-07-02', 'rateAmountCents' => 5000, 'discountPercent' => null, 'effectiveAmountCents' => 5000],
            ]),
            guestCount: new GuestCount(2),
            createdAt: new \DateTimeImmutable('2026-06-01'),
            status: $status,
        );
    }

    private function makeGuest(string $id = 'g-uuid-1'): Guest
    {
        return new Guest(new GuestId($id), 'Alice', 'Smith', new \DateTimeImmutable('1990-01-15'));
    }
}

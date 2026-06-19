<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Persistence\InMemory;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationPeriodFilter;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Shared\Domain\ValueObject\BookerId;
use App\Shared\Domain\ValueObject\ReservationId;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class InMemoryReservationRepositoryTest extends TestCase
{
    private const string BOOKER_ID = 'b1000000-0000-4000-8000-000000000001';
    private const string ROOM_ID = 'c1000000-0000-4000-8000-000000000001';

    #[Test]
    public function itClassifiesACheckInExactlyTodayAsCurrentEvenWithATimeComponent(): void
    {
        $repository = new InMemoryReservationRepository();
        $repository->add($this->makeReservation(
            'd1000000-0000-4000-8000-000000000001',
            new \DateTimeImmutable('today 14:00'),
            new \DateTimeImmutable('+2 days'),
        ));

        $page = $repository->listByBooker(new BookerId(self::BOOKER_ID), 1, 100, period: ReservationPeriodFilter::Current);

        self::assertCount(1, $page->reservations);

        $upcoming = $repository->listByBooker(new BookerId(self::BOOKER_ID), 1, 100, period: ReservationPeriodFilter::Upcoming);

        self::assertCount(0, $upcoming->reservations);
    }

    #[Test]
    public function itClassifiesACheckOutExactlyTodayAsPastEvenWithATimeComponent(): void
    {
        $repository = new InMemoryReservationRepository();
        $repository->add($this->makeReservation(
            'd1000000-0000-4000-8000-000000000002',
            new \DateTimeImmutable('-2 days'),
            new \DateTimeImmutable('today 09:00'),
        ));

        $page = $repository->listByBooker(new BookerId(self::BOOKER_ID), 1, 100, period: ReservationPeriodFilter::Past);

        self::assertCount(1, $page->reservations);

        $current = $repository->listByBooker(new BookerId(self::BOOKER_ID), 1, 100, period: ReservationPeriodFilter::Current);

        self::assertCount(0, $current->reservations);
    }

    private function makeReservation(string $id, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): Reservation
    {
        return new Reservation(
            id: new ReservationId($id),
            roomId: new RoomId(self::ROOM_ID),
            bookerId: new BookerId(self::BOOKER_ID),
            period: new DatePeriod($checkIn, $checkOut),
            totalPrice: 10000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: PriceBreakdown::fromArray([]),
            guestCount: new GuestCount(1),
            createdAt: new \DateTimeImmutable(),
        );
    }
}

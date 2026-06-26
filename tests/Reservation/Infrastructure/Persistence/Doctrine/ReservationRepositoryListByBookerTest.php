<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Persistence\Doctrine;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationPeriodFilter;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Reservation\Infrastructure\Persistence\Doctrine\ReservationRepository;
use App\Shared\Domain\ValueObject\BookerId;
use App\Shared\Domain\ValueObject\ReservationId;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class ReservationRepositoryListByBookerTest extends KernelTestCase
{
    private const BOOKER_ID = 'b1000000-0000-4000-8000-000000000001';
    private const ROOM_ID = 'c1000000-0000-4000-8000-000000000001';
    private const PAST_ID = 'd1000000-0000-4000-8000-000000000001';
    private const CURRENT_ID = 'd1000000-0000-4000-8000-000000000002';
    private const UPCOMING_ID = 'd1000000-0000-4000-8000-000000000003';
    private const CANCELLED_UPCOMING_ID = 'd1000000-0000-4000-8000-000000000004';

    private ReservationRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(ReservationRepository::class);

        $this->addReservation(self::PAST_ID, '-10 days', '-8 days', ReservationStatus::CheckedOut);
        $this->addReservation(self::CURRENT_ID, '-1 days', '+2 days', ReservationStatus::CheckedIn);
        $this->addReservation(self::UPCOMING_ID, '+5 days', '+8 days', ReservationStatus::Confirmed);
        $this->addReservation(self::CANCELLED_UPCOMING_ID, '+10 days', '+12 days', ReservationStatus::Cancelled);
    }

    #[Test]
    public function itFiltersByPeriodPast(): void
    {
        $page = $this->repository->listByBooker(new BookerId(self::BOOKER_ID), 1, 100, period: ReservationPeriodFilter::Past);

        self::assertSame([self::PAST_ID], $this->ids($page));
    }

    #[Test]
    public function itFiltersByPeriodCurrent(): void
    {
        $page = $this->repository->listByBooker(new BookerId(self::BOOKER_ID), 1, 100, period: ReservationPeriodFilter::Current);

        self::assertSame([self::CURRENT_ID], $this->ids($page));
    }

    #[Test]
    public function itFiltersByPeriodUpcoming(): void
    {
        $page = $this->repository->listByBooker(new BookerId(self::BOOKER_ID), 1, 100, period: ReservationPeriodFilter::Upcoming);

        self::assertSame([self::UPCOMING_ID, self::CANCELLED_UPCOMING_ID], $this->ids($page));
    }

    #[Test]
    public function itFiltersByStatus(): void
    {
        $page = $this->repository->listByBooker(new BookerId(self::BOOKER_ID), 1, 100, status: ReservationStatus::Cancelled);

        self::assertSame([self::CANCELLED_UPCOMING_ID], $this->ids($page));
    }

    #[Test]
    public function itCombinesStatusAndPeriod(): void
    {
        $page = $this->repository->listByBooker(
            new BookerId(self::BOOKER_ID),
            1,
            100,
            status: ReservationStatus::Confirmed,
            period: ReservationPeriodFilter::Upcoming,
        );

        self::assertSame([self::UPCOMING_ID], $this->ids($page));
    }

    /** @return list<string> */
    private function ids(\App\Reservation\Domain\Model\ReservationPage $page): array
    {
        $ids = array_map(static fn(Reservation $r) => $r->id->value, $page->reservations);
        sort($ids);

        return $ids;
    }

    private function addReservation(string $id, string $checkInOffset, string $checkOutOffset, ReservationStatus $status): void
    {
        $reservation = Reservation::reconstitute(
            id: new ReservationId($id),
            roomId: new RoomId(self::ROOM_ID),
            bookerId: new BookerId(self::BOOKER_ID),
            period: new DatePeriod(
                new \DateTimeImmutable($checkInOffset),
                new \DateTimeImmutable($checkOutOffset),
            ),
            totalPrice: 10000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: PriceBreakdown::fromArray([]),
            guestCount: new GuestCount(1),
            createdAt: new \DateTimeImmutable(),
            status: $status,
        );

        $this->repository->add($reservation);
    }
}

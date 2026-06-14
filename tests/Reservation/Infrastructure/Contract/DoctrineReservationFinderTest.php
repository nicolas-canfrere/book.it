<?php

declare(strict_types=1);

namespace Tests\Reservation\Infrastructure\Contract;

use App\Reservation\Application\Contract\ReservationFinderInterface;
use App\Reservation\Application\Contract\ReservationView;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Reservation\Infrastructure\Contract\DoctrineReservationFinder;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineReservationFinderTest extends TestCase
{
    private ReservationRepositoryInterface&Stub $repository;
    private ReservationFinderInterface $finder;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(ReservationRepositoryInterface::class);
        $this->finder = new DoctrineReservationFinder($this->repository);
    }

    #[Test]
    public function itReturnsViewWhenReservationExists(): void
    {
        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        $reservation = new Reservation(
            id: 'res-1',
            roomId: new RoomId('room-1'),
            bookerId: 'booker-1',
            period: new DatePeriod($checkIn, $checkOut),
            totalPrice: 40000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: PriceBreakdown::fromArray([]),
            guestCount: new GuestCount(2),
            createdAt: new \DateTimeImmutable(),
        );
        $this->repository->method('get')->willReturn($reservation);

        $view = $this->finder->find('res-1');

        self::assertInstanceOf(ReservationView::class, $view);
        self::assertSame('res-1', $view->id);
        self::assertEquals($checkIn, $view->checkIn);
        self::assertEquals($checkOut, $view->checkOut);
        self::assertSame(40000, $view->totalPriceCents);
    }

    #[Test]
    public function itReturnsNullWhenReservationNotFound(): void
    {
        $this->repository->method('get')->willReturn(null);
        self::assertNull($this->finder->find('unknown'));
    }
}

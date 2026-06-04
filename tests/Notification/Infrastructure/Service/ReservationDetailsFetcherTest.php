<?php

declare(strict_types=1);

namespace Tests\Notification\Infrastructure\Service;

use App\Notification\Domain\Port\ReservationDetailsFetcherInterface;
use App\Notification\Infrastructure\Service\ReservationDetailsFetcher;
use App\Reservation\Application\Contract\ReservationFinderInterface;
use App\Reservation\Application\Contract\ReservationView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationDetailsFetcherTest extends TestCase
{
    private ReservationFinderInterface&Stub $reservationFinder;
    private ReservationDetailsFetcherInterface $fetcher;

    protected function setUp(): void
    {
        $this->reservationFinder = $this->createStub(ReservationFinderInterface::class);
        $this->fetcher = new ReservationDetailsFetcher($this->reservationFinder);
    }

    #[Test]
    public function itReturnsDetailsWhenReservationFound(): void
    {
        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        $this->reservationFinder->method('find')->willReturn(
            new ReservationView($checkIn, $checkOut, 40000)
        );

        $details = $this->fetcher->fetch('res-1');

        self::assertNotNull($details);
        self::assertEquals($checkIn, $details->checkIn);
        self::assertEquals($checkOut, $details->checkOut);
        self::assertSame(40000, $details->totalPriceCents);
    }

    #[Test]
    public function itReturnsNullWhenReservationNotFound(): void
    {
        $this->reservationFinder->method('find')->willReturn(null);
        self::assertNull($this->fetcher->fetch('unknown'));
    }
}

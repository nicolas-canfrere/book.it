<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure\Service;

use App\Notification\Infrastructure\Service\ReservationDetailsFetcher;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationDetailsFetcherTest extends TestCase
{
    #[Test]
    public function itReturnsDetailsWhenReservationFound(): void
    {
        $reservation = new Reservation(
            id: 'res-001',
            roomId: 'room-001',
            bookerId: 'booker-001',
            period: new DatePeriod(
                new \DateTimeImmutable('2026-07-01'),
                new \DateTimeImmutable('2026-07-05'),
            ),
            totalPrice: 40000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: new PriceBreakdown([]),
            guestCount: new GuestCount(1),
            createdAt: new \DateTimeImmutable(),
        );

        $queryBus = new class($reservation) implements SyncQueryBusInterface {
            public function __construct(private readonly Reservation $reservation)
            {
            }

            public function ask(object $query): mixed
            {
                /** @phpstan-ignore-next-line return.type */
                return $this->reservation;
            }
        };

        $fetcher = new ReservationDetailsFetcher($queryBus);
        $details = $fetcher->fetch('res-001');

        self::assertNotNull($details);
        self::assertSame('2026-07-01', $details->checkIn->format('Y-m-d'));
        self::assertSame('2026-07-05', $details->checkOut->format('Y-m-d'));
        self::assertSame(40000, $details->totalPriceCents);
    }

    #[Test]
    public function itReturnsNullWhenReservationNotFound(): void
    {
        $queryBus = new class implements SyncQueryBusInterface {
            public function ask(object $query): mixed
            {
                /** @phpstan-ignore-next-line return.type */
                return null;
            }
        };

        $fetcher = new ReservationDetailsFetcher($queryBus);

        self::assertNull($fetcher->fetch('unknown'));
    }
}

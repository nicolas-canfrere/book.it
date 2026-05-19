<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Service;

use App\Pricing\Domain\Exception\RoomHasNoBaseRateException;
use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Infrastructure\Service\PricingQuoteFetcher;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PricingQuoteFetcherTest extends TestCase
{
    private const string ROOM_ID = '550e8400-e29b-41d4-a716-446655440001';

    #[Test]
    public function itBuildsSnapshotFromPricingQuote(): void
    {
        $queryBus = $this->createMock(SyncQueryBusInterface::class);
        $queryBus->method('ask')->willReturn([
            'roomId' => self::ROOM_ID,
            'checkIn' => '2026-06-01',
            'checkOut' => '2026-06-03',
            'totalAmountCents' => 19000,
            'nights' => [
                ['date' => '2026-06-01', 'rateAmountCents' => 10000, 'discountPercent' => null, 'effectiveAmountCents' => 10000],
                ['date' => '2026-06-02', 'rateAmountCents' => 10000, 'discountPercent' => 10, 'effectiveAmountCents' => 9000],
            ],
        ]);

        $fetcher = new PricingQuoteFetcher($queryBus);
        $snapshot = $fetcher->fetch(self::ROOM_ID, new \DateTimeImmutable('2026-06-01'), new \DateTimeImmutable('2026-06-03'));

        self::assertSame(19000, $snapshot->totalAmountCents);
        self::assertCount(2, $snapshot->breakdown->nights);
        self::assertSame('2026-06-01', $snapshot->breakdown->nights[0]->date);
        self::assertSame(10000, $snapshot->breakdown->nights[0]->rateAmountCents);
        self::assertNull($snapshot->breakdown->nights[0]->discountPercent);
        self::assertSame(10000, $snapshot->breakdown->nights[0]->effectiveAmountCents);
        self::assertSame('2026-06-02', $snapshot->breakdown->nights[1]->date);
        self::assertSame(10, $snapshot->breakdown->nights[1]->discountPercent);
        self::assertSame(9000, $snapshot->breakdown->nights[1]->effectiveAmountCents);
    }

    #[Test]
    public function itThrowsRoomNotBookableWhenNoBaseRate(): void
    {
        $queryBus = $this->createMock(SyncQueryBusInterface::class);
        $queryBus->method('ask')->willThrowException(
            new RoomHasNoBaseRateException(self::ROOM_ID),
        );

        $fetcher = new PricingQuoteFetcher($queryBus);

        $this->expectException(RoomNotBookableException::class);

        $fetcher->fetch(self::ROOM_ID, new \DateTimeImmutable('2026-06-01'), new \DateTimeImmutable('2026-06-03'));
    }
}

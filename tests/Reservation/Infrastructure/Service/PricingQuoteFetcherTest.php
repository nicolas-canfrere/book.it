<?php

declare(strict_types=1);

namespace Tests\Reservation\Infrastructure\Service;

use App\Pricing\Application\Contract\PricingQuoteFinderInterface;
use App\Pricing\Application\Contract\PricingQuoteView;
use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Port\PricingQuoteFetcherInterface;
use App\Reservation\Infrastructure\Service\PricingQuoteFetcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PricingQuoteFetcherTest extends TestCase
{
    private PricingQuoteFinderInterface&Stub $pricingFinder;
    private PricingQuoteFetcherInterface $fetcher;

    protected function setUp(): void
    {
        $this->pricingFinder = $this->createStub(PricingQuoteFinderInterface::class);
        $this->fetcher = new PricingQuoteFetcher($this->pricingFinder);
    }

    #[Test]
    public function itReturnsSnapshotFromView(): void
    {
        $nights = [
            ['date' => '2026-07-01', 'rateAmountCents' => 10000, 'discountPercent' => null, 'effectiveAmountCents' => 10000],
        ];
        $this->pricingFinder->method('fetch')->willReturn(new PricingQuoteView(10000, $nights));

        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-02');

        $snapshot = $this->fetcher->fetch('room-1', $checkIn, $checkOut);

        self::assertSame(10000, $snapshot->totalAmountCents);
    }

    #[Test]
    public function itThrowsRoomNotBookableOnDomainException(): void
    {
        $this->pricingFinder->method('fetch')->willThrowException(new \DomainException('no base rate'));

        $this->expectException(RoomNotBookableException::class);

        $this->fetcher->fetch('room-1', new \DateTimeImmutable('2026-07-01'), new \DateTimeImmutable('2026-07-02'));
    }
}

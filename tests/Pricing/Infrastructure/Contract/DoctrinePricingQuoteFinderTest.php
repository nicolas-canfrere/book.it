<?php

declare(strict_types=1);

namespace Tests\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\PricingQuoteFinderInterface;
use App\Pricing\Domain\Service\PricingQuoteCalculatorInterface;
use App\Pricing\Domain\ValueObject\NightPricingDetail;
use App\Pricing\Domain\ValueObject\PricingQuote;
use App\Pricing\Infrastructure\Contract\DoctrinePricingQuoteFinder;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrinePricingQuoteFinderTest extends TestCase
{
    private PricingQuoteCalculatorInterface&Stub $calculator;
    private PricingQuoteFinderInterface $finder;

    protected function setUp(): void
    {
        $this->calculator = $this->createStub(PricingQuoteCalculatorInterface::class);
        $this->finder = new DoctrinePricingQuoteFinder($this->calculator);
    }

    #[Test]
    public function itReturnsViewFromCalculatorResult(): void
    {
        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-03');

        $nights = [
            new NightPricingDetail(new \DateTimeImmutable('2026-07-01'), 10000, null, 10000),
            new NightPricingDetail(new \DateTimeImmutable('2026-07-02'), 10000, null, 10000),
        ];

        $roomId = new RoomId('550e8400-e29b-41d4-a716-446655440000');

        $this->calculator->method('calculate')->willReturn(new PricingQuote(
            roomId: $roomId,
            checkIn: $checkIn,
            checkOut: $checkOut,
            totalAmountCents: 20000,
            nights: $nights,
        ));

        $view = $this->finder->fetch($roomId, $checkIn, $checkOut);

        self::assertSame(20000, $view->totalAmountCents);
        self::assertSame('2026-07-01', $view->nights[0]['date']);
        self::assertSame(10000, $view->nights[0]['rateAmountCents']);
        self::assertNull($view->nights[0]['discountPercent']);
        self::assertSame(10000, $view->nights[0]['effectiveAmountCents']);
    }

    #[Test]
    public function itPropagatesDomainException(): void
    {
        $this->calculator->method('calculate')->willThrowException(new \DomainException('no base rate'));

        $this->expectException(\DomainException::class);

        $this->finder->fetch(new RoomId('550e8400-e29b-41d4-a716-446655440000'), new \DateTimeImmutable('2026-07-01'), new \DateTimeImmutable('2026-07-03'));
    }
}

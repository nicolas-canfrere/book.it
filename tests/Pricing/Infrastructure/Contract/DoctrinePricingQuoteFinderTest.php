<?php

declare(strict_types=1);

namespace Tests\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\PricingQuoteCalculatorInterface;
use App\Pricing\Application\Contract\PricingQuoteFinderInterface;
use App\Pricing\Infrastructure\Contract\DoctrinePricingQuoteFinder;
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
            ['date' => '2026-07-01', 'rateAmountCents' => 10000, 'discountPercent' => null, 'effectiveAmountCents' => 10000],
            ['date' => '2026-07-02', 'rateAmountCents' => 10000, 'discountPercent' => null, 'effectiveAmountCents' => 10000],
        ];

        $this->calculator->method('calculate')->willReturn([
            'roomId' => 'room-1',
            'checkIn' => '2026-07-01',
            'checkOut' => '2026-07-03',
            'totalAmountCents' => 20000,
            'nights' => $nights,
        ]);

        $view = $this->finder->fetch('room-1', $checkIn, $checkOut);

        self::assertSame(20000, $view->totalAmountCents);
        self::assertSame($nights, $view->nights);
    }

    #[Test]
    public function itPropagatesDomainException(): void
    {
        $this->calculator->method('calculate')->willThrowException(new \DomainException('no base rate'));

        $this->expectException(\DomainException::class);

        $this->finder->fetch('room-1', new \DateTimeImmutable('2026-07-01'), new \DateTimeImmutable('2026-07-03'));
    }
}

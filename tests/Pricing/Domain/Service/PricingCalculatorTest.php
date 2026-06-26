<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Domain\Service;

use App\Pricing\Domain\Exception\RoomHasNoBaseRateException;
use App\Pricing\Domain\Model\BaseRate;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Model\RatePeriod;
use App\Pricing\Domain\Service\PricingCalculator;
use App\Shared\Domain\ValueObject\RoomId;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryBaseRateRepository;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryPromotionRepository;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryRatePeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PricingCalculatorTest extends TestCase
{
    private const string ROOM_ID = '550e8400-e29b-41d4-a716-446655440000';

    private InMemoryBaseRateRepository $baseRates;
    private InMemoryRatePeriodRepository $ratePeriods;
    private InMemoryPromotionRepository $promotions;
    private PricingCalculator $calculator;

    protected function setUp(): void
    {
        $this->baseRates = new InMemoryBaseRateRepository();
        $this->ratePeriods = new InMemoryRatePeriodRepository();
        $this->promotions = new InMemoryPromotionRepository();
        $this->calculator = new PricingCalculator($this->baseRates, $this->ratePeriods, $this->promotions);
    }

    #[Test]
    public function itComputesTotalUsingBaseRateForEachNight(): void
    {
        $this->baseRates->save(new BaseRate(new RoomId(self::ROOM_ID), 10000, new \DateTimeImmutable('2025-01-01')));

        $result = $this->calculator->calculate(
            new RoomId(self::ROOM_ID),
            new \DateTimeImmutable('2025-07-10'),
            new \DateTimeImmutable('2025-07-13'),
        );

        self::assertSame(30000, $result->totalAmountCents);
        self::assertCount(3, $result->nights);
        self::assertSame('2025-07-10', $result->nights[0]->date->format('Y-m-d'));
        self::assertSame(10000, $result->nights[0]->rateAmountCents);
        self::assertNull($result->nights[0]->discountPercent);
        self::assertSame(10000, $result->nights[0]->effectiveAmountCents);
    }

    #[Test]
    public function itOverridesBaseRateForNightsCoveredByRatePeriod(): void
    {
        $this->baseRates->save(new BaseRate(new RoomId(self::ROOM_ID), 10000, new \DateTimeImmutable('2025-01-01')));
        $this->ratePeriods->save(new RatePeriod(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            roomId: new RoomId(self::ROOM_ID),
            checkIn: new \DateTimeImmutable('2025-07-11'),
            checkOut: new \DateTimeImmutable('2025-07-13'),
            amountCents: 20000,
            createdAt: new \DateTimeImmutable('2025-01-01'),
            updatedAt: new \DateTimeImmutable('2025-01-01'),
        ));

        $result = $this->calculator->calculate(
            new RoomId(self::ROOM_ID),
            new \DateTimeImmutable('2025-07-10'),
            new \DateTimeImmutable('2025-07-13'),
        );

        self::assertSame(50000, $result->totalAmountCents);
        self::assertSame(10000, $result->nights[0]->rateAmountCents);
        self::assertSame(20000, $result->nights[1]->rateAmountCents);
        self::assertSame(20000, $result->nights[2]->rateAmountCents);
    }

    #[Test]
    public function itAppliesPromotionDiscountOnCoveredNights(): void
    {
        $this->baseRates->save(new BaseRate(new RoomId(self::ROOM_ID), 10000, new \DateTimeImmutable('2025-01-01')));
        $this->promotions->save(new Promotion(
            id: 'c2ffcd00-ad1c-4ef9-cc7e-7cc0ce491b33',
            roomId: new RoomId(self::ROOM_ID),
            checkIn: new \DateTimeImmutable('2025-07-11'),
            checkOut: new \DateTimeImmutable('2025-07-13'),
            discountPercent: 20,
            createdAt: new \DateTimeImmutable('2025-01-01'),
            updatedAt: new \DateTimeImmutable('2025-01-01'),
        ));

        $result = $this->calculator->calculate(
            new RoomId(self::ROOM_ID),
            new \DateTimeImmutable('2025-07-10'),
            new \DateTimeImmutable('2025-07-13'),
        );

        // 10000 + 8000 + 8000 = 26000
        self::assertSame(26000, $result->totalAmountCents);
        self::assertNull($result->nights[0]->discountPercent);
        self::assertSame(10000, $result->nights[0]->effectiveAmountCents);
        self::assertSame(20, $result->nights[1]->discountPercent);
        self::assertSame(8000, $result->nights[1]->effectiveAmountCents);
    }

    #[Test]
    public function itCombinesRatePeriodAndPromotionOnSameNight(): void
    {
        $this->baseRates->save(new BaseRate(new RoomId(self::ROOM_ID), 10000, new \DateTimeImmutable('2025-01-01')));
        $this->ratePeriods->save(new RatePeriod(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            roomId: new RoomId(self::ROOM_ID),
            checkIn: new \DateTimeImmutable('2025-07-10'),
            checkOut: new \DateTimeImmutable('2025-07-13'),
            amountCents: 20000,
            createdAt: new \DateTimeImmutable('2025-01-01'),
            updatedAt: new \DateTimeImmutable('2025-01-01'),
        ));
        $this->promotions->save(new Promotion(
            id: 'c2ffcd00-ad1c-4ef9-cc7e-7cc0ce491b33',
            roomId: new RoomId(self::ROOM_ID),
            checkIn: new \DateTimeImmutable('2025-07-11'),
            checkOut: new \DateTimeImmutable('2025-07-12'),
            discountPercent: 25,
            createdAt: new \DateTimeImmutable('2025-01-01'),
            updatedAt: new \DateTimeImmutable('2025-01-01'),
        ));

        $result = $this->calculator->calculate(
            new RoomId(self::ROOM_ID),
            new \DateTimeImmutable('2025-07-10'),
            new \DateTimeImmutable('2025-07-13'),
        );

        // 20000 + (20000 * 0.75 = 15000) + 20000 = 55000
        self::assertSame(55000, $result->totalAmountCents);
        self::assertSame(20000, $result->nights[0]->effectiveAmountCents);
        self::assertSame(25, $result->nights[1]->discountPercent);
        self::assertSame(15000, $result->nights[1]->effectiveAmountCents);
        self::assertSame(20000, $result->nights[2]->effectiveAmountCents);
    }

    #[Test]
    public function itThrowsWhenRoomHasNoBaseRate(): void
    {
        $this->expectException(RoomHasNoBaseRateException::class);

        $this->calculator->calculate(
            new RoomId(self::ROOM_ID),
            new \DateTimeImmutable('2025-07-10'),
            new \DateTimeImmutable('2025-07-13'),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\ValueObject;

use App\Reservation\Domain\ValueObject\NightPrice;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PriceBreakdownTest extends TestCase
{
    #[Test]
    public function itRoundTripsToAndFromArray(): void
    {
        $original = new PriceBreakdown([
            new NightPrice('2026-06-01', 10000, null, 10000),
            new NightPrice('2026-06-02', 10000, 10, 9000),
        ]);

        $restored = PriceBreakdown::fromArray($original->toArray());

        self::assertCount(2, $restored->nights);
        self::assertSame('2026-06-01', $restored->nights[0]->date);
        self::assertSame(10000, $restored->nights[0]->rateAmountCents);
        self::assertNull($restored->nights[0]->discountPercent);
        self::assertSame(10000, $restored->nights[0]->effectiveAmountCents);
        self::assertSame('2026-06-02', $restored->nights[1]->date);
        self::assertSame(10, $restored->nights[1]->discountPercent);
        self::assertSame(9000, $restored->nights[1]->effectiveAmountCents);
    }

    #[Test]
    public function toArrayReturnsExpectedShape(): void
    {
        $breakdown = new PriceBreakdown([
            new NightPrice('2026-06-01', 10000, null, 10000),
        ]);

        $array = $breakdown->toArray();

        self::assertSame([
            [
                'date' => '2026-06-01',
                'rateAmountCents' => 10000,
                'discountPercent' => null,
                'effectiveAmountCents' => 10000,
            ],
        ], $array);
    }

    #[Test]
    public function itHandlesEmptyBreakdown(): void
    {
        $breakdown = PriceBreakdown::fromArray([]);

        self::assertSame([], $breakdown->nights);
        self::assertSame([], $breakdown->toArray());
    }
}

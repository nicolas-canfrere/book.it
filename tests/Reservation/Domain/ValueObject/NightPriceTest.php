<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\ValueObject;

use App\Reservation\Domain\ValueObject\NightPrice;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class NightPriceTest extends TestCase
{
    #[Test]
    public function itStoresAllFields(): void
    {
        $night = new NightPrice('2026-06-01', 10000, 10, 9000);

        self::assertSame('2026-06-01', $night->date);
        self::assertSame(10000, $night->rateAmountCents);
        self::assertSame(10, $night->discountPercent);
        self::assertSame(9000, $night->effectiveAmountCents);
    }

    #[Test]
    public function itAcceptsNullDiscount(): void
    {
        $night = new NightPrice('2026-06-01', 10000, null, 10000);

        self::assertNull($night->discountPercent);
    }
}

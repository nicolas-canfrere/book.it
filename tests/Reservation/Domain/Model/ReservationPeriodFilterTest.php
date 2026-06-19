<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\Model;

use App\Reservation\Domain\Model\ReservationPeriodFilter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationPeriodFilterTest extends TestCase
{
    #[Test]
    public function itListsAllValues(): void
    {
        self::assertSame(['past', 'current', 'upcoming'], ReservationPeriodFilter::values());
    }

    #[Test]
    public function itResolvesFromValue(): void
    {
        foreach (ReservationPeriodFilter::cases() as $case) {
            self::assertSame($case, ReservationPeriodFilter::from($case->value));
        }
    }
}

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
        self::assertSame(ReservationPeriodFilter::Past, ReservationPeriodFilter::from('past'));
        self::assertSame(ReservationPeriodFilter::Current, ReservationPeriodFilter::from('current'));
        self::assertSame(ReservationPeriodFilter::Upcoming, ReservationPeriodFilter::from('upcoming'));
    }
}

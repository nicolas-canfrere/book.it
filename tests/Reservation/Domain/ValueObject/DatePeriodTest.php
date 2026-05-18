<?php
declare(strict_types=1);

namespace App\Tests\Reservation\Domain\ValueObject;

use App\Reservation\Domain\ValueObject\DatePeriod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DatePeriodTest extends TestCase
{
    #[Test]
    public function itCreatesAValidPeriod(): void
    {
        $period = new DatePeriod(
            new \DateTimeImmutable('2026-06-01'),
            new \DateTimeImmutable('2026-06-05'),
        );

        self::assertSame('2026-06-01', $period->checkIn->format('Y-m-d'));
        self::assertSame('2026-06-05', $period->checkOut->format('Y-m-d'));
    }

    #[Test]
    public function itRejectsCheckOutBeforeCheckIn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DatePeriod(
            new \DateTimeImmutable('2026-06-05'),
            new \DateTimeImmutable('2026-06-01'),
        );
    }

    #[Test]
    public function itRejectsCheckOutEqualToCheckIn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DatePeriod(
            new \DateTimeImmutable('2026-06-01'),
            new \DateTimeImmutable('2026-06-01'),
        );
    }
}

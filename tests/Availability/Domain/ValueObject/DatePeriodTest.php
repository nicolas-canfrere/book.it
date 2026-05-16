<?php

declare(strict_types=1);

namespace App\Tests\Availability\Domain\ValueObject;

use App\Availability\Domain\ValueObject\DatePeriod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DatePeriodTest extends TestCase
{
    #[Test]
    public function itCreatesAValidPeriod(): void
    {
        $checkIn = new \DateTimeImmutable('2025-06-10');
        $checkOut = new \DateTimeImmutable('2025-06-13');

        $period = new DatePeriod($checkIn, $checkOut);

        self::assertSame('2025-06-10', $period->checkIn->format('Y-m-d'));
        self::assertSame('2025-06-13', $period->checkOut->format('Y-m-d'));
    }

    #[Test]
    public function itRejectsWhenCheckInEqualsCheckOut(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DatePeriod(
            new \DateTimeImmutable('2025-06-10'),
            new \DateTimeImmutable('2025-06-10'),
        );
    }

    #[Test]
    public function itRejectsWhenCheckInIsAfterCheckOut(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DatePeriod(
            new \DateTimeImmutable('2025-06-13'),
            new \DateTimeImmutable('2025-06-10'),
        );
    }
}

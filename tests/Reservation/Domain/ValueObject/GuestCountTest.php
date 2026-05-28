<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\ValueObject;

use App\Reservation\Domain\ValueObject\GuestCount;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GuestCountTest extends TestCase
{
    #[Test]
    public function itCreatesAValidGuestCount(): void
    {
        $count = new GuestCount(3);

        self::assertSame(3, $count->value);
    }

    #[Test]
    public function itAcceptsMinimumValue(): void
    {
        $count = new GuestCount(1);

        self::assertSame(1, $count->value);
    }

    #[Test]
    public function itAcceptsMaximumValue(): void
    {
        $count = new GuestCount(20);

        self::assertSame(20, $count->value);
    }

    #[Test]
    public function itRejectsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new GuestCount(0);
    }

    #[Test]
    public function itRejectsAboveMaximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new GuestCount(21);
    }
}

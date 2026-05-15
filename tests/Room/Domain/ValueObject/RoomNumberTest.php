<?php

declare(strict_types=1);

namespace App\Tests\Room\Domain\ValueObject;

use App\Room\Domain\ValueObject\RoomNumber;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RoomNumberTest extends TestCase
{
    #[Test]
    public function itAcceptsNumericString(): void
    {
        $vo = new RoomNumber('101');
        self::assertSame('101', $vo->value);
    }

    #[Test]
    public function itAcceptsAlphanumericString(): void
    {
        $vo = new RoomNumber('2A');
        self::assertSame('2A', $vo->value);
    }

    #[Test]
    public function itAcceptsStringWithSpecialChars(): void
    {
        $vo = new RoomNumber('Suite #3');
        self::assertSame('Suite #3', $vo->value);
    }

    #[Test]
    public function itAcceptsMaxLength(): void
    {
        $vo = new RoomNumber(str_repeat('X', 50));
        self::assertSame(str_repeat('X', 50), $vo->value);
    }

    #[Test]
    public function itThrowsOnBlankString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RoomNumber('');
    }

    #[Test]
    public function itThrowsOnWhitespaceOnlyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RoomNumber('   ');
    }

    #[Test]
    public function itThrowsWhenExceeding50Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RoomNumber(str_repeat('X', 51));
    }
}

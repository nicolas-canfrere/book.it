<?php

declare(strict_types=1);

namespace App\Tests\Room\Domain\ValueObject;

use App\Room\Domain\ValueObject\RoomFloor;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RoomFloorTest extends TestCase
{
    #[Test]
    public function itAcceptsGroundFloor(): void
    {
        $vo = new RoomFloor(0);
        self::assertSame(0, $vo->value);
    }

    #[Test]
    public function itAcceptsPositiveFloor(): void
    {
        $vo = new RoomFloor(5);
        self::assertSame(5, $vo->value);
    }

    #[Test]
    public function itAcceptsNegativeFloor(): void
    {
        $vo = new RoomFloor(-1);
        self::assertSame(-1, $vo->value);
    }

    #[Test]
    public function itAcceptsLowerBound(): void
    {
        $vo = new RoomFloor(-20);
        self::assertSame(-20, $vo->value);
    }

    #[Test]
    public function itAcceptsUpperBound(): void
    {
        $vo = new RoomFloor(300);
        self::assertSame(300, $vo->value);
    }

    #[Test]
    public function itThrowsBelowLowerBound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RoomFloor(-21);
    }

    #[Test]
    public function itThrowsAboveUpperBound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RoomFloor(301);
    }
}

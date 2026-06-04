<?php

declare(strict_types=1);

namespace Tests\Pricing\Infrastructure\Service;

use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Pricing\Infrastructure\Service\RoomExistenceChecker;
use App\Room\Application\Contract\RoomFinderInterface;
use App\Room\Application\Contract\RoomView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RoomExistenceCheckerTest extends TestCase
{
    private RoomFinderInterface&Stub $roomFinder;
    private RoomExistsInterface $checker;

    protected function setUp(): void
    {
        $this->roomFinder = $this->createStub(RoomFinderInterface::class);
        $this->checker = new RoomExistenceChecker($this->roomFinder);
    }

    #[Test]
    public function itReturnsTrueWhenRoomFound(): void
    {
        $this->roomFinder->method('find')->willReturn(new RoomView('room-1', 2));
        self::assertTrue($this->checker->exists('room-1'));
    }

    #[Test]
    public function itReturnsFalseWhenRoomNotFound(): void
    {
        $this->roomFinder->method('find')->willReturn(null);
        self::assertFalse($this->checker->exists('unknown'));
    }
}

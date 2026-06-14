<?php

declare(strict_types=1);

namespace Tests\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\RoomCapacityFetcherInterface;
use App\Reservation\Infrastructure\Service\RoomCapacityFetcher;
use App\Room\Application\Contract\RoomFinderInterface;
use App\Room\Application\Contract\RoomView;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RoomCapacityFetcherTest extends TestCase
{
    private RoomFinderInterface&Stub $roomFinder;
    private RoomCapacityFetcherInterface $fetcher;

    protected function setUp(): void
    {
        $this->roomFinder = $this->createStub(RoomFinderInterface::class);
        $this->fetcher = new RoomCapacityFetcher($this->roomFinder);
    }

    #[Test]
    public function itFetchesCapacityFromRoomView(): void
    {
        $this->roomFinder->method('find')->willReturn(new RoomView('room-1', 4));
        self::assertSame(4, $this->fetcher->fetchCapacity(new RoomId('room-1')));
    }

    #[Test]
    public function itReturnsZeroCapacityWhenRoomNotFound(): void
    {
        $this->roomFinder->method('find')->willReturn(null);
        self::assertSame(0, $this->fetcher->fetchCapacity(new RoomId('unknown')));
    }
}

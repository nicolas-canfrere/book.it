<?php

declare(strict_types=1);

namespace Tests\Room\Infrastructure\Contract;

use App\Room\Application\Contract\RoomFinderInterface;
use App\Room\Application\Contract\RoomView;
use App\Room\Domain\Model\Room;
use App\Room\Domain\Port\RoomCapacityFinderInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use App\Room\Infrastructure\Contract\DoctrineRoomFinder;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineRoomFinderTest extends TestCase
{
    private RoomRepositoryInterface&Stub $roomRepository;
    private RoomCapacityFinderInterface&Stub $capacityFinder;
    private RoomFinderInterface $finder;

    protected function setUp(): void
    {
        $this->roomRepository = $this->createStub(RoomRepositoryInterface::class);
        $this->capacityFinder = $this->createStub(RoomCapacityFinderInterface::class);
        $this->finder = new DoctrineRoomFinder($this->roomRepository, $this->capacityFinder);
    }

    #[Test]
    public function itReturnsViewWithCapacityWhenRoomExists(): void
    {
        $room = new Room(
            id: new RoomId('room-1'),
            hotelId: 'hotel-1',
            number: new RoomNumber('101'),
            floor: new RoomFloor(1),
            roomTypeId: new RoomTypeId('type-1'),
            createdAt: new \DateTimeImmutable(),
        );
        $this->roomRepository->method('get')->willReturn($room);
        $this->capacityFinder->method('findCapacity')->willReturn(3);

        $view = $this->finder->find('room-1');

        self::assertInstanceOf(RoomView::class, $view);
        self::assertSame('room-1', $view->id);
        self::assertSame(3, $view->capacity);
    }

    #[Test]
    public function itReturnsNullWhenRoomNotFound(): void
    {
        $this->roomRepository->method('get')->willReturn(null);

        self::assertNull($this->finder->find('unknown'));
    }
}

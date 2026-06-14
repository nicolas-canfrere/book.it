<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\DeleteRoomType;

use App\Room\Application\UseCase\DeleteRoomType\DeleteRoomTypeCommand;
use App\Room\Application\UseCase\DeleteRoomType\DeleteRoomTypeCommandHandler;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommandHandler;
use App\Room\Domain\Exception\RoomTypeHasRoomsException;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Shared\Domain\Event\RoomTypeDeleted;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\FakeRoomTypeHasRooms;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeleteRoomTypeCommandHandlerTest extends TestCase
{
    private const string ROOM_TYPE_ID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private const string HOTEL_ID = '550e8400-e29b-41d4-a716-446655440000';
    private InMemoryRoomTypeRepository $repository;
    private FakeRoomTypeHasRooms $hasRooms;
    private FakeEventDispatcher $eventDispatcher;
    private DeleteRoomTypeCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRoomTypeRepository();
        $this->hasRooms = new FakeRoomTypeHasRooms();
        $this->eventDispatcher = new FakeEventDispatcher();
        $this->handler = new DeleteRoomTypeCommandHandler($this->repository, $this->hasRooms, $this->eventDispatcher);

        $registerHandler = new RegisterRoomTypeCommandHandler($this->repository, new FakeHotelExistenceChecker(), new FakeEventDispatcher());
        ($registerHandler)(new RegisterRoomTypeCommand(
            id: new RoomTypeId(self::ROOM_TYPE_ID),
            hotelId: new HotelId(self::HOTEL_ID),
            name: 'Single',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 1,
            isAccessible: false,
            bedEntries: [['type' => 'single', 'count' => 1]],
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itDeletesTheRoomType(): void
    {
        ($this->handler)(new DeleteRoomTypeCommand(new RoomTypeId(self::ROOM_TYPE_ID)));

        self::assertNull($this->repository->get(new RoomTypeId(self::ROOM_TYPE_ID)));
    }

    #[Test]
    public function itDispatchesRoomTypeDeleted(): void
    {
        ($this->handler)(new DeleteRoomTypeCommand(new RoomTypeId(self::ROOM_TYPE_ID)));

        $event = $this->eventDispatcher->getLastDispatched();
        self::assertInstanceOf(RoomTypeDeleted::class, $event);
        self::assertSame(self::ROOM_TYPE_ID, $event->roomTypeId);
        self::assertSame(self::HOTEL_ID, $event->hotelId);
    }

    #[Test]
    public function itThrowsWhenRoomTypeNotFound(): void
    {
        $this->expectException(RoomTypeNotFoundException::class);

        ($this->handler)(new DeleteRoomTypeCommand(new RoomTypeId('00000000-0000-4000-8000-000000000000')));
    }

    #[Test]
    public function itDoesNotDispatchWhenRoomTypeNotFound(): void
    {
        try {
            ($this->handler)(new DeleteRoomTypeCommand(new RoomTypeId('00000000-0000-4000-8000-000000000000')));
        } catch (RoomTypeNotFoundException) {
            // Expected
        }

        self::assertEmpty($this->eventDispatcher->getDispatched());
    }

    #[Test]
    public function itThrowsWhenRoomsAreAssigned(): void
    {
        $this->hasRooms->setHasRooms(true);
        $this->expectException(RoomTypeHasRoomsException::class);

        ($this->handler)(new DeleteRoomTypeCommand(new RoomTypeId(self::ROOM_TYPE_ID)));
    }

    #[Test]
    public function itDoesNotDispatchWhenRoomsAreAssigned(): void
    {
        $this->hasRooms->setHasRooms(true);

        try {
            ($this->handler)(new DeleteRoomTypeCommand(new RoomTypeId(self::ROOM_TYPE_ID)));
        } catch (RoomTypeHasRoomsException) {
            // Expected
        }

        self::assertEmpty($this->eventDispatcher->getDispatched());
    }
}

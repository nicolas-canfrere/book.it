<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\DeleteRoomType;

use App\Room\Application\UseCase\DeleteRoomType\DeleteRoomTypeCommand;
use App\Room\Application\UseCase\DeleteRoomType\DeleteRoomTypeCommandHandler;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommandHandler;
use App\Room\Domain\Exception\RoomTypeHasRoomsException;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\FakeRoomTypeHasRooms;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeleteRoomTypeCommandHandlerTest extends TestCase
{
    private InMemoryRoomTypeRepository $repository;
    private FakeRoomTypeHasRooms $hasRooms;
    private DeleteRoomTypeCommandHandler $handler;

    private const string ROOM_TYPE_ID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    protected function setUp(): void
    {
        $this->repository = new InMemoryRoomTypeRepository();
        $this->hasRooms = new FakeRoomTypeHasRooms();
        $this->handler = new DeleteRoomTypeCommandHandler($this->repository, $this->hasRooms);

        $registerHandler = new RegisterRoomTypeCommandHandler($this->repository, new FakeHotelExistenceChecker());
        ($registerHandler)(new RegisterRoomTypeCommand(
            id: self::ROOM_TYPE_ID,
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
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
        ($this->handler)(new DeleteRoomTypeCommand(self::ROOM_TYPE_ID));

        self::assertNull($this->repository->get(self::ROOM_TYPE_ID));
    }

    #[Test]
    public function itThrowsWhenRoomTypeNotFound(): void
    {
        $this->expectException(RoomTypeNotFoundException::class);

        ($this->handler)(new DeleteRoomTypeCommand('00000000-0000-4000-8000-000000000000'));
    }

    #[Test]
    public function itThrowsWhenRoomsAreAssigned(): void
    {
        $this->hasRooms->setHasRooms(true);
        $this->expectException(RoomTypeHasRoomsException::class);

        ($this->handler)(new DeleteRoomTypeCommand(self::ROOM_TYPE_ID));
    }
}

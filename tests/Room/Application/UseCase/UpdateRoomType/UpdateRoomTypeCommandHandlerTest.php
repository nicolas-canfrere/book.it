<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\UpdateRoomType;

use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommandHandler;
use App\Room\Application\UseCase\UpdateRoomType\UpdateRoomTypeCommand;
use App\Room\Application\UseCase\UpdateRoomType\UpdateRoomTypeCommandHandler;
use App\Room\Domain\Exception\RoomTypeAlreadyExistsException;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Shared\Domain\Event\RoomTypeUpdated;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UpdateRoomTypeCommandHandlerTest extends TestCase
{
    private const string HOTEL_ID = '550e8400-e29b-41d4-a716-446655440000';
    private const string ROOM_TYPE_ID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private InMemoryRoomTypeRepository $repository;
    private FakeEventDispatcher $dispatcher;
    private UpdateRoomTypeCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRoomTypeRepository();
        $this->dispatcher = new FakeEventDispatcher();
        $this->handler = new UpdateRoomTypeCommandHandler($this->repository, $this->dispatcher);

        $registerHandler = new RegisterRoomTypeCommandHandler($this->repository, new FakeHotelExistenceChecker(), new FakeEventDispatcher());
        ($registerHandler)(new RegisterRoomTypeCommand(
            id: self::ROOM_TYPE_ID,
            hotelId: self::HOTEL_ID,
            name: 'Single',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 1,
            isAccessible: false,
            bedEntries: [['type' => 'single', 'count' => 1]],
            createdAt: new \DateTimeImmutable('2024-01-01'),
        ));
    }

    #[Test]
    public function itUpdatesTheRoomType(): void
    {
        ($this->handler)(new UpdateRoomTypeCommand(
            id: self::ROOM_TYPE_ID,
            name: 'Double',
            livingSpaceCount: 1,
            surfaceM2: 25,
            guestCapacity: 2,
            isAccessible: true,
            bedEntries: [['type' => 'double', 'count' => 1]],
        ));

        $updated = $this->repository->get(self::ROOM_TYPE_ID);
        self::assertNotNull($updated);
        self::assertSame('Double', $updated->name);
        self::assertSame(25, $updated->surfaceM2);
        self::assertSame(2, $updated->guestCapacity);
        self::assertTrue($updated->isAccessible);
        self::assertSame([['type' => 'double', 'count' => 1]], $updated->bedComposition->toArray());
        self::assertSame(self::HOTEL_ID, $updated->hotelId);

        $event = $this->dispatcher->getLastDispatched();
        self::assertInstanceOf(RoomTypeUpdated::class, $event);
        self::assertSame(self::ROOM_TYPE_ID, $event->roomTypeId);
        self::assertSame(self::HOTEL_ID, $event->hotelId);
        self::assertSame('Double', $event->name);
        self::assertSame(2, $event->guestCapacity);
        self::assertSame([['type' => 'double', 'count' => 1]], $event->bedComposition);
    }

    #[Test]
    public function itThrowsWhenRoomTypeNotFound(): void
    {
        try {
            ($this->handler)(new UpdateRoomTypeCommand(
                id: '00000000-0000-4000-8000-000000000000',
                name: 'X',
                livingSpaceCount: 1,
                surfaceM2: null,
                guestCapacity: 1,
                isAccessible: false,
                bedEntries: [['type' => 'single', 'count' => 1]],
            ));
            self::fail('Expected RoomTypeNotFoundException was not thrown');
        } catch (RoomTypeNotFoundException $e) {
            // expected
        }

        self::assertEmpty($this->dispatcher->getDispatched());
    }

    #[Test]
    public function itThrowsWhenNewNameAlreadyTaken(): void
    {
        $registerHandler = new RegisterRoomTypeCommandHandler($this->repository, new FakeHotelExistenceChecker(), new FakeEventDispatcher());
        ($registerHandler)(new RegisterRoomTypeCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            hotelId: self::HOTEL_ID,
            name: 'Double',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 2,
            isAccessible: false,
            bedEntries: [['type' => 'double', 'count' => 1]],
            createdAt: new \DateTimeImmutable(),
        ));

        try {
            ($this->handler)(new UpdateRoomTypeCommand(
                id: self::ROOM_TYPE_ID,
                name: 'Double',
                livingSpaceCount: 1,
                surfaceM2: null,
                guestCapacity: 1,
                isAccessible: false,
                bedEntries: [['type' => 'single', 'count' => 1]],
            ));
            self::fail('Expected RoomTypeAlreadyExistsException was not thrown');
        } catch (RoomTypeAlreadyExistsException $e) {
            // expected
        }

        self::assertEmpty($this->dispatcher->getDispatched());
    }

    #[Test]
    public function itAllowsKeepingTheSameName(): void
    {
        ($this->handler)(new UpdateRoomTypeCommand(
            id: self::ROOM_TYPE_ID,
            name: 'Single',
            livingSpaceCount: 1,
            surfaceM2: 20,
            guestCapacity: 1,
            isAccessible: false,
            bedEntries: [['type' => 'single', 'count' => 1]],
        ));

        $updated = $this->repository->get(self::ROOM_TYPE_ID);
        self::assertNotNull($updated);
        self::assertSame(20, $updated->surfaceM2);

        $event = $this->dispatcher->getLastDispatched();
        self::assertInstanceOf(RoomTypeUpdated::class, $event);
        self::assertSame(self::ROOM_TYPE_ID, $event->roomTypeId);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\RegisterRoom;

use App\Room\Application\UseCase\RegisterRoom\RegisterRoomCommand;
use App\Room\Application\UseCase\RegisterRoom\RegisterRoomCommandHandler;
use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomAlreadyExistsException;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Shared\Domain\Event\RoomRegistered;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\FakeRoomTypeExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegisterRoomCommandHandlerTest extends TestCase
{
    private const string ROOM_TYPE_ID = 'cccccccc-0000-4000-8000-000000000001';

    private InMemoryRoomRepository $roomRepository;
    private FakeHotelExistenceChecker $hotelExistenceChecker;
    private FakeRoomTypeExistenceChecker $roomTypeExistenceChecker;
    private FakeEventDispatcher $eventDispatcher;
    private RegisterRoomCommandHandler $handler;

    protected function setUp(): void
    {
        $this->roomRepository = new InMemoryRoomRepository();
        $this->hotelExistenceChecker = new FakeHotelExistenceChecker();
        $this->roomTypeExistenceChecker = new FakeRoomTypeExistenceChecker();
        $this->eventDispatcher = new FakeEventDispatcher();
        $this->handler = new RegisterRoomCommandHandler(
            $this->roomRepository,
            $this->hotelExistenceChecker,
            $this->roomTypeExistenceChecker,
            $this->eventDispatcher,
        );
    }

    #[Test]
    public function itPersistsTheRoom(): void
    {
        $command = new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 1,
            roomTypeId: self::ROOM_TYPE_ID,
            createdAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
        );

        ($this->handler)($command);

        $room = $this->roomRepository->get($command->id);
        self::assertNotNull($room);
        self::assertSame($command->id, $room->id);
        self::assertSame($command->hotelId, $room->hotelId);
        self::assertSame('101', $room->number->value);
        self::assertSame(1, $room->floor->value);
        self::assertSame(self::ROOM_TYPE_ID, $room->roomTypeId);
        self::assertEquals($command->createdAt, $room->createdAt);
    }

    #[Test]
    public function itThrowsWhenHotelDoesNotExist(): void
    {
        $this->hotelExistenceChecker->setExists(false);
        $this->expectException(HotelNotFoundException::class);

        ($this->handler)(new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 1,
            roomTypeId: self::ROOM_TYPE_ID,
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itThrowsWhenRoomNumberAlreadyExistsInHotel(): void
    {
        $command = new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 1,
            roomTypeId: self::ROOM_TYPE_ID,
            createdAt: new \DateTimeImmutable(),
        );
        ($this->handler)($command);

        $this->expectException(RoomAlreadyExistsException::class);

        ($this->handler)(new RegisterRoomCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 2,
            roomTypeId: self::ROOM_TYPE_ID,
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itThrowsWhenRoomTypeDoesNotExist(): void
    {
        $this->roomTypeExistenceChecker->setExists(false);
        $this->expectException(RoomTypeNotFoundException::class);

        ($this->handler)(new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 1,
            roomTypeId: self::ROOM_TYPE_ID,
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itAllowsSameNumberInDifferentHotels(): void
    {
        $command1 = new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440001',
            number: '101',
            floor: 1,
            roomTypeId: self::ROOM_TYPE_ID,
            createdAt: new \DateTimeImmutable(),
        );
        $command2 = new RegisterRoomCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            hotelId: '550e8400-e29b-41d4-a716-446655440002',
            number: '101',
            floor: 1,
            roomTypeId: self::ROOM_TYPE_ID,
            createdAt: new \DateTimeImmutable(),
        );

        ($this->handler)($command1);
        ($this->handler)($command2);

        self::assertNotNull($this->roomRepository->get($command1->id));
        self::assertNotNull($this->roomRepository->get($command2->id));
    }

    #[Test]
    public function itDispatchesRoomRegistered(): void
    {
        $command = new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 1,
            roomTypeId: self::ROOM_TYPE_ID,
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        ($this->handler)($command);

        $event = $this->eventDispatcher->getLastDispatched();
        self::assertInstanceOf(RoomRegistered::class, $event);
        self::assertSame($command->id, $event->roomId);
        self::assertSame($command->hotelId, $event->hotelId);
        self::assertSame(self::ROOM_TYPE_ID, $event->roomTypeId);
    }

    #[Test]
    public function itDoesNotDispatchWhenRoomAlreadyExists(): void
    {
        $command = new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 1,
            roomTypeId: self::ROOM_TYPE_ID,
            createdAt: new \DateTimeImmutable(),
        );
        ($this->handler)($command);

        try {
            ($this->handler)(new RegisterRoomCommand(
                id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
                hotelId: '550e8400-e29b-41d4-a716-446655440000',
                number: '101',
                floor: 2,
                roomTypeId: self::ROOM_TYPE_ID,
                createdAt: new \DateTimeImmutable(),
            ));
            self::fail('Expected RoomAlreadyExistsException was not thrown');
        } catch (RoomAlreadyExistsException $e) {
            // expected
        }

        $dispatched = $this->eventDispatcher->getDispatched();
        self::assertCount(1, $dispatched);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\BatchRegisterRooms;

use App\Room\Application\UseCase\BatchRegisterRooms\BatchRegisterRoomsCommand;
use App\Room\Application\UseCase\BatchRegisterRooms\BatchRegisterRoomsCommandHandler;
use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomBatchInvalidException;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\FakeRoomTypeExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BatchRegisterRoomsCommandHandlerTest extends TestCase
{
    private const string HOTEL_ID = '550e8400-e29b-41d4-a716-446655440000';
    private const string ROOM_TYPE_ID = 'cccccccc-0000-4000-8000-000000000001';

    private InMemoryRoomRepository $roomRepository;
    private FakeHotelExistenceChecker $hotelExistenceChecker;
    private FakeRoomTypeExistenceChecker $roomTypeExistenceChecker;
    private BatchRegisterRoomsCommandHandler $handler;

    protected function setUp(): void
    {
        $this->roomRepository = new InMemoryRoomRepository();
        $this->hotelExistenceChecker = new FakeHotelExistenceChecker();
        $this->roomTypeExistenceChecker = new FakeRoomTypeExistenceChecker();
        $this->handler = new BatchRegisterRoomsCommandHandler(
            $this->roomRepository,
            $this->hotelExistenceChecker,
            $this->roomTypeExistenceChecker,
        );
    }

    #[Test]
    public function itPersistsAllRooms(): void
    {
        $command = new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [
                ['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000001'), 'number' => '101', 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)],
                ['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000002'), 'number' => '102', 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)],
            ],
            createdAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
        );

        ($this->handler)($command);

        $room1 = $this->roomRepository->get(new RoomId('aaaaaaaa-0000-4000-8000-000000000001'));
        $room2 = $this->roomRepository->get(new RoomId('aaaaaaaa-0000-4000-8000-000000000002'));
        self::assertNotNull($room1);
        self::assertNotNull($room2);
        self::assertSame('101', $room1->number->value);
        self::assertSame('102', $room2->number->value);
        self::assertSame(1, $room1->floor->value);
    }

    #[Test]
    public function itSucceedsWithEmptyBatch(): void
    {
        $command = new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [],
            createdAt: new \DateTimeImmutable(),
        );

        $this->expectNotToPerformAssertions();

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenHotelDoesNotExist(): void
    {
        $this->hotelExistenceChecker->setExists(false);
        $this->expectException(HotelNotFoundException::class);

        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000001'), 'number' => '101', 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)]],
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itRejectsBlankNumber(): void
    {
        $this->expectException(RoomBatchInvalidException::class);

        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000001'), 'number' => '', 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)]],
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itRejectsNumberExceeding50Characters(): void
    {
        $this->expectException(RoomBatchInvalidException::class);

        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000001'), 'number' => str_repeat('X', 51), 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)]],
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itRejectsFloorBelowMinimum(): void
    {
        $this->expectException(RoomBatchInvalidException::class);

        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000001'), 'number' => '101', 'floor' => -21, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)]],
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itRejectsFloorAboveMaximum(): void
    {
        $this->expectException(RoomBatchInvalidException::class);

        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000001'), 'number' => '101', 'floor' => 301, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)]],
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itRejectsDuplicateWithinBatch(): void
    {
        $exception = null;
        try {
            ($this->handler)(new BatchRegisterRoomsCommand(
                hotelId: self::HOTEL_ID,
                entries: [
                    ['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000001'), 'number' => '101', 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)],
                    ['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000002'), 'number' => '101', 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)],
                ],
                createdAt: new \DateTimeImmutable(),
            ));
        } catch (RoomBatchInvalidException $e) {
            $exception = $e;
        }

        self::assertNotNull($exception);
        self::assertCount(1, $exception->violations);
        self::assertSame('line[3]', $exception->violations[0]['field']);
    }

    #[Test]
    public function itRejectsDuplicateAlreadyInRepository(): void
    {
        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000001'), 'number' => '101', 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)]],
            createdAt: new \DateTimeImmutable(),
        ));

        $exception = null;
        try {
            ($this->handler)(new BatchRegisterRoomsCommand(
                hotelId: self::HOTEL_ID,
                entries: [['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000002'), 'number' => '101', 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)]],
                createdAt: new \DateTimeImmutable(),
            ));
        } catch (RoomBatchInvalidException $e) {
            $exception = $e;
        }

        self::assertNotNull($exception);
        self::assertCount(1, $exception->violations);
        self::assertSame('line[2]', $exception->violations[0]['field']);
    }

    #[Test]
    public function itReportsAllViolationsAtOnce(): void
    {
        $exception = null;
        try {
            ($this->handler)(new BatchRegisterRoomsCommand(
                hotelId: self::HOTEL_ID,
                entries: [
                    ['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000001'), 'number' => '', 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)],
                    ['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000002'), 'number' => str_repeat('X', 51), 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)],
                    ['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000003'), 'number' => '101', 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)],
                    ['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000004'), 'number' => '101', 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)],
                ],
                createdAt: new \DateTimeImmutable(),
            ));
        } catch (RoomBatchInvalidException $e) {
            $exception = $e;
        }

        self::assertNotNull($exception);
        self::assertCount(3, $exception->violations);
        self::assertSame('line[2]', $exception->violations[0]['field']);
        self::assertSame('line[3]', $exception->violations[1]['field']);
        self::assertSame('line[5]', $exception->violations[2]['field']);
    }

    #[Test]
    public function itRejectsEntryWithNonExistentRoomType(): void
    {
        $this->roomTypeExistenceChecker->setExists(false);

        $exception = null;
        try {
            ($this->handler)(new BatchRegisterRoomsCommand(
                hotelId: self::HOTEL_ID,
                entries: [['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000001'), 'number' => '101', 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)]],
                createdAt: new \DateTimeImmutable(),
            ));
        } catch (RoomBatchInvalidException $e) {
            $exception = $e;
        }

        self::assertNotNull($exception);
        self::assertCount(1, $exception->violations);
        self::assertSame('line[2]', $exception->violations[0]['field']);
        self::assertStringContainsString('Room type not found', $exception->violations[0]['message']);
    }

    #[Test]
    public function itDoesNotPersistAnythingWhenValidationFails(): void
    {
        try {
            ($this->handler)(new BatchRegisterRoomsCommand(
                hotelId: self::HOTEL_ID,
                entries: [
                    ['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000001'), 'number' => '101', 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)],
                    ['id' => new RoomId('aaaaaaaa-0000-4000-8000-000000000002'), 'number' => '', 'floor' => 1, 'roomTypeId' => new RoomTypeId(self::ROOM_TYPE_ID)],
                ],
                createdAt: new \DateTimeImmutable(),
            ));
        } catch (RoomBatchInvalidException) {
        }

        self::assertNull($this->roomRepository->get(new RoomId('aaaaaaaa-0000-4000-8000-000000000001')));
    }
}

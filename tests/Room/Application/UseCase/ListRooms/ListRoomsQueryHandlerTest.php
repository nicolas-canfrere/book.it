<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\ListRooms;

use App\Room\Application\UseCase\ListRooms\ListRoomsQuery;
use App\Room\Application\UseCase\ListRooms\ListRoomsQueryHandler;
use App\Room\Domain\Model\Room;
use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ListRoomsQueryHandlerTest extends TestCase
{
    private const string HOTEL_ID = '550e8400-e29b-41d4-a716-446655440000';
    private InMemoryRoomRepository $repository;
    private ListRoomsQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRoomRepository();
        $this->handler = new ListRoomsQueryHandler($this->repository);
    }

    #[Test]
    public function itReturnsEmptyPageWhenNoRoomsExist(): void
    {
        $result = ($this->handler)(new ListRoomsQuery(self::HOTEL_ID));

        self::assertCount(0, $result->rooms);
        self::assertSame(0, $result->total);
    }

    #[Test]
    public function itReturnsRoomsSortedByNumberAscending(): void
    {
        $this->repository->add($this->makeRoom('1', self::HOTEL_ID, '202'));
        $this->repository->add($this->makeRoom('2', self::HOTEL_ID, '101'));

        $result = ($this->handler)(new ListRoomsQuery(self::HOTEL_ID));

        self::assertCount(2, $result->rooms);
        self::assertSame('101', $result->rooms[0]->number->value);
        self::assertSame('202', $result->rooms[1]->number->value);
    }

    #[Test]
    public function itOnlyReturnsRoomsForTheGivenHotel(): void
    {
        $otherHotelId = '550e8400-e29b-41d4-a716-446655440001';
        $this->repository->add($this->makeRoom('1', self::HOTEL_ID, '101'));
        $this->repository->add($this->makeRoom('2', $otherHotelId, '201'));

        $result = ($this->handler)(new ListRoomsQuery(self::HOTEL_ID));

        self::assertCount(1, $result->rooms);
        self::assertSame(1, $result->total);
        self::assertSame('101', $result->rooms[0]->number->value);
    }

    #[Test]
    public function itPaginatesResults(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->repository->add($this->makeRoom((string) $i, self::HOTEL_ID, \sprintf('%03d', $i)));
        }

        $result = ($this->handler)(new ListRoomsQuery(self::HOTEL_ID, page: 2, limit: 2));

        self::assertCount(2, $result->rooms);
        self::assertSame(5, $result->total);
    }

    #[Test]
    public function itReturnsCorrectTotalWhenPageExceedsResults(): void
    {
        $this->repository->add($this->makeRoom('1', self::HOTEL_ID, '101'));

        $result = ($this->handler)(new ListRoomsQuery(self::HOTEL_ID, page: 99, limit: 20));

        self::assertCount(0, $result->rooms);
        self::assertSame(1, $result->total);
    }

    private function makeRoom(string $id, string $hotelId, string $number): Room
    {
        return new Room($id, $hotelId, new RoomNumber($number), new RoomFloor(1), 'cccccccc-0000-4000-8000-000000000001', new \DateTimeImmutable('2024-01-01'));
    }
}

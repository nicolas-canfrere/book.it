<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\DeclareRoomTypeAmenities;

use App\Room\Application\UseCase\DeclareRoomTypeAmenities\DeclareRoomTypeAmenitiesCommand;
use App\Room\Application\UseCase\DeclareRoomTypeAmenities\DeclareRoomTypeAmenitiesCommandHandler;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommandHandler;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Room\Domain\ValueObject\RoomAmenity;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeclareRoomTypeAmenitiesCommandHandlerTest extends TestCase
{
    private const string HOTEL_ID = '550e8400-e29b-41d4-a716-446655440000';
    private const string ROOM_TYPE_ID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    private InMemoryRoomTypeRepository $repository;
    private DeclareRoomTypeAmenitiesCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRoomTypeRepository();
        $this->handler = new DeclareRoomTypeAmenitiesCommandHandler($this->repository);

        $registerHandler = new RegisterRoomTypeCommandHandler(
            $this->repository,
            new FakeHotelExistenceChecker(),
            new FakeEventDispatcher(),
        );
        ($registerHandler)(new RegisterRoomTypeCommand(
            id: self::ROOM_TYPE_ID,
            hotelId: self::HOTEL_ID,
            name: 'Standard',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 2,
            isAccessible: false,
            bedEntries: [['type' => 'double', 'count' => 1]],
            createdAt: new \DateTimeImmutable('2024-01-01'),
        ));
    }

    #[Test]
    public function itDeclaresSetsAmenities(): void
    {
        ($this->handler)(new DeclareRoomTypeAmenitiesCommand(
            roomTypeId: self::ROOM_TYPE_ID,
            amenities: ['wifi', 'tv', 'minibar'],
        ));

        $updated = $this->repository->get(self::ROOM_TYPE_ID);
        self::assertNotNull($updated);
        self::assertCount(3, $updated->amenities);
        self::assertSame(RoomAmenity::Wifi, $updated->amenities[0]);
        self::assertSame(RoomAmenity::Tv, $updated->amenities[1]);
        self::assertSame(RoomAmenity::Minibar, $updated->amenities[2]);
    }

    #[Test]
    public function itDeclaresEmptyList(): void
    {
        ($this->handler)(new DeclareRoomTypeAmenitiesCommand(
            roomTypeId: self::ROOM_TYPE_ID,
            amenities: [],
        ));

        $updated = $this->repository->get(self::ROOM_TYPE_ID);
        self::assertNotNull($updated);
        self::assertSame([], $updated->amenities);
    }

    #[Test]
    public function itThrowsWhenRoomTypeNotFound(): void
    {
        $this->expectException(RoomTypeNotFoundException::class);

        ($this->handler)(new DeclareRoomTypeAmenitiesCommand(
            roomTypeId: '00000000-0000-4000-8000-000000000000',
            amenities: ['wifi'],
        ));
    }
}

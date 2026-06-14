<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\ListRoomTypesByAmenity;

use App\Room\Application\UseCase\DeclareRoomTypeAmenities\DeclareRoomTypeAmenitiesCommand;
use App\Room\Application\UseCase\DeclareRoomTypeAmenities\DeclareRoomTypeAmenitiesCommandHandler;
use App\Room\Application\UseCase\ListRoomTypesByAmenity\ListRoomTypesByAmenityQuery;
use App\Room\Application\UseCase\ListRoomTypesByAmenity\ListRoomTypesByAmenityQueryHandler;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommandHandler;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeCatalogueFinder;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ListRoomTypesByAmenityQueryHandlerTest extends TestCase
{
    private const string HOTEL_ID = '550e8400-e29b-41d4-a716-446655440000';
    private const string RT_WIFI_BALCONY = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380001';
    private const string RT_WIFI_ONLY = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380002';
    private const string RT_NO_AMENITIES = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380003';

    private InMemoryRoomTypeCatalogueFinder $finder;
    private ListRoomTypesByAmenityQueryHandler $handler;

    protected function setUp(): void
    {
        $repository = new InMemoryRoomTypeRepository();
        $this->finder = new InMemoryRoomTypeCatalogueFinder();

        $registerHandler = new RegisterRoomTypeCommandHandler(
            $repository,
            new FakeHotelExistenceChecker(),
            new FakeEventDispatcher(),
        );
        $amenitiesHandler = new DeclareRoomTypeAmenitiesCommandHandler(
            $repository,
            new FakeEventDispatcher(),
        );

        ($registerHandler)(new RegisterRoomTypeCommand(
            id: new RoomTypeId(self::RT_WIFI_BALCONY),
            hotelId: new HotelId(self::HOTEL_ID),
            name: 'Suite Balcony',
            livingSpaceCount: 2,
            surfaceM2: null,
            guestCapacity: 2,
            isAccessible: false,
            bedEntries: [['type' => 'double', 'count' => 1]],
            createdAt: new \DateTimeImmutable(),
        ));
        ($amenitiesHandler)(new DeclareRoomTypeAmenitiesCommand(new RoomTypeId(self::RT_WIFI_BALCONY), ['wifi', 'balcony']));

        ($registerHandler)(new RegisterRoomTypeCommand(
            id: new RoomTypeId(self::RT_WIFI_ONLY),
            hotelId: new HotelId(self::HOTEL_ID),
            name: 'Standard',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 2,
            isAccessible: false,
            bedEntries: [['type' => 'single', 'count' => 1]],
            createdAt: new \DateTimeImmutable(),
        ));
        ($amenitiesHandler)(new DeclareRoomTypeAmenitiesCommand(new RoomTypeId(self::RT_WIFI_ONLY), ['wifi']));

        ($registerHandler)(new RegisterRoomTypeCommand(
            id: new RoomTypeId(self::RT_NO_AMENITIES),
            hotelId: new HotelId(self::HOTEL_ID),
            name: 'Basic',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 1,
            isAccessible: false,
            bedEntries: [['type' => 'single', 'count' => 1]],
            createdAt: new \DateTimeImmutable(),
        ));

        // Sync registered room types into the finder
        foreach ([$repository->get(new RoomTypeId(self::RT_WIFI_BALCONY)), $repository->get(new RoomTypeId(self::RT_WIFI_ONLY)), $repository->get(new RoomTypeId(self::RT_NO_AMENITIES))] as $rt) {
            if (null !== $rt) {
                $this->finder->add($rt);
            }
        }

        $this->handler = new ListRoomTypesByAmenityQueryHandler($this->finder);
    }

    #[Test]
    public function itReturnsAllRoomTypesWhenNoAmenityFilterGiven(): void
    {
        $page = ($this->handler)(new ListRoomTypesByAmenityQuery(new HotelId(self::HOTEL_ID), [], 1, 20));

        self::assertSame(3, $page->total);
    }

    #[Test]
    public function itFiltersRoomTypesByASingleAmenity(): void
    {
        $page = ($this->handler)(new ListRoomTypesByAmenityQuery(new HotelId(self::HOTEL_ID), ['wifi'], 1, 20));

        self::assertSame(2, $page->total);
        self::assertSame('Standard', $page->roomTypes[0]->name);
        self::assertSame('Suite Balcony', $page->roomTypes[1]->name);
    }

    #[Test]
    public function itFiltersRoomTypesByMultipleAmenitiesWithAndLogic(): void
    {
        $page = ($this->handler)(new ListRoomTypesByAmenityQuery(new HotelId(self::HOTEL_ID), ['wifi', 'balcony'], 1, 20));

        self::assertSame(1, $page->total);
        self::assertSame('Suite Balcony', $page->roomTypes[0]->name);
    }

    #[Test]
    public function itReturnsEmptyPageWhenNoRoomTypeMatchesAllAmenities(): void
    {
        $page = ($this->handler)(new ListRoomTypesByAmenityQuery(new HotelId(self::HOTEL_ID), ['wifi', 'balcony', 'jacuzzi'], 1, 20));

        self::assertSame(0, $page->total);
        self::assertCount(0, $page->roomTypes);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Search\Infrastructure\Persistence;

use App\Search\Infrastructure\Persistence\HotelRoomTypeWriter;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class HotelRoomTypeWriterTest extends TestCase
{
    #[Test]
    public function itUpdatesStarRating(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE hotel_room_types SET star_rating = :starRating WHERE hotel_id = :hotelId',
                ['starRating' => 4, 'hotelId' => 'hotel-id-1'],
            );

        (new HotelRoomTypeWriter($connection, $this->createStub(Connection::class)))
            ->updateStarRating(new HotelId('hotel-id-1'), 4);
    }

    #[Test]
    public function itSetsNullStarRating(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE hotel_room_types SET star_rating = :starRating WHERE hotel_id = :hotelId',
                ['starRating' => null, 'hotelId' => 'hotel-id-1'],
            );

        (new HotelRoomTypeWriter($connection, $this->createStub(Connection::class)))
            ->updateStarRating(new HotelId('hotel-id-1'), null);
    }

    #[Test]
    public function itUpdatesHotelAmenities(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE hotel_room_types SET hotel_amenities = :amenities WHERE hotel_id = :hotelId',
                ['amenities' => '["pool","gym"]', 'hotelId' => 'hotel-id-1'],
            );

        (new HotelRoomTypeWriter($connection, $this->createStub(Connection::class)))
            ->updateHotelAmenities(new HotelId('hotel-id-1'), ['pool', 'gym']);
    }

    #[Test]
    public function itUpsertsRoomTypeWithDenormalizedHotelData(): void
    {
        $searchConnection = $this->createMock(Connection::class);
        $hotelConnection = $this->createStub(Connection::class);

        $hotelConnection->method('fetchAssociative')->willReturn([
            'name' => 'Le Grand Hôtel',
            'city' => 'Paris',
            'country' => 'FR',
            'stars' => 4,
            'amenities' => '{pool}',
        ]);

        $searchConnection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('INSERT INTO hotel_room_types'),
                $this->callback(static function (array $p): bool {
                    return 'rt-id-1' === $p['roomTypeId']
                        && 'hotel-id-1' === $p['hotelId']
                        && 'Le Grand Hôtel' === $p['hotelName']
                        && 'Paris' === $p['city']
                        && 'FR' === $p['country']
                        && 4 === $p['starRating']
                        && '["pool"]' === $p['hotelAmenities']
                        && 'Standard' === $p['roomTypeName']
                        && 2 === $p['guestCapacity'];
                }),
            );

        (new HotelRoomTypeWriter($searchConnection, $hotelConnection))
            ->upsertRoomType(new RoomTypeId('rt-id-1'), new HotelId('hotel-id-1'), 'Standard', 2, [['type' => 'double', 'count' => 1]]);
    }

    #[Test]
    public function itSkipsUpsertWhenHotelNotFound(): void
    {
        $searchConnection = $this->createMock(Connection::class);
        $hotelConnection = $this->createStub(Connection::class);

        $hotelConnection->method('fetchAssociative')->willReturn(false);
        $searchConnection->expects($this->never())->method('executeStatement');

        (new HotelRoomTypeWriter($searchConnection, $hotelConnection))
            ->upsertRoomType(new RoomTypeId('rt-id-1'), new HotelId('missing-hotel'), 'Standard', 2, []);
    }

    #[Test]
    public function itUpdatesRoomType(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('UPDATE hotel_room_types'),
                $this->callback(static function (array $p): bool {
                    return 'rt-id-1' === $p['roomTypeId']
                        && 'Standard Plus' === $p['name']
                        && 3 === $p['guestCapacity'];
                }),
            );

        (new HotelRoomTypeWriter($connection, $this->createStub(Connection::class)))
            ->updateRoomType(new RoomTypeId('rt-id-1'), 'Standard Plus', 3, [['type' => 'king', 'count' => 1]]);
    }

    #[Test]
    public function itUpdatesRoomAmenities(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE hotel_room_types SET room_amenities = :amenities WHERE room_type_id = :roomTypeId',
                ['amenities' => '["wifi","tv"]', 'roomTypeId' => 'rt-id-1'],
            );

        (new HotelRoomTypeWriter($connection, $this->createStub(Connection::class)))
            ->updateRoomAmenities(new RoomTypeId('rt-id-1'), ['wifi', 'tv']);
    }

    #[Test]
    public function itDeletesRoomType(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'DELETE FROM hotel_room_types WHERE room_type_id = :roomTypeId',
                ['roomTypeId' => 'rt-id-1'],
            );

        (new HotelRoomTypeWriter($connection, $this->createStub(Connection::class)))
            ->deleteRoomType(new RoomTypeId('rt-id-1'));
    }

    #[Test]
    public function itUpdatesBaseRateByRoomLookingUpRoomIndex(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                'SELECT room_type_id FROM room_index WHERE room_id = :roomId',
                ['roomId' => 'room-id-1'],
            )
            ->willReturn(['room_type_id' => 'rt-id-1']);

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE hotel_room_types SET base_price_cents = :amountCents WHERE room_type_id = :roomTypeId',
                ['amountCents' => 15000, 'roomTypeId' => 'rt-id-1'],
            );

        (new HotelRoomTypeWriter($connection, $this->createStub(Connection::class)))
            ->updateBaseRateByRoom(new RoomId('room-id-1'), 15000);
    }

    #[Test]
    public function itSkipsBaseRateUpdateWhenRoomNotIndexed(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        $connection->expects($this->never())->method('executeStatement');

        (new HotelRoomTypeWriter($connection, $this->createStub(Connection::class)))
            ->updateBaseRateByRoom(new RoomId('unknown-room'), 15000);
    }
}

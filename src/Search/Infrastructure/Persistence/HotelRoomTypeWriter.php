<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Persistence;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use Doctrine\DBAL\Connection;

final readonly class HotelRoomTypeWriter implements HotelRoomTypeWriterInterface
{
    public function __construct(
        private Connection $searchConnection,
        private Connection $hotelConnection,
    ) {
    }

    public function updateStarRating(HotelId $hotelId, ?int $starRating): void
    {
        $this->searchConnection->executeStatement(
            'UPDATE hotel_room_types SET star_rating = :starRating WHERE hotel_id = :hotelId',
            ['starRating' => $starRating, 'hotelId' => $hotelId->value],
        );
    }

    public function updateHotelAmenities(HotelId $hotelId, array $amenities): void
    {
        $this->searchConnection->executeStatement(
            'UPDATE hotel_room_types SET hotel_amenities = :amenities WHERE hotel_id = :hotelId',
            ['amenities' => json_encode($amenities, \JSON_THROW_ON_ERROR), 'hotelId' => $hotelId->value],
        );
    }

    public function upsertRoomType(
        RoomTypeId $roomTypeId,
        HotelId $hotelId,
        string $name,
        int $guestCapacity,
        array $bedComposition,
    ): void {
        $hotel = $this->hotelConnection->fetchAssociative(
            'SELECT name, city, country, stars, amenities FROM hotel WHERE id = :id',
            ['id' => $hotelId->value],
        );

        if (false === $hotel) {
            return;
        }

        $this->searchConnection->executeStatement(
            <<<'SQL'
            INSERT INTO hotel_room_types
                (room_type_id, hotel_id, hotel_name, city, country, star_rating, hotel_amenities,
                 room_type_name, guest_capacity, bed_composition, room_amenities)
            VALUES
                (:roomTypeId, :hotelId, :hotelName, :city, :country, :starRating, :hotelAmenities,
                 :roomTypeName, :guestCapacity, :bedComposition, '[]')
            ON CONFLICT (room_type_id) DO UPDATE SET
                hotel_name      = EXCLUDED.hotel_name,
                city            = EXCLUDED.city,
                country         = EXCLUDED.country,
                star_rating     = EXCLUDED.star_rating,
                hotel_amenities = EXCLUDED.hotel_amenities,
                room_type_name  = EXCLUDED.room_type_name,
                guest_capacity  = EXCLUDED.guest_capacity,
                bed_composition = EXCLUDED.bed_composition
            SQL,
            [
                'roomTypeId' => $roomTypeId->value,
                'hotelId' => $hotelId->value,
                'hotelName' => $hotel['name'],
                'city' => $hotel['city'],
                'country' => $hotel['country'],
                'starRating' => is_numeric($hotel['stars']) ? (int) $hotel['stars'] : null,
                'hotelAmenities' => $this->parsePostgresAmenities(is_string($hotel['amenities']) ? $hotel['amenities'] : '{}'),
                'roomTypeName' => $name,
                'guestCapacity' => $guestCapacity,
                'bedComposition' => json_encode($bedComposition, \JSON_THROW_ON_ERROR),
            ],
        );
    }

    public function updateRoomType(
        RoomTypeId $roomTypeId,
        string $name,
        int $guestCapacity,
        array $bedComposition,
    ): void {
        $this->searchConnection->executeStatement(
            <<<'SQL'
            UPDATE hotel_room_types
            SET room_type_name  = :name,
                guest_capacity  = :guestCapacity,
                bed_composition = :bedComposition
            WHERE room_type_id = :roomTypeId
            SQL,
            [
                'name' => $name,
                'guestCapacity' => $guestCapacity,
                'bedComposition' => json_encode($bedComposition, \JSON_THROW_ON_ERROR),
                'roomTypeId' => $roomTypeId->value,
            ],
        );
    }

    public function updateRoomAmenities(RoomTypeId $roomTypeId, array $amenities): void
    {
        $this->searchConnection->executeStatement(
            'UPDATE hotel_room_types SET room_amenities = :amenities WHERE room_type_id = :roomTypeId',
            ['amenities' => json_encode($amenities, \JSON_THROW_ON_ERROR), 'roomTypeId' => $roomTypeId->value],
        );
    }

    public function deleteRoomType(RoomTypeId $roomTypeId): void
    {
        $this->searchConnection->executeStatement(
            'DELETE FROM hotel_room_types WHERE room_type_id = :roomTypeId',
            ['roomTypeId' => $roomTypeId->value],
        );
    }

    public function updateBaseRateByRoom(RoomId $roomId, int $amountCents): void
    {
        $roomRow = $this->searchConnection->fetchAssociative(
            'SELECT room_type_id FROM room_index WHERE room_id = :roomId',
            ['roomId' => $roomId->value],
        );

        if (false === $roomRow) {
            return;
        }

        $this->searchConnection->executeStatement(
            'UPDATE hotel_room_types SET base_price_cents = :amountCents WHERE room_type_id = :roomTypeId',
            ['amountCents' => $amountCents, 'roomTypeId' => $roomRow['room_type_id']],
        );
    }

    private function parsePostgresAmenities(string $raw): string
    {
        if ('{}' === $raw) {
            return '[]';
        }

        preg_match_all('/"([^"]+)"|([^,{}]+)/', $raw, $matches);
        $values = array_map(
            static fn(string $quoted, string $plain): string => '' !== $quoted ? $quoted : $plain,
            $matches[1],
            $matches[2],
        );

        return json_encode(array_values(array_filter($values, static fn(string $v): bool => '' !== $v)), \JSON_THROW_ON_ERROR);
    }
}

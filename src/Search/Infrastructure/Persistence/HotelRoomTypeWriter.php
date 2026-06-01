<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Persistence;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use Doctrine\DBAL\Connection;

final readonly class HotelRoomTypeWriter implements HotelRoomTypeWriterInterface
{
    public function __construct(
        private Connection $connection,
        private Connection $hotelConnection,
    ) {
    }

    public function updateStarRating(string $hotelId, ?int $starRating): void
    {
        $this->connection->executeStatement(
            'UPDATE hotel_room_types SET star_rating = :starRating WHERE hotel_id = :hotelId',
            ['starRating' => $starRating, 'hotelId' => $hotelId],
        );
    }

    public function updateHotelAmenities(string $hotelId, array $amenities): void
    {
        $this->connection->executeStatement(
            'UPDATE hotel_room_types SET hotel_amenities = :amenities WHERE hotel_id = :hotelId',
            ['amenities' => json_encode($amenities, \JSON_THROW_ON_ERROR), 'hotelId' => $hotelId],
        );
    }

    public function upsertRoomType(
        string $roomTypeId,
        string $hotelId,
        string $name,
        int $guestCapacity,
        array $bedComposition,
    ): void {
        $hotel = $this->hotelConnection->fetchAssociative(
            'SELECT name, city, country, stars, amenities FROM hotel WHERE id = :id',
            ['id' => $hotelId],
        );

        if (false === $hotel) {
            return;
        }

        $this->connection->executeStatement(
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
                'roomTypeId' => $roomTypeId,
                'hotelId' => $hotelId,
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
        string $roomTypeId,
        string $name,
        int $guestCapacity,
        array $bedComposition,
    ): void {
        $this->connection->executeStatement(
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
                'roomTypeId' => $roomTypeId,
            ],
        );
    }

    public function updateRoomAmenities(string $roomTypeId, array $amenities): void
    {
        $this->connection->executeStatement(
            'UPDATE hotel_room_types SET room_amenities = :amenities WHERE room_type_id = :roomTypeId',
            ['amenities' => json_encode($amenities, \JSON_THROW_ON_ERROR), 'roomTypeId' => $roomTypeId],
        );
    }

    public function deleteRoomType(string $roomTypeId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM hotel_room_types WHERE room_type_id = :roomTypeId',
            ['roomTypeId' => $roomTypeId],
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

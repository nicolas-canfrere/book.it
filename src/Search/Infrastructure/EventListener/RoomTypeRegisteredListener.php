<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\RoomTypeRegistered;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeRegistered::class)]
final readonly class RoomTypeRegisteredListener
{
    public function __construct(
        private Connection $connection,
        private Connection $hotelConnection,
    ) {
    }

    public function __invoke(RoomTypeRegistered $event): void
    {
        /** @var array{name: string, city: string, country: string, stars: int|null, amenities: string}|false $hotel */
        $hotel = $this->hotelConnection->fetchAssociative(
            'SELECT name, city, country, stars, amenities FROM hotel WHERE id = :id',
            ['id' => $event->hotelId],
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
                'roomTypeId' => $event->roomTypeId,
                'hotelId' => $event->hotelId,
                'hotelName' => $hotel['name'],
                'city' => $hotel['city'],
                'country' => $hotel['country'],
                'starRating' => isset($hotel['stars']) ? (int) $hotel['stars'] : null,
                'hotelAmenities' => $this->parsePostgresArray($hotel['amenities']),
                'roomTypeName' => $event->name,
                'guestCapacity' => $event->guestCapacity,
                'bedComposition' => json_encode($event->bedComposition, \JSON_THROW_ON_ERROR),
            ],
        );
    }

    /**
     * Converts a PostgreSQL text[] literal (e.g. "{wifi,pool}") to a JSON array string.
     */
    private function parsePostgresArray(string $raw): string
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

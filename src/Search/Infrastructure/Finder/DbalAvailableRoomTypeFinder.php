<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Finder;

use App\Search\Domain\Port\AvailableRoomTypeFinderInterface;
use Doctrine\DBAL\Connection;

final readonly class DbalAvailableRoomTypeFinder implements AvailableRoomTypeFinderInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function find(
        string $city,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
        int $guests,
    ): array {
        return $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                s.hotel_id,
                s.hotel_name,
                s.city,
                s.country,
                s.star_rating,
                s.hotel_amenities,
                s.room_type_id,
                s.room_type_name,
                s.guest_capacity,
                s.bed_composition,
                s.room_amenities,
                s.base_price_cents
            FROM hotel_room_types s
            WHERE s.city = :city
              AND s.guest_capacity >= :guests
              AND (
                SELECT COUNT(*)
                FROM room_index r
                WHERE r.room_type_id = s.room_type_id
                  AND NOT EXISTS (
                    SELECT 1
                    FROM unavailable_periods u
                    WHERE u.room_id = r.room_id
                      AND u.period && daterange(:checkIn, :checkOut)
                  )
              ) > 0
            ORDER BY s.hotel_name, s.room_type_name
            SQL,
            [
                'city' => $city,
                'guests' => $guests,
                'checkIn' => $checkIn->format('Y-m-d'),
                'checkOut' => $checkOut->format('Y-m-d'),
            ],
        );
    }
}

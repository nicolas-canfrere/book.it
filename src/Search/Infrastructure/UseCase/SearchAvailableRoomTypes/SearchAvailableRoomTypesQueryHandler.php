<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\UseCase\SearchAvailableRoomTypes;

use App\Search\Application\UseCase\SearchAvailableRoomTypes\SearchAvailableRoomTypesQuery;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;
use Doctrine\DBAL\Connection;

final readonly class SearchAvailableRoomTypesQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(SearchAvailableRoomTypesQuery $query): array
    {
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
                'city' => $query->city,
                'guests' => $query->guests,
                'checkIn' => $query->checkIn->format('Y-m-d'),
                'checkOut' => $query->checkOut->format('Y-m-d'),
            ],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Finder;

use App\Search\Domain\AvailableRoomType;
use App\Search\Domain\Port\AvailableRoomTypeFinderInterface;
use Doctrine\DBAL\Connection;

final readonly class DbalAvailableRoomTypeFinder implements AvailableRoomTypeFinderInterface
{
    public function __construct(private Connection $searchConnection)
    {
    }

    /** @return list<AvailableRoomType> */
    public function find(
        string $city,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
        int $guests,
    ): array {
        $rows = $this->searchConnection->fetchAllAssociative(
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

        $results = [];
        foreach ($rows as $row) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): AvailableRoomType
    {
        /** @var array{hotel_id:string,hotel_name:string,city:string,country:string,star_rating:string|null,hotel_amenities:string,room_type_id:string,room_type_name:string,guest_capacity:string,bed_composition:string,room_amenities:string,base_price_cents:string|null} $row */
        /** @var list<string> $hotelAmenities */
        $hotelAmenities = json_decode((string) $row['hotel_amenities'], true, flags: \JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $bedComposition */
        $bedComposition = json_decode((string) $row['bed_composition'], true, flags: \JSON_THROW_ON_ERROR);
        /** @var list<string> $roomAmenities */
        $roomAmenities = json_decode((string) $row['room_amenities'], true, flags: \JSON_THROW_ON_ERROR);

        return new AvailableRoomType(
            hotelId: (string) $row['hotel_id'],
            hotelName: (string) $row['hotel_name'],
            city: (string) $row['city'],
            country: (string) $row['country'],
            starRating: null !== $row['star_rating'] ? (int) $row['star_rating'] : null,
            hotelAmenities: $hotelAmenities,
            roomTypeId: (string) $row['room_type_id'],
            roomTypeName: (string) $row['room_type_name'],
            guestCapacity: (int) $row['guest_capacity'],
            bedComposition: $bedComposition,
            roomAmenities: $roomAmenities,
            basePriceCents: null !== $row['base_price_cents'] ? (int) $row['base_price_cents'] : null,
        );
    }
}

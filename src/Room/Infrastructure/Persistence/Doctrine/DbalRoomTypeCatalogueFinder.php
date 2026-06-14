<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Model\RoomTypePage;
use App\Room\Domain\Port\RoomTypeCatalogueFinderInterface;
use App\Room\Domain\ValueObject\BedComposition;
use App\Room\Domain\ValueObject\RoomAmenity;
use App\Shared\Domain\ValueObject\RoomTypeId;
use Doctrine\DBAL\Connection;

final readonly class DbalRoomTypeCatalogueFinder implements RoomTypeCatalogueFinderInterface
{
    public function __construct(private Connection $roomConnection)
    {
    }

    /** @param string[] $amenities */
    public function find(string $hotelId, array $amenities, int $page, int $limit): RoomTypePage
    {
        $whereClause = 'WHERE hotel_id = :hotelId';
        $params = ['hotelId' => $hotelId];

        if ([] !== $amenities) {
            $whereClause .= ' AND amenities @> :filter::text[]';
            $params['filter'] = '{' . implode(',', $amenities) . '}';
        }

        /** @var int|string $count */
        $count = $this->roomConnection->fetchOne(
            "SELECT COUNT(*) FROM room_type {$whereClause}",
            $params,
        );
        $total = (int) $count;

        /** @var list<array{id: string, hotel_id: string, name: string, living_space_count: int|string, surface_m2: int|string|null, guest_capacity: int|string, is_accessible: string|bool, bed_composition: string, amenities: string, created_at: string}> $rows */
        $rows = $this->roomConnection->fetchAllAssociative(
            "SELECT id, hotel_id, name, living_space_count, surface_m2, guest_capacity, is_accessible, bed_composition, amenities, created_at FROM room_type {$whereClause} ORDER BY name ASC LIMIT :limit OFFSET :offset",
            array_merge($params, ['limit' => $limit, 'offset' => ($page - 1) * $limit]),
        );

        return new RoomTypePage(array_map($this->hydrate(...), $rows), $total);
    }

    /**
     * @param array{id: string, hotel_id: string, name: string, living_space_count: int|string, surface_m2: int|string|null, guest_capacity: int|string, is_accessible: string|bool, bed_composition: string, amenities: string, created_at: string} $row
     */
    private function hydrate(array $row): RoomType
    {
        /** @var list<array{type: string, count: int}> $bedData */
        $bedData = json_decode($row['bed_composition'], true, 512, \JSON_THROW_ON_ERROR);

        return new RoomType(
            new RoomTypeId($row['id']),
            $row['hotel_id'],
            $row['name'],
            (int) $row['living_space_count'],
            null !== $row['surface_m2'] ? (int) $row['surface_m2'] : null,
            (int) $row['guest_capacity'],
            't' === $row['is_accessible'] || true === $row['is_accessible'],
            BedComposition::fromArray($bedData),
            new \DateTimeImmutable($row['created_at']),
            $this->parseAmenities($row['amenities']),
        );
    }

    /** @return array<RoomAmenity> */
    private function parseAmenities(string $raw): array
    {
        if ('{}' === $raw) {
            return [];
        }

        preg_match_all('/"([^"]+)"|([^,{}]+)/', $raw, $matches);
        $values = array_map(
            static fn(string $quoted, string $plain): string => '' !== $quoted ? $quoted : $plain,
            $matches[1],
            $matches[2],
        );

        return array_map(RoomAmenity::from(...), $values);
    }
}

<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Model\RoomTypePage;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\BedComposition;
use App\Room\Domain\ValueObject\RoomAmenity;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

final readonly class RoomTypeRepository implements RoomTypeRepositoryInterface
{
    public function __construct(private Connection $roomConnection)
    {
    }

    public function add(RoomType $roomType): void
    {
        $this->roomConnection->insert('room_type', [
            'id' => $roomType->id->value,
            'hotel_id' => $roomType->hotelId->value,
            'name' => $roomType->name,
            'living_space_count' => $roomType->livingSpaceCount,
            'surface_m2' => $roomType->surfaceM2,
            'guest_capacity' => $roomType->guestCapacity,
            'is_accessible' => $roomType->isAccessible,
            'bed_composition' => json_encode($roomType->bedComposition->toArray(), \JSON_THROW_ON_ERROR),
            'amenities' => $this->serializeAmenities($roomType->amenities),
            'created_at' => $roomType->createdAt->format('Y-m-d H:i:s'),
        ], [
            'is_accessible' => Types::BOOLEAN,
        ]);
    }

    public function get(RoomTypeId $id): ?RoomType
    {
        /** @var array{id: string, hotel_id: string, name: string, living_space_count: int|string, surface_m2: int|string|null, guest_capacity: int|string, is_accessible: string|bool, bed_composition: string, amenities: string, created_at: string}|false $row */
        $row = $this->roomConnection->fetchAssociative(
            'SELECT id, hotel_id, name, living_space_count, surface_m2, guest_capacity, is_accessible, bed_composition, amenities, created_at FROM room_type WHERE id = :id',
            ['id' => $id->value],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function existsByHotelIdAndName(HotelId $hotelId, string $name): bool
    {
        $count = $this->roomConnection->fetchOne(
            'SELECT COUNT(*) FROM room_type WHERE hotel_id = :hotelId AND name = :name',
            ['hotelId' => $hotelId->value, 'name' => $name],
        );

        return $count > 0;
    }

    public function update(RoomType $roomType): void
    {
        $this->roomConnection->update('room_type', [
            'name' => $roomType->name,
            'living_space_count' => $roomType->livingSpaceCount,
            'surface_m2' => $roomType->surfaceM2,
            'guest_capacity' => $roomType->guestCapacity,
            'is_accessible' => $roomType->isAccessible,
            'bed_composition' => json_encode($roomType->bedComposition->toArray(), \JSON_THROW_ON_ERROR),
            'amenities' => $this->serializeAmenities($roomType->amenities),
        ], ['id' => $roomType->id->value], [
            'is_accessible' => Types::BOOLEAN,
        ]);
    }

    public function save(RoomType $roomType): void
    {
        $this->roomConnection->update('room_type', [
            'amenities' => $this->serializeAmenities($roomType->amenities),
        ], ['id' => $roomType->id->value]);
    }

    public function delete(RoomTypeId $id): void
    {
        $this->roomConnection->delete('room_type', ['id' => $id->value]);
    }

    public function list(HotelId $hotelId, int $page, int $limit): RoomTypePage
    {
        /** @var int|string $count */
        $count = $this->roomConnection->fetchOne(
            'SELECT COUNT(*) FROM room_type WHERE hotel_id = :hotelId',
            ['hotelId' => $hotelId->value],
        );
        $total = (int) $count;

        /** @var list<array{id: string, hotel_id: string, name: string, living_space_count: int|string, surface_m2: int|string|null, guest_capacity: int|string, is_accessible: string|bool, bed_composition: string, amenities: string, created_at: string}> $rows */
        $rows = $this->roomConnection->fetchAllAssociative(
            'SELECT id, hotel_id, name, living_space_count, surface_m2, guest_capacity, is_accessible, bed_composition, amenities, created_at FROM room_type WHERE hotel_id = :hotelId ORDER BY name ASC LIMIT :limit OFFSET :offset',
            ['hotelId' => $hotelId->value, 'limit' => $limit, 'offset' => ($page - 1) * $limit],
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
            new HotelId($row['hotel_id']),
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

    /** @param array<RoomAmenity> $amenities */
    private function serializeAmenities(array $amenities): string
    {
        if ([] === $amenities) {
            return '{}';
        }

        return '{' . implode(',', array_map(static fn(RoomAmenity $a) => $a->value, $amenities)) . '}';
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

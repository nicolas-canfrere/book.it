<?php

declare(strict_types=1);

namespace App\Tests\Room\Infrastructure\Persistence\InMemory;

use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Model\RoomTypePage;
use App\Room\Domain\Port\RoomTypeCatalogueFinderInterface;

final class InMemoryRoomTypeCatalogueFinder implements RoomTypeCatalogueFinderInterface
{
    /** @var array<string, RoomType> */
    private array $roomTypes = [];

    public function add(RoomType $roomType): void
    {
        $this->roomTypes[$roomType->id] = $roomType;
    }

    /** @param string[] $amenities */
    public function find(string $hotelId, array $amenities, int $page, int $limit): RoomTypePage
    {
        $filtered = array_values(array_filter(
            $this->roomTypes,
            static function (RoomType $rt) use ($hotelId, $amenities): bool {
                if ($rt->hotelId !== $hotelId) {
                    return false;
                }
                $declared = array_map(static fn($a) => $a->value, $rt->amenities);
                foreach ($amenities as $required) {
                    if (!in_array($required, $declared, true)) {
                        return false;
                    }
                }

                return true;
            },
        ));

        usort($filtered, static fn(RoomType $a, RoomType $b) => strcmp($a->name, $b->name));
        $total = count($filtered);
        $slice = array_slice($filtered, ($page - 1) * $limit, $limit);

        return new RoomTypePage($slice, $total);
    }
}

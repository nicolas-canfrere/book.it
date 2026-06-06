<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\ListRoomTypesByAmenity;

use App\Room\Domain\Model\RoomTypePage;
use App\Room\Domain\Port\RoomTypeCatalogueFinderInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class ListRoomTypesByAmenityQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private RoomTypeCatalogueFinderInterface $finder)
    {
    }

    public function __invoke(ListRoomTypesByAmenityQuery $query): RoomTypePage
    {
        return $this->finder->find($query->hotelId, $query->amenities, $query->page, $query->limit);
    }
}

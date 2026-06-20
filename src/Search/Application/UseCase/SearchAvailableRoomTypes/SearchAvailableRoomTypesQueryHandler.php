<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\SearchAvailableRoomTypes;

use App\Search\Domain\AvailableRoomType;
use App\Search\Domain\Port\AvailableRoomTypeFinderInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class SearchAvailableRoomTypesQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private AvailableRoomTypeFinderInterface $finder)
    {
    }

    /** @return list<AvailableRoomType> */
    public function __invoke(SearchAvailableRoomTypesQuery $query): array
    {
        return $this->finder->find(
            geoPlaceId: $query->geoPlaceId,
            checkIn: $query->checkIn,
            checkOut: $query->checkOut,
            guests: $query->guests,
        );
    }
}

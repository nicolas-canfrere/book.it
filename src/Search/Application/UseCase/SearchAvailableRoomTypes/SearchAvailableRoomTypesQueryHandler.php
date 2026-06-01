<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\SearchAvailableRoomTypes;

use App\Search\Domain\Port\AvailableRoomTypesRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class SearchAvailableRoomTypesQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private AvailableRoomTypesRepositoryInterface $repository)
    {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(SearchAvailableRoomTypesQuery $query): array
    {
        return $this->repository->findAvailable(
            $query->city,
            $query->checkIn,
            $query->checkOut,
            $query->guests,
        );
    }
}

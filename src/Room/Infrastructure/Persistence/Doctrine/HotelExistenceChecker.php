<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Hotel\Application\UseCase\GetHotel\GetHotelQuery;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class HotelExistenceChecker implements HotelExistsInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function exists(string $hotelId): bool
    {
        return null !== $this->queryBus->ask(new GetHotelQuery($hotelId));
    }
}

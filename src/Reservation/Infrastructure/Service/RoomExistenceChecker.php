<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\RoomExistsInterface;
use App\Room\Application\UseCase\GetRoom\GetRoomQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class RoomExistenceChecker implements RoomExistsInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function exists(string $roomId): bool
    {
        return null !== $this->queryBus->ask(new GetRoomQuery($roomId));
    }
}

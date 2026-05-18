<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Availability\Application\UseCase\CheckAvailability\CheckAvailabilityQuery;
use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class AvailabilityChecker implements RoomAvailabilityCheckerInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function isAvailable(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        return $this->queryBus->ask(new CheckAvailabilityQuery($roomId, $checkIn, $checkOut));
    }
}

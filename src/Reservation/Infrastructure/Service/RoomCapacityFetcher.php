<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\RoomCapacityFetcherInterface;
use App\Room\Application\Contract\RoomFinderInterface;

final readonly class RoomCapacityFetcher implements RoomCapacityFetcherInterface
{
    public function __construct(private RoomFinderInterface $rooms)
    {
    }

    public function fetchCapacity(string $roomId): int
    {
        $view = $this->rooms->find($roomId);

        if (null === $view) {
            return 0;
        }

        return $view->capacity;
    }
}

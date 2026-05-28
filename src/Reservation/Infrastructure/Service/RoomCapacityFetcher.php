<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\RoomCapacityFetcherInterface;
use Doctrine\DBAL\Connection;

final readonly class RoomCapacityFetcher implements RoomCapacityFetcherInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function fetchCapacity(string $roomId): int
    {
        $capacity = $this->bookit->fetchOne(
            'SELECT rt.guest_capacity
               FROM room r
               JOIN room_type rt ON rt.id = r.room_type_id
              WHERE r.id = :roomId',
            ['roomId' => $roomId],
        );

        if (false === $capacity) {
            return 0;
        }

        /** @var int|string $capacity */
        return (int) $capacity;
    }
}

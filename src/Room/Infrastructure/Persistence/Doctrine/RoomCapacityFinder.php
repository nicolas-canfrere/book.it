<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Port\RoomCapacityFinderInterface;
use App\Shared\Domain\ValueObject\RoomId;
use Doctrine\DBAL\Connection;

final readonly class RoomCapacityFinder implements RoomCapacityFinderInterface
{
    public function __construct(private Connection $roomConnection)
    {
    }

    public function findCapacity(RoomId $roomId): int
    {
        $capacity = $this->roomConnection->fetchOne(
            'SELECT rt.guest_capacity
               FROM room r
               JOIN room_type rt ON rt.id = r.room_type_id
              WHERE r.id = :roomId',
            ['roomId' => $roomId->value],
        );

        if (false === $capacity) {
            return 0;
        }

        /** @var int|string $capacity */
        return (int) $capacity;
    }
}

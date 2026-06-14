<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Contract;

use App\Room\Application\Contract\RoomsByTypeFinderInterface;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use Doctrine\DBAL\Connection;

final readonly class DoctrineRoomsByTypeFinder implements RoomsByTypeFinderInterface
{
    public function __construct(private Connection $roomConnection)
    {
    }

    public function findByType(RoomTypeId $roomTypeId): array
    {
        /** @var list<array{id: string}> $rows */
        $rows = $this->roomConnection->fetchAllAssociative(
            'SELECT id FROM rooms WHERE room_type_id = :roomTypeId',
            ['roomTypeId' => $roomTypeId->value],
        );

        return array_map(
            static fn(array $row) => new RoomId($row['id']),
            $rows,
        );
    }
}

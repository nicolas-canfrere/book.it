<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Port\RoomTypeExistsInterface;
use Doctrine\DBAL\Connection;

final class RoomTypeExistenceChecker implements RoomTypeExistsInterface
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function __construct(private readonly Connection $roomConnection)
    {
    }

    public function exists(string $roomTypeId): bool
    {
        if (!array_key_exists($roomTypeId, $this->cache)) {
            $count = $this->roomConnection->fetchOne(
                'SELECT COUNT(*) FROM room_type WHERE id = :id',
                ['id' => $roomTypeId],
            );
            $this->cache[$roomTypeId] = $count > 0;
        }

        return $this->cache[$roomTypeId];
    }
}

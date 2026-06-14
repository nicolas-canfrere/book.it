<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Port\RoomTypeExistsInterface;
use App\Shared\Domain\ValueObject\RoomTypeId;
use Doctrine\DBAL\Connection;

final class RoomTypeExistenceChecker implements RoomTypeExistsInterface
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function __construct(private readonly Connection $roomConnection)
    {
    }

    public function exists(RoomTypeId $roomTypeId): bool
    {
        if (!array_key_exists($roomTypeId->value, $this->cache)) {
            $count = $this->roomConnection->fetchOne(
                'SELECT COUNT(*) FROM room_type WHERE id = :id',
                ['id' => $roomTypeId->value],
            );
            $this->cache[$roomTypeId->value] = $count > 0;
        }

        return $this->cache[$roomTypeId->value];
    }
}

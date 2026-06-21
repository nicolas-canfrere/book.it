<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Port;

use App\Pricing\Domain\Model\BaseRate;
use App\Shared\Domain\ValueObject\RoomId;

interface BaseRateRepositoryInterface
{
    public function save(BaseRate $baseRate): void;

    public function findByRoomId(RoomId $roomId): ?BaseRate;

    /**
     * @param list<RoomId> $roomIds
     *
     * @return array<string, BaseRate>
     */
    public function findByRoomIds(array $roomIds): array;
}

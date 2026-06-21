<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Infrastructure\Persistence\InMemory;

use App\Pricing\Domain\Model\BaseRate;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;
use App\Shared\Domain\ValueObject\RoomId;

final class InMemoryBaseRateRepository implements BaseRateRepositoryInterface
{
    /** @var array<string, BaseRate> */
    private array $rates = [];

    public function save(BaseRate $baseRate): void
    {
        $this->rates[$baseRate->roomId->value] = $baseRate;
    }

    public function findByRoomId(RoomId $roomId): ?BaseRate
    {
        return $this->rates[$roomId->value] ?? null;
    }

    public function findByRoomIds(array $roomIds): array
    {
        $result = [];
        foreach ($roomIds as $roomId) {
            if (isset($this->rates[$roomId->value])) {
                $result[$roomId->value] = $this->rates[$roomId->value];
            }
        }

        return $result;
    }
}

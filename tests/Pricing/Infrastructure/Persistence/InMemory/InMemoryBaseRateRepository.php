<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Infrastructure\Persistence\InMemory;

use App\Pricing\Domain\Model\BaseRate;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;

final class InMemoryBaseRateRepository implements BaseRateRepositoryInterface
{
    /** @var array<string, BaseRate> */
    private array $rates = [];

    public function save(BaseRate $baseRate): void
    {
        $this->rates[$baseRate->roomId] = $baseRate;
    }

    public function findByRoomId(string $roomId): ?BaseRate
    {
        return $this->rates[$roomId] ?? null;
    }
}

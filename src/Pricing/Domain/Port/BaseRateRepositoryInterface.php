<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Port;

use App\Pricing\Domain\Model\BaseRate;

interface BaseRateRepositoryInterface
{
    public function save(BaseRate $baseRate): void;

    public function findByRoomId(string $roomId): ?BaseRate;
}

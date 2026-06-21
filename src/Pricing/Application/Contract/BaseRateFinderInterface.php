<?php

declare(strict_types=1);

namespace App\Pricing\Application\Contract;

use App\Shared\Domain\ValueObject\RoomId;

interface BaseRateFinderInterface
{
    public function find(RoomId $roomId): ?BaseRateView;
}

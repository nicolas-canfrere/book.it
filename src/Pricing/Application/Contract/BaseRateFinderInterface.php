<?php

declare(strict_types=1);

namespace App\Pricing\Application\Contract;

interface BaseRateFinderInterface
{
    public function find(string $roomId): ?BaseRateView;
}

<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\BaseRateFinderInterface;
use App\Pricing\Application\Contract\BaseRateView;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;

final readonly class DoctrineBaseRateFinder implements BaseRateFinderInterface
{
    public function __construct(private BaseRateRepositoryInterface $baseRates)
    {
    }

    public function findByRoomIds(array $roomIds): array
    {
        $views = [];
        foreach ($this->baseRates->findByRoomIds($roomIds) as $roomId => $baseRate) {
            $views[$roomId] = new BaseRateView(amountCents: $baseRate->amountCents);
        }

        return $views;
    }
}

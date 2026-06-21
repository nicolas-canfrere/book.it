<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\BaseRateFinderInterface;
use App\Pricing\Application\Contract\BaseRateView;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class DoctrineBaseRateFinder implements BaseRateFinderInterface
{
    public function __construct(private BaseRateRepositoryInterface $baseRates)
    {
    }

    public function find(RoomId $roomId): ?BaseRateView
    {
        $baseRate = $this->baseRates->findByRoomId($roomId);

        if (null === $baseRate) {
            return null;
        }

        return new BaseRateView(amountCents: $baseRate->amountCents);
    }
}

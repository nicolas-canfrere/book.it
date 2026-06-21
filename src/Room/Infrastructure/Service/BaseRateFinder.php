<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Service;

use App\Pricing\Application\Contract\BaseRateFinderInterface;
use App\Room\Domain\Port\RoomBaseRateFinderInterface;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class BaseRateFinder implements RoomBaseRateFinderInterface
{
    public function __construct(private BaseRateFinderInterface $baseRateFinder)
    {
    }

    public function find(RoomId $roomId): ?int
    {
        return $this->baseRateFinder->find($roomId->value)?->amountCents;
    }
}

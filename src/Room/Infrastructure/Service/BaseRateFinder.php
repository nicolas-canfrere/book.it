<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Service;

use App\Pricing\Application\Contract\BaseRateFinderInterface;
use App\Room\Domain\Port\RoomBaseRateFinderInterface;

final readonly class BaseRateFinder implements RoomBaseRateFinderInterface
{
    public function __construct(private BaseRateFinderInterface $baseRateFinder)
    {
    }

    public function findByRoomIds(array $roomIds): array
    {
        $amountCentsByRoomId = [];
        foreach ($this->baseRateFinder->findByRoomIds($roomIds) as $roomId => $view) {
            $amountCentsByRoomId[$roomId] = $view->amountCents;
        }

        return $amountCentsByRoomId;
    }
}

<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetPromotions;

use App\Pricing\Domain\Model\Promotion;
use App\Shared\Application\Bus\SyncQueryInterface;
use App\Shared\Domain\ValueObject\RoomId;

/** @implements SyncQueryInterface<list<Promotion>> */
final readonly class GetPromotionsQuery implements SyncQueryInterface
{
    public function __construct(
        public RoomId $roomId,
    ) {
    }
}

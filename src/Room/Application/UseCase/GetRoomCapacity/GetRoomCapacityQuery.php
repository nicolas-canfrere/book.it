<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\GetRoomCapacity;

use App\Shared\Application\Bus\SyncQueryInterface;
use App\Shared\Domain\ValueObject\RoomId;

/**
 * @implements SyncQueryInterface<int>
 */
final readonly class GetRoomCapacityQuery implements SyncQueryInterface
{
    public function __construct(
        public RoomId $roomId,
    ) {
    }
}

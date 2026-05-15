<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\GetRoom;

use App\Room\Domain\Model\Room;
use App\Shared\Application\Bus\SyncQueryInterface;

/**
 * @implements SyncQueryInterface<Room|null>
 */
final readonly class GetRoomQuery implements SyncQueryInterface
{
    public function __construct(
        public string $roomId,
    ) {
    }
}

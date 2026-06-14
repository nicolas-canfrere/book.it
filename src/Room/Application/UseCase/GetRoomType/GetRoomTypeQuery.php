<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\GetRoomType;

use App\Room\Domain\Model\RoomType;
use App\Shared\Application\Bus\SyncQueryInterface;
use App\Shared\Domain\ValueObject\RoomTypeId;

/**
 * @implements SyncQueryInterface<RoomType|null>
 */
final readonly class GetRoomTypeQuery implements SyncQueryInterface
{
    public function __construct(public RoomTypeId $id)
    {
    }
}

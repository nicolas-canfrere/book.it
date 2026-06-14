<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\DeleteSearchRoomType;

use App\Shared\Application\Bus\AsyncCommandInterface;
use App\Shared\Domain\ValueObject\RoomTypeId;

final readonly class DeleteSearchRoomTypeCommand implements AsyncCommandInterface
{
    public function __construct(public RoomTypeId $roomTypeId)
    {
    }
}

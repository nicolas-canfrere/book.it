<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\DeleteRoomType;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class DeleteRoomTypeCommand implements SyncCommandInterface
{
    public function __construct(public string $id)
    {
    }
}

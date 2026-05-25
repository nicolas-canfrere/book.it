<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\GetRoomType;

use App\Shared\Application\Bus\SyncQueryInterface;

final readonly class GetRoomTypeQuery implements SyncQueryInterface
{
    public function __construct(public string $id) {}
}

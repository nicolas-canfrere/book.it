<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\ListRoomTypes;

use App\Shared\Application\Bus\SyncQueryInterface;

final readonly class ListRoomTypesQuery implements SyncQueryInterface
{
    public function __construct(
        public string $hotelId,
        public int $page,
        public int $limit,
    ) {}
}

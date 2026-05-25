<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\UpdateRoomType;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class UpdateRoomTypeCommand implements SyncCommandInterface
{
    /** @param list<array{type: string, count: int}> $bedEntries */
    public function __construct(
        public string $id,
        public string $name,
        public int $livingSpaceCount,
        public ?int $surfaceM2,
        public int $guestCapacity,
        public bool $isAccessible,
        public array $bedEntries,
    ) {
    }
}

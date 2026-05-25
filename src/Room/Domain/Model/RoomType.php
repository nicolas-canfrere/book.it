<?php

declare(strict_types=1);

namespace App\Room\Domain\Model;

use App\Room\Domain\ValueObject\BedComposition;

final readonly class RoomType
{
    public function __construct(
        public string $id,
        public string $hotelId,
        public string $name,
        public int $livingSpaceCount,
        public ?int $surfaceM2,
        public int $guestCapacity,
        public bool $isAccessible,
        public BedComposition $bedComposition,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

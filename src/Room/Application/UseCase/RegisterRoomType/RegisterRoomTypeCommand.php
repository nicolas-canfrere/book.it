<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\RegisterRoomType;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomTypeId;

final readonly class RegisterRoomTypeCommand implements SyncCommandInterface
{
    /** @param list<array{type: string, count: int}> $bedEntries */
    public function __construct(
        public RoomTypeId $id,
        public HotelId $hotelId,
        public string $name,
        public int $livingSpaceCount,
        public ?int $surfaceM2,
        public int $guestCapacity,
        public bool $isAccessible,
        public array $bedEntries,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

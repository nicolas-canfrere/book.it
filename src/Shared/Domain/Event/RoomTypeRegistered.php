<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class RoomTypeRegistered
{
    /** @param list<array{type: string, count: int}> $bedComposition */
    public function __construct(
        public string $roomTypeId,
        public string $hotelId,
        public string $name,
        public int $guestCapacity,
        public array $bedComposition,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

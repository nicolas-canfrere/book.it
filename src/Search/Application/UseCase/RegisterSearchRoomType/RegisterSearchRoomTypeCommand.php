<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RegisterSearchRoomType;

use App\Shared\Application\Bus\AsyncCommandInterface;

/** @param list<array{type: string, count: int}> $bedComposition */
final readonly class RegisterSearchRoomTypeCommand implements AsyncCommandInterface
{
    /** @param list<array{type: string, count: int}> $bedComposition */
    public function __construct(
        public string $roomTypeId,
        public string $hotelId,
        public string $name,
        public int $guestCapacity,
        public array $bedComposition,
    ) {
    }
}

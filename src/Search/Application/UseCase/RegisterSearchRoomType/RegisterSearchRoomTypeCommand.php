<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RegisterSearchRoomType;

use App\Shared\Application\Bus\AsyncCommandInterface;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomTypeId;

/** @param list<array{type: string, count: int}> $bedComposition */
final readonly class RegisterSearchRoomTypeCommand implements AsyncCommandInterface
{
    /** @param list<array{type: string, count: int}> $bedComposition */
    public function __construct(
        public RoomTypeId $roomTypeId,
        public HotelId $hotelId,
        public string $name,
        public int $guestCapacity,
        public array $bedComposition,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\BatchRegisterRooms;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class BatchRegisterRoomsCommand implements SyncCommandInterface
{
    /**
     * @param list<array{id: string, number: string, floor: int}> $entries
     */
    public function __construct(
        public string $hotelId,
        public string $roomTypeId,
        public array $entries,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

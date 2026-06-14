<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\BatchRegisterRooms;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;

final readonly class BatchRegisterRoomsCommand implements SyncCommandInterface
{
    /**
     * @param list<array{id: RoomId, number: string, floor: int, roomTypeId: RoomTypeId}> $entries
     */
    public function __construct(
        public string $hotelId,
        public array $entries,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

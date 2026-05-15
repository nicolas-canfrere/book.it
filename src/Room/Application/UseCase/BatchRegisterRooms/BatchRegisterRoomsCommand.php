<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\BatchRegisterRooms;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class BatchRegisterRoomsCommand implements SyncCommandInterface
{
    /**
     * @param list<array{id: string, number: string}> $entries
     */
    public function __construct(
        public string $hotelId,
        public array $entries,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

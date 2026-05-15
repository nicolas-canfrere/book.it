<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\RegisterRoom;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class RegisterRoomCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public string $hotelId,
        public string $number,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

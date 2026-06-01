<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RegisterSearchRoom;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class RegisterSearchRoomCommand implements AsyncCommandInterface
{
    public function __construct(
        public string $roomId,
        public string $hotelId,
        public string $roomTypeId,
    ) {
    }
}

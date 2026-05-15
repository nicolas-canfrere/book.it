<?php

declare(strict_types=1);

namespace App\Room\Domain\Model;

final readonly class Room
{
    public function __construct(
        public string $id,
        public string $hotelId,
        public string $number,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

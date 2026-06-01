<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

use App\Search\Domain\AvailableRoomType;

interface AvailableRoomTypeFinderInterface
{
    /** @return list<AvailableRoomType> */
    public function find(
        string $city,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
        int $guests,
    ): array;
}

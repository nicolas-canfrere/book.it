<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

interface AvailableRoomTypeFinderInterface
{
    /** @return list<array<string, mixed>> */
    public function find(
        string $city,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
        int $guests,
    ): array;
}

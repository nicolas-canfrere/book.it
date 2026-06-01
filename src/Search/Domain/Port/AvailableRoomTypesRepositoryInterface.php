<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

interface AvailableRoomTypesRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function findAvailable(string $city, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut, int $guests): array;
}

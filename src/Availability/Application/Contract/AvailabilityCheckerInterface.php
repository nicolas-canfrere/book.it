<?php

declare(strict_types=1);

namespace App\Availability\Application\Contract;

interface AvailabilityCheckerInterface
{
    public function isAvailable(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool;
}

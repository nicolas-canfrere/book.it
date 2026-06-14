<?php

declare(strict_types=1);

namespace App\Availability\Application\Contract;

use App\Shared\Domain\ValueObject\RoomId;

interface AvailabilityCheckerInterface
{
    public function isAvailable(RoomId $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool;
}

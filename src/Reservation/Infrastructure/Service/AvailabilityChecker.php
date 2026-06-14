<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Availability\Application\Contract\AvailabilityCheckerInterface;
use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class AvailabilityChecker implements RoomAvailabilityCheckerInterface
{
    public function __construct(private AvailabilityCheckerInterface $availabilityChecker)
    {
    }

    public function isAvailable(RoomId $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        return $this->availabilityChecker->isAvailable($roomId, $checkIn, $checkOut);
    }
}

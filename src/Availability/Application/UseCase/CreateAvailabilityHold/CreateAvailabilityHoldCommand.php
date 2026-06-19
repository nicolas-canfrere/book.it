<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\CreateAvailabilityHold;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\AvailabilityHoldId;
use App\Shared\Domain\ValueObject\ReservationId;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class CreateAvailabilityHoldCommand implements SyncCommandInterface
{
    public function __construct(
        public AvailabilityHoldId $id,
        public RoomId $roomId,
        public ReservationId $reservationId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

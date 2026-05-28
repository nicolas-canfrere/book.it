<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CreateReservation;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class CreateReservationCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public string $roomId,
        public string $bookerId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $guestCount,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

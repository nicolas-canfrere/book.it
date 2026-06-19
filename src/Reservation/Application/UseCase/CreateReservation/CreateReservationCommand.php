<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CreateReservation;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\BookerId;
use App\Shared\Domain\ValueObject\ReservationId;
use App\Shared\Domain\ValueObject\RoomTypeId;

final readonly class CreateReservationCommand implements SyncCommandInterface
{
    public function __construct(
        public ReservationId $id,
        public RoomTypeId $roomTypeId,
        public BookerId $bookerId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $guestCount,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

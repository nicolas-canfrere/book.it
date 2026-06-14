<?php

declare(strict_types=1);

namespace App\Reservation\Application\Service;

use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommand;
use App\Reservation\Domain\Port\ReservationIdGeneratorInterface;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class CreateReservationCommandFactory
{
    public function __construct(private ReservationIdGeneratorInterface $idGenerator)
    {
    }

    public function create(
        string $roomId,
        string $bookerId,
        string $checkIn,
        string $checkOut,
        int $guestCount,
    ): CreateReservationCommand {
        return new CreateReservationCommand(
            id: $this->idGenerator->generate(),
            roomId: new RoomId($roomId),
            bookerId: $bookerId,
            checkIn: new \DateTimeImmutable($checkIn),
            checkOut: new \DateTimeImmutable($checkOut),
            guestCount: $guestCount,
            createdAt: new \DateTimeImmutable(),
        );
    }
}

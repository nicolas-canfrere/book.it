<?php
declare(strict_types=1);

namespace App\Reservation\Application\Service;

use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommand;
use App\Reservation\Domain\Port\ReservationIdGeneratorInterface;

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
    ): CreateReservationCommand {
        return new CreateReservationCommand(
            id: $this->idGenerator->generate(),
            roomId: $roomId,
            bookerId: $bookerId,
            checkIn: new \DateTimeImmutable($checkIn),
            checkOut: new \DateTimeImmutable($checkOut),
            createdAt: new \DateTimeImmutable(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CheckIn;

use App\Reservation\Domain\Exception\ReservationNotFoundException;
use App\Reservation\Domain\Model\Guest;
use App\Reservation\Domain\Port\GuestIdGeneratorInterface;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\ValueObject\ReservationId;

final class CheckInCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservations,
        private readonly GuestIdGeneratorInterface $guestIdGenerator,
    ) {
    }

    public function __invoke(CheckInCommand $command): void
    {
        $reservation = $this->reservations->get(new ReservationId($command->reservationId));

        if (null === $reservation) {
            throw new ReservationNotFoundException($command->reservationId);
        }

        $guests = array_map(
            fn(array $data) => new Guest(
                id: $this->guestIdGenerator->generate(),
                firstName: $data['firstName'],
                lastName: $data['lastName'],
                dateOfBirth: \DateTimeImmutable::createFromFormat('Y-m-d', $data['dateOfBirth']) ?: throw new \InvalidArgumentException(sprintf('Invalid date of birth format: "%s". Expected Y-m-d.', $data['dateOfBirth'])),
            ),
            $command->guests,
        );

        $reservation->checkIn($guests, $command->today);

        $this->reservations->save($reservation);
    }
}

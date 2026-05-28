<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\PreRegisterGuests;

use App\Reservation\Domain\Exception\ReservationNotFoundException;
use App\Reservation\Domain\Model\Guest;
use App\Reservation\Domain\Port\GuestIdGeneratorInterface;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final class PreRegisterGuestsCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservations,
        private readonly GuestIdGeneratorInterface $guestIdGenerator,
    ) {
    }

    public function __invoke(PreRegisterGuestsCommand $command): void
    {
        $reservation = $this->reservations->get($command->reservationId);

        if (null === $reservation) {
            throw new ReservationNotFoundException($command->reservationId);
        }

        $guests = array_map(
            fn(array $data) => new Guest(
                id: $this->guestIdGenerator->generate(),
                firstName: $data['firstName'],
                lastName: $data['lastName'],
                dateOfBirth: new \DateTimeImmutable($data['dateOfBirth']),
            ),
            $command->guests,
        );

        $reservation->preRegisterGuests($guests, $command->today);

        $this->reservations->save($reservation);
    }
}

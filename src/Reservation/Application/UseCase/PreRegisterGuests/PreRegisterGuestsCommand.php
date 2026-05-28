<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\PreRegisterGuests;

final readonly class PreRegisterGuestsCommand
{
    /**
     * @param list<array{firstName: string, lastName: string, dateOfBirth: string}> $guests
     */
    public function __construct(
        public string $reservationId,
        public array $guests,
        public \DateTimeImmutable $today,
    ) {
    }
}

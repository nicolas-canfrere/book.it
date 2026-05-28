<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CheckIn;

final readonly class CheckInCommand
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

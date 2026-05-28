<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CheckIn;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class CheckInCommand implements SyncCommandInterface
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

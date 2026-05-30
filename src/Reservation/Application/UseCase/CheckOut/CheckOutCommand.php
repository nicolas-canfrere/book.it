<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CheckOut;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class CheckOutCommand implements SyncCommandInterface
{
    public function __construct(
        public string $reservationId,
        public \DateTimeImmutable $actualDepartureDate,
    ) {
    }
}

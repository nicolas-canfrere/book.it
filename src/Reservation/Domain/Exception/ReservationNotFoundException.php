<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

final class ReservationNotFoundException extends \RuntimeException
{
    public function __construct(string $reservationId)
    {
        parent::__construct(sprintf('Reservation "%s" not found.', $reservationId));
    }
}

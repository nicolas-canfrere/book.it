<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

use App\Reservation\Domain\Model\ReservationStatus;

final class GuestPreRegistrationNotAllowedException extends \DomainException
{
    public function __construct(ReservationStatus $status)
    {
        parent::__construct(sprintf(
            'Cannot pre-register guests on a reservation with status "%s".',
            $status->value,
        ));
    }
}

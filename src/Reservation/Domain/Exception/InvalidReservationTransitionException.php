<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

use App\Reservation\Domain\Model\ReservationStatus;

final class InvalidReservationTransitionException extends \DomainException
{
    public function __construct(ReservationStatus $from, ReservationStatus $to)
    {
        parent::__construct(sprintf(
            'Cannot transition reservation from "%s" to "%s".',
            $from->value,
            $to->value,
        ));
    }
}

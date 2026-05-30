<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

use App\Reservation\Domain\Model\ReservationStatus;

final class CheckOutNotAllowedException extends \DomainException
{
    public static function wrongStatus(ReservationStatus $current): self
    {
        return new self(sprintf(
            'Check-out is not allowed: reservation status is "%s", expected "checked_in".',
            $current->value,
        ));
    }

    public static function afterCheckOutDate(\DateTimeImmutable $checkOutDate, \DateTimeImmutable $actualDeparture): self
    {
        return new self(sprintf(
            'Check-out is not allowed: actual departure date "%s" is after the planned check-out date "%s".',
            $actualDeparture->format('Y-m-d'),
            $checkOutDate->format('Y-m-d'),
        ));
    }

    public static function beforeCheckInDate(\DateTimeImmutable $checkInDate, \DateTimeImmutable $actualDeparture): self
    {
        return new self(sprintf(
            'Check-out is not allowed: actual departure date "%s" is before the check-in date "%s".',
            $actualDeparture->format('Y-m-d'),
            $checkInDate->format('Y-m-d'),
        ));
    }
}

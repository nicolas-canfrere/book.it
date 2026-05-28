<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

use App\Reservation\Domain\Model\ReservationStatus;

final class CheckInNotAllowedException extends \DomainException
{
    public static function wrongStatus(ReservationStatus $status): self
    {
        return new self(sprintf(
            'Check-in is not allowed on a reservation with status "%s".',
            $status->value,
        ));
    }

    public static function tooEarly(\DateTimeImmutable $checkInDate, \DateTimeImmutable $today): self
    {
        return new self(sprintf(
            'Check-in is not allowed before the check-in date %s (today: %s).',
            $checkInDate->format('Y-m-d'),
            $today->format('Y-m-d'),
        ));
    }
}

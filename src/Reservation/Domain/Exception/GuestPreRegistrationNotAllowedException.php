<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

use App\Reservation\Domain\Model\ReservationStatus;

final class GuestPreRegistrationNotAllowedException extends \DomainException
{
    public static function dueToStatus(ReservationStatus $status): self
    {
        return new self(sprintf(
            'Cannot pre-register guests on a reservation with status "%s".',
            $status->value,
        ));
    }

    public static function dueToDate(\DateTimeImmutable $today, \DateTimeImmutable $checkInDate): self
    {
        return new self(sprintf(
            'Cannot pre-register guests on or after the check-in date %s (today: %s).',
            $checkInDate->format('Y-m-d'),
            $today->format('Y-m-d'),
        ));
    }
}

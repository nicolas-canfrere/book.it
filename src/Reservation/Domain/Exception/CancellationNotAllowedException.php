<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

final class CancellationNotAllowedException extends \DomainException
{
    public static function afterCheckIn(\DateTimeImmutable $checkIn, \DateTimeImmutable $today): self
    {
        return new self(sprintf(
            'Cancellation is not allowed on or after the check-in date (%s). Today is %s.',
            $checkIn->format('Y-m-d'),
            $today->format('Y-m-d'),
        ));
    }
}

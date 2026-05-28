<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

final class GuestCapacityExceededException extends \DomainException
{
    public function __construct(int $guestCount, int $capacity)
    {
        parent::__construct(
            sprintf('Guest count %d exceeds room capacity of %d.', $guestCount, $capacity)
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Reservation\Domain\Exception\RoomNotBookableException;

interface PriceCalculatorInterface
{
    /**
     * Returns total price in cents.
     *
     * @throws RoomNotBookableException when no base rate is configured
     */
    public function calculate(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): int;
}

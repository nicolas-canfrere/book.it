<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

interface PriceCalculatorInterface
{
    /**
     * Returns total price in cents.
     *
     * @throws \App\Reservation\Domain\Exception\RoomNotBookableException when no base rate is configured
     */
    public function calculate(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): int;
}

<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Port\PriceCalculatorInterface;

final class FakePriceCalculator implements PriceCalculatorInterface
{
    private ?int $price = 42000;

    public function setPrice(?int $price): void
    {
        $this->price = $price;
    }

    public function calculate(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): int
    {
        if (null === $this->price) {
            throw new RoomNotBookableException($roomId);
        }

        return $this->price;
    }
}

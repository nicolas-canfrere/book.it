<?php

declare(strict_types=1);

namespace App\Availability\Domain\ValueObject;

final readonly class DatePeriod
{
    public function __construct(
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
        if ($checkIn >= $checkOut) {
            throw new \InvalidArgumentException('Check-in must be strictly before check-out.');
        }
    }
}

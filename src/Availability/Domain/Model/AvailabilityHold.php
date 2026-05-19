<?php

declare(strict_types=1);

namespace App\Availability\Domain\Model;

use App\Availability\Domain\ValueObject\DatePeriod;

final readonly class AvailabilityHold
{
    public function __construct(
        public string $id,
        public string $roomId,
        public string $reservationId,
        public DatePeriod $period,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

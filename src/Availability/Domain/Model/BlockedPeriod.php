<?php

declare(strict_types=1);

namespace App\Availability\Domain\Model;

use App\Availability\Domain\ValueObject\DatePeriod;

final readonly class BlockedPeriod
{
    public function __construct(
        public string $id,
        public string $roomId,
        public DatePeriod $period,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}

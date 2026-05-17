<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\UpdateRatePeriod;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class UpdateRatePeriodCommand implements SyncCommandInterface
{
    public function __construct(
        public string $ratePeriodId,
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $amountCents,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}

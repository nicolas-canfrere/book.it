<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RemoveSearchUnavailablePeriodByPeriod;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class RemoveSearchUnavailablePeriodByPeriodCommand implements AsyncCommandInterface
{
    public function __construct(
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}

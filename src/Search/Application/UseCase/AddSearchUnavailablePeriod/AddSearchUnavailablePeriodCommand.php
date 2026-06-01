<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\AddSearchUnavailablePeriod;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class AddSearchUnavailablePeriodCommand implements AsyncCommandInterface
{
    public function __construct(
        public string $sourceId,
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\AddSearchUnavailablePeriod;

use App\Shared\Application\Bus\AsyncCommandInterface;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class AddSearchUnavailablePeriodCommand implements AsyncCommandInterface
{
    public function __construct(
        public string $sourceId,
        public RoomId $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}

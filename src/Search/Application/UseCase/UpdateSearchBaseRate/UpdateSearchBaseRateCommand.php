<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchBaseRate;

use App\Shared\Application\Bus\AsyncCommandInterface;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class UpdateSearchBaseRateCommand implements AsyncCommandInterface
{
    public function __construct(
        public RoomId $roomId,
        public int $amountCents,
    ) {
    }
}

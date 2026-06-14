<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Pricing\Application\UseCase\SetBaseRate\SetBaseRateCommand;
use App\Pricing\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\RoomId;
use Psr\Clock\ClockInterface;

final readonly class SetBaseRateCommandFactory
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function create(string $roomId, float $amount): SetBaseRateCommand
    {
        return new SetBaseRateCommand(
            roomId: new RoomId($roomId),
            amountCents: Money::fromEuros($amount)->amountCents,
            updatedAt: $this->clock->now(),
        );
    }
}

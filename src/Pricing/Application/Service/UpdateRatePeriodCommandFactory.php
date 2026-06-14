<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Pricing\Application\UseCase\UpdateRatePeriod\UpdateRatePeriodCommand;
use App\Pricing\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\RoomId;
use Psr\Clock\ClockInterface;

final readonly class UpdateRatePeriodCommandFactory
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function create(string $ratePeriodId, string $roomId, string $checkIn, string $checkOut, float $amount): UpdateRatePeriodCommand
    {
        return new UpdateRatePeriodCommand(
            ratePeriodId: $ratePeriodId,
            roomId: new RoomId($roomId),
            checkIn: new \DateTimeImmutable($checkIn),
            checkOut: new \DateTimeImmutable($checkOut),
            amountCents: Money::fromEuros($amount)->amountCents,
            updatedAt: $this->clock->now(),
        );
    }
}

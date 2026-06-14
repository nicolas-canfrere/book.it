<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Pricing\Application\UseCase\CreateRatePeriod\CreateRatePeriodCommand;
use App\Pricing\Domain\Port\RatePeriodIdGeneratorInterface;
use App\Pricing\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\RoomId;
use Psr\Clock\ClockInterface;

final readonly class CreateRatePeriodCommandFactory
{
    public function __construct(
        private RatePeriodIdGeneratorInterface $idGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(string $roomId, string $checkIn, string $checkOut, float $amount): CreateRatePeriodCommand
    {
        $now = $this->clock->now();

        return new CreateRatePeriodCommand(
            id: $this->idGenerator->generate(),
            roomId: new RoomId($roomId),
            checkIn: new \DateTimeImmutable($checkIn),
            checkOut: new \DateTimeImmutable($checkOut),
            amountCents: Money::fromEuros($amount)->amountCents,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}

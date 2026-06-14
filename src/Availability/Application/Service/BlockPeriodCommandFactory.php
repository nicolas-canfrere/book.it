<?php

declare(strict_types=1);

namespace App\Availability\Application\Service;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Domain\Port\BlockedPeriodIdGeneratorInterface;
use App\Shared\Domain\ValueObject\RoomId;
use Psr\Clock\ClockInterface;

final readonly class BlockPeriodCommandFactory
{
    public function __construct(
        private BlockedPeriodIdGeneratorInterface $idGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(string $roomId, string $checkIn, string $checkOut): BlockPeriodCommand
    {
        return new BlockPeriodCommand(
            id: $this->idGenerator->generate(),
            roomId: new RoomId($roomId),
            checkIn: new \DateTimeImmutable($checkIn),
            checkOut: new \DateTimeImmutable($checkOut),
            createdAt: $this->clock->now(),
        );
    }
}

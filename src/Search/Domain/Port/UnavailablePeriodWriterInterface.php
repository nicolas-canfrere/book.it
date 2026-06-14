<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

use App\Shared\Domain\ValueObject\RoomId;

interface UnavailablePeriodWriterInterface
{
    public function add(
        string $sourceId,
        RoomId $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void;

    public function removeByPeriod(
        RoomId $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void;

    public function removeBySource(string $sourceId): void;
}

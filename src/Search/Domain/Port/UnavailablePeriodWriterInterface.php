<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

interface UnavailablePeriodWriterInterface
{
    public function add(
        string $sourceId,
        string $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void;

    public function removeByPeriod(
        string $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void;

    public function removeBySource(string $sourceId): void;
}

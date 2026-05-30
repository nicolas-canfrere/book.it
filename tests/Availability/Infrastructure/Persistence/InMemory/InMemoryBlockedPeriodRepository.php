<?php

declare(strict_types=1);

namespace App\Tests\Availability\Infrastructure\Persistence\InMemory;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;

final class InMemoryBlockedPeriodRepository implements BlockedPeriodRepositoryInterface
{
    /** @var array<string, BlockedPeriod> */
    private array $periods = [];

    public function add(BlockedPeriod $period): void
    {
        $this->periods[$period->id] = $period;
    }

    public function get(string $id): ?BlockedPeriod
    {
        return $this->periods[$id] ?? null;
    }

    public function remove(string $id): void
    {
        unset($this->periods[$id]);
    }

    public function hasOverlap(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        foreach ($this->periods as $period) {
            if ($period->roomId !== $roomId) {
                continue;
            }
            if ($checkIn < $period->period->checkOut && $checkOut > $period->period->checkIn) {
                return true;
            }
        }

        return false;
    }

    public function removeByRoomAndPeriod(
        string $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void {
        foreach ($this->periods as $key => $period) {
            if ($period->roomId === $roomId
                && $period->period->checkIn == $checkIn
                && $period->period->checkOut == $checkOut
            ) {
                unset($this->periods[$key]);

                return;
            }
        }
    }

    /** @return list<BlockedPeriod> */
    public function listByRoomId(string $roomId): array
    {
        $filtered = array_values(array_filter(
            $this->periods,
            static fn(BlockedPeriod $p) => $p->roomId === $roomId,
        ));

        usort($filtered, static fn(BlockedPeriod $a, BlockedPeriod $b) => $a->period->checkIn <=> $b->period->checkIn);

        return $filtered;
    }
}

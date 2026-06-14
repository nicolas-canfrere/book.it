<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Infrastructure\Persistence\InMemory;

use App\Pricing\Domain\Model\RatePeriod;
use App\Pricing\Domain\Port\RatePeriodRepositoryInterface;
use App\Pricing\Domain\ValueObject\DatePeriod;
use App\Shared\Domain\ValueObject\RoomId;

final class InMemoryRatePeriodRepository implements RatePeriodRepositoryInterface
{
    /** @var array<string, RatePeriod> */
    private array $periods = [];

    public function save(RatePeriod $ratePeriod): void
    {
        $this->periods[$ratePeriod->id] = $ratePeriod;
    }

    public function findById(string $id): ?RatePeriod
    {
        return $this->periods[$id] ?? null;
    }

    /** @return list<RatePeriod> */
    public function findByRoomId(RoomId $roomId): array
    {
        $filtered = array_values(array_filter(
            $this->periods,
            static fn(RatePeriod $rp) => $rp->roomId->value === $roomId->value,
        ));

        usort($filtered, static fn(RatePeriod $a, RatePeriod $b) => $a->checkIn <=> $b->checkIn);

        return $filtered;
    }

    /** @return list<RatePeriod> */
    public function findOverlappingByRoomId(RoomId $roomId, DatePeriod $period): array
    {
        $filtered = array_values(array_filter(
            $this->periods,
            static fn(RatePeriod $rp) => $rp->roomId->value === $roomId->value
                && $period->overlaps(new DatePeriod($rp->checkIn, $rp->checkOut)),
        ));

        usort($filtered, static fn(RatePeriod $a, RatePeriod $b) => $a->checkIn <=> $b->checkIn);

        return $filtered;
    }

    public function hasOverlap(RoomId $roomId, DatePeriod $period, ?string $excludeId = null): bool
    {
        foreach ($this->periods as $rp) {
            if ($rp->roomId->value !== $roomId->value) {
                continue;
            }
            if (null !== $excludeId && $rp->id === $excludeId) {
                continue;
            }
            if ($period->overlaps(new DatePeriod($rp->checkIn, $rp->checkOut))) {
                return true;
            }
        }

        return false;
    }

    public function delete(RatePeriod $ratePeriod): void
    {
        unset($this->periods[$ratePeriod->id]);
    }
}

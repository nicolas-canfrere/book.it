<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Infrastructure\Persistence\InMemory;

use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\DatePeriod;
use App\Shared\Domain\ValueObject\RoomId;

final class InMemoryPromotionRepository implements PromotionRepositoryInterface
{
    /** @var array<string, Promotion> */
    private array $promotions = [];

    public function save(Promotion $promotion): void
    {
        $this->promotions[$promotion->id] = $promotion;
    }

    public function findById(string $id): ?Promotion
    {
        return $this->promotions[$id] ?? null;
    }

    /** @return list<Promotion> */
    public function findByRoomId(RoomId $roomId): array
    {
        $results = array_values(array_filter(
            $this->promotions,
            static fn(Promotion $p) => $p->roomId->value === $roomId->value,
        ));

        usort($results, static fn(Promotion $a, Promotion $b) => $a->getCheckIn() <=> $b->getCheckIn());

        return $results;
    }

    /** @return list<Promotion> */
    public function findOverlappingByRoomId(RoomId $roomId, DatePeriod $period): array
    {
        $filtered = array_values(array_filter(
            $this->promotions,
            static fn(Promotion $p) => $p->roomId->value === $roomId->value
                && $period->overlaps(new DatePeriod($p->getCheckIn(), $p->getCheckOut())),
        ));

        usort($filtered, static fn(Promotion $a, Promotion $b) => $a->getCheckIn() <=> $b->getCheckIn());

        return $filtered;
    }

    public function hasOverlap(RoomId $roomId, DatePeriod $period, ?string $excludeId = null): bool
    {
        foreach ($this->promotions as $promotion) {
            if ($promotion->roomId->value !== $roomId->value) {
                continue;
            }
            if (null !== $excludeId && $promotion->id === $excludeId) {
                continue;
            }
            if ($period->overlaps(new DatePeriod($promotion->getCheckIn(), $promotion->getCheckOut()))) {
                return true;
            }
        }

        return false;
    }

    public function delete(Promotion $promotion): void
    {
        unset($this->promotions[$promotion->id]);
    }
}

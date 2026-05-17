<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Infrastructure\Persistence\InMemory;

use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\DatePeriod;

final class InMemoryPromotionRepository implements PromotionRepositoryInterface
{
    /** @var array<string, Promotion> */
    private array $store = [];

    public function save(Promotion $promotion): void
    {
        $this->store[$promotion->id] = $promotion;
    }

    public function findById(string $id): ?Promotion
    {
        return $this->store[$id] ?? null;
    }

    /** @return list<Promotion> */
    public function findByRoomId(string $roomId): array
    {
        $results = array_values(array_filter(
            $this->store,
            static fn(Promotion $p) => $p->roomId === $roomId,
        ));

        usort($results, static fn(Promotion $a, Promotion $b) => $a->getCheckIn() <=> $b->getCheckIn());

        return $results;
    }

    public function hasOverlap(string $roomId, DatePeriod $period, ?string $excludeId = null): bool
    {
        foreach ($this->store as $promotion) {
            if ($promotion->roomId !== $roomId) {
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
        unset($this->store[$promotion->id]);
    }
}

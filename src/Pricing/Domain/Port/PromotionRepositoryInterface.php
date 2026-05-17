<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Port;

use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\ValueObject\DatePeriod;

interface PromotionRepositoryInterface
{
    public function save(Promotion $promotion): void;

    public function findById(string $id): ?Promotion;

    /** @return list<Promotion> */
    public function findByRoomId(string $roomId): array;

    /** @return list<Promotion> */
    public function findOverlappingByRoomId(string $roomId, DatePeriod $period): array;

    public function hasOverlap(string $roomId, DatePeriod $period, ?string $excludeId = null): bool;

    public function delete(Promotion $promotion): void;
}

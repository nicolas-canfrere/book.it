<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Port;

use App\Pricing\Domain\Model\RatePeriod;
use App\Pricing\Domain\ValueObject\DatePeriod;

interface RatePeriodRepositoryInterface
{
    public function save(RatePeriod $ratePeriod): void;

    public function findById(string $id): ?RatePeriod;

    /** @return list<RatePeriod> */
    public function findByRoomId(string $roomId): array;

    public function hasOverlap(string $roomId, DatePeriod $period, ?string $excludeId = null): bool;

    public function delete(RatePeriod $ratePeriod): void;
}

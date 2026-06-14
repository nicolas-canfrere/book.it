<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Port;

use App\Pricing\Domain\Model\RatePeriod;
use App\Pricing\Domain\ValueObject\DatePeriod;
use App\Shared\Domain\ValueObject\RoomId;

interface RatePeriodRepositoryInterface
{
    public function save(RatePeriod $ratePeriod): void;

    public function findById(string $id): ?RatePeriod;

    /** @return list<RatePeriod> */
    public function findByRoomId(RoomId $roomId): array;

    /** @return list<RatePeriod> */
    public function findOverlappingByRoomId(RoomId $roomId, DatePeriod $period): array;

    public function hasOverlap(RoomId $roomId, DatePeriod $period, ?string $excludeId = null): bool;

    public function delete(RatePeriod $ratePeriod): void;
}

<?php

declare(strict_types=1);

namespace App\Tests\Availability\Infrastructure\Persistence\InMemory;

use App\Availability\Domain\Model\AvailabilityHold;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;

final class InMemoryAvailabilityHoldRepository implements AvailabilityHoldRepositoryInterface
{
    /** @var array<string, AvailabilityHold> */
    private array $holds = [];

    public function add(AvailabilityHold $hold): void
    {
        $this->holds[$hold->id->value] = $hold;
    }

    public function deleteByReservationId(string $reservationId): void
    {
        foreach ($this->holds as $id => $hold) {
            if ($hold->reservationId === $reservationId) {
                unset($this->holds[$id]);
            }
        }
    }

    public function hasActiveOverlap(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        $now = new \DateTimeImmutable();

        foreach ($this->holds as $hold) {
            if ($hold->roomId !== $roomId) {
                continue;
            }
            if ($hold->expiresAt <= $now) {
                continue;
            }
            if ($checkIn < $hold->period->checkOut && $checkOut > $hold->period->checkIn) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Availability\Infrastructure\Contract;

use App\Availability\Application\Contract\AvailabilityCheckerInterface;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Availability\Infrastructure\Contract\DoctrineAvailabilityChecker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineAvailabilityCheckerTest extends TestCase
{
    private BlockedPeriodRepositoryInterface&Stub $blockedPeriods;
    private AvailabilityHoldRepositoryInterface&Stub $holds;
    private AvailabilityCheckerInterface $checker;

    private \DateTimeImmutable $checkIn;
    private \DateTimeImmutable $checkOut;

    protected function setUp(): void
    {
        $this->blockedPeriods = $this->createStub(BlockedPeriodRepositoryInterface::class);
        $this->holds = $this->createStub(AvailabilityHoldRepositoryInterface::class);
        $this->checker = new DoctrineAvailabilityChecker($this->blockedPeriods, $this->holds);
        $this->checkIn = new \DateTimeImmutable('2026-07-01');
        $this->checkOut = new \DateTimeImmutable('2026-07-05');
    }

    #[Test]
    public function itReturnsFalseWhenBlockedPeriodOverlaps(): void
    {
        $this->blockedPeriods->method('hasOverlap')->willReturn(true);

        self::assertFalse($this->checker->isAvailable('room-1', $this->checkIn, $this->checkOut));
    }

    #[Test]
    public function itReturnsFalseWhenActiveHoldOverlaps(): void
    {
        $this->blockedPeriods->method('hasOverlap')->willReturn(false);
        $this->holds->method('hasActiveOverlap')->willReturn(true);

        self::assertFalse($this->checker->isAvailable('room-1', $this->checkIn, $this->checkOut));
    }

    #[Test]
    public function itReturnsTrueWhenNoOverlap(): void
    {
        $this->blockedPeriods->method('hasOverlap')->willReturn(false);
        $this->holds->method('hasActiveOverlap')->willReturn(false);

        self::assertTrue($this->checker->isAvailable('room-1', $this->checkIn, $this->checkOut));
    }
}

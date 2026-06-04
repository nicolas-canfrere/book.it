<?php

declare(strict_types=1);

namespace Tests\Reservation\Infrastructure\Service;

use App\Availability\Application\Contract\AvailabilityCheckerInterface;
use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;
use App\Reservation\Infrastructure\Service\AvailabilityChecker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AvailabilityCheckerTest extends TestCase
{
    private AvailabilityCheckerInterface&Stub $availabilityChecker;
    private RoomAvailabilityCheckerInterface $checker;

    protected function setUp(): void
    {
        $this->availabilityChecker = $this->createStub(AvailabilityCheckerInterface::class);
        $this->checker = new AvailabilityChecker($this->availabilityChecker);
    }

    #[Test]
    public function itReturnsTrueWhenAvailable(): void
    {
        $this->availabilityChecker->method('isAvailable')->willReturn(true);
        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        self::assertTrue($this->checker->isAvailable('room-1', $checkIn, $checkOut));
    }

    #[Test]
    public function itReturnsFalseWhenNotAvailable(): void
    {
        $this->availabilityChecker->method('isAvailable')->willReturn(false);
        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        self::assertFalse($this->checker->isAvailable('room-1', $checkIn, $checkOut));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\CheckAvailability;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommandHandler;
use App\Availability\Application\UseCase\CheckAvailability\CheckAvailabilityQuery;
use App\Availability\Application\UseCase\CheckAvailability\CheckAvailabilityQueryHandler;
use App\Availability\Domain\Model\AvailabilityHold;
use App\Availability\Domain\ValueObject\DatePeriod;
use App\Tests\Availability\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Availability\Infrastructure\Persistence\InMemory\InMemoryAvailabilityHoldRepository;
use App\Tests\Availability\Infrastructure\Persistence\InMemory\InMemoryBlockedPeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class CheckAvailabilityQueryHandlerTest extends TestCase
{
    private const string ROOM_ID = '550e8400-e29b-41d4-a716-446655440000';

    private InMemoryBlockedPeriodRepository $blockedPeriodRepository;
    private InMemoryAvailabilityHoldRepository $holdRepository;
    private CheckAvailabilityQueryHandler $handler;

    protected function setUp(): void
    {
        $this->blockedPeriodRepository = new InMemoryBlockedPeriodRepository();
        $this->holdRepository = new InMemoryAvailabilityHoldRepository();
        $this->handler = new CheckAvailabilityQueryHandler($this->blockedPeriodRepository, $this->holdRepository);

        $blockHandler = new BlockPeriodCommandHandler($this->blockedPeriodRepository, new FakeRoomExistenceChecker(), $this->createStub(EventDispatcherInterface::class));
        ($blockHandler)(new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itReturnsTrueWhenNoOverlap(): void
    {
        $result = ($this->handler)(new CheckAvailabilityQuery(
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-06-15'),
            checkOut: new \DateTimeImmutable('2025-06-18'),
        ));

        self::assertTrue($result);
    }

    #[Test]
    public function itReturnsFalseWhenOverlap(): void
    {
        $result = ($this->handler)(new CheckAvailabilityQuery(
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-06-12'),
            checkOut: new \DateTimeImmutable('2025-06-17'),
        ));

        self::assertFalse($result);
    }

    #[Test]
    public function itReturnsTrueForDifferentRoom(): void
    {
        $result = ($this->handler)(new CheckAvailabilityQuery(
            roomId: '550e8400-e29b-41d4-a716-446655440099',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
        ));

        self::assertTrue($result);
    }

    #[Test]
    public function itReturnsFalseWhenActiveHoldOverlaps(): void
    {
        $this->holdRepository->add(new AvailabilityHold(
            id: 'hold-1',
            roomId: self::ROOM_ID,
            reservationId: 'res-1',
            period: new DatePeriod(
                new \DateTimeImmutable('2025-07-01'),
                new \DateTimeImmutable('2025-07-05'),
            ),
            expiresAt: new \DateTimeImmutable('+15 minutes'),
            createdAt: new \DateTimeImmutable(),
        ));

        $result = ($this->handler)(new CheckAvailabilityQuery(
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-07-03'),
            checkOut: new \DateTimeImmutable('2025-07-08'),
        ));

        self::assertFalse($result);
    }

    #[Test]
    public function itReturnsTrueWhenHoldIsExpired(): void
    {
        $this->holdRepository->add(new AvailabilityHold(
            id: 'hold-2',
            roomId: self::ROOM_ID,
            reservationId: 'res-2',
            period: new DatePeriod(
                new \DateTimeImmutable('2025-08-01'),
                new \DateTimeImmutable('2025-08-05'),
            ),
            expiresAt: new \DateTimeImmutable('-1 second'),
            createdAt: new \DateTimeImmutable('-20 minutes'),
        ));

        $result = ($this->handler)(new CheckAvailabilityQuery(
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-08-01'),
            checkOut: new \DateTimeImmutable('2025-08-05'),
        ));

        self::assertTrue($result);
    }
}

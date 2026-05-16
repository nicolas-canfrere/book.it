<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\CheckAvailability;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommandHandler;
use App\Availability\Application\UseCase\CheckAvailability\CheckAvailabilityQuery;
use App\Availability\Application\UseCase\CheckAvailability\CheckAvailabilityQueryHandler;
use App\Tests\Availability\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Availability\Infrastructure\Persistence\InMemory\InMemoryBlockedPeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CheckAvailabilityQueryHandlerTest extends TestCase
{
    private const string ROOM_ID = '550e8400-e29b-41d4-a716-446655440000';

    private InMemoryBlockedPeriodRepository $repository;
    private CheckAvailabilityQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryBlockedPeriodRepository();
        $this->handler = new CheckAvailabilityQueryHandler($this->repository);

        $blockHandler = new BlockPeriodCommandHandler($this->repository, new FakeRoomExistenceChecker());
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
}

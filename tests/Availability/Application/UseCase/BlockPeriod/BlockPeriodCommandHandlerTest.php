<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\BlockPeriod;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommandHandler;
use App\Availability\Domain\Exception\BlockedPeriodOverlapException;
use App\Availability\Domain\Exception\RoomNotFoundException;
use App\Tests\Availability\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Availability\Infrastructure\Persistence\InMemory\InMemoryBlockedPeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BlockPeriodCommandHandlerTest extends TestCase
{
    private InMemoryBlockedPeriodRepository $repository;
    private FakeRoomExistenceChecker $roomExists;
    private BlockPeriodCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryBlockedPeriodRepository();
        $this->roomExists = new FakeRoomExistenceChecker();
        $this->handler = new BlockPeriodCommandHandler($this->repository, $this->roomExists);
    }

    #[Test]
    public function itPersistsTheBlockedPeriod(): void
    {
        $command = new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        );

        ($this->handler)($command);

        $period = $this->repository->get($command->id);
        self::assertNotNull($period);
        self::assertSame($command->id, $period->id);
        self::assertSame($command->roomId, $period->roomId);
        self::assertSame('2025-06-10', $period->period->checkIn->format('Y-m-d'));
        self::assertSame('2025-06-13', $period->period->checkOut->format('Y-m-d'));
    }

    #[Test]
    public function itThrowsWhenRoomDoesNotExist(): void
    {
        $this->roomExists->setExists(false);
        $this->expectException(RoomNotFoundException::class);

        ($this->handler)(new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itThrowsWhenPeriodOverlapsExistingBlock(): void
    {
        ($this->handler)(new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            createdAt: new \DateTimeImmutable(),
        ));

        $this->expectException(BlockedPeriodOverlapException::class);

        ($this->handler)(new BlockPeriodCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-12'),
            checkOut: new \DateTimeImmutable('2025-06-17'),
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itAllowsAdjacentBlocksOnSameRoom(): void
    {
        ($this->handler)(new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable(),
        ));

        ($this->handler)(new BlockPeriodCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-13'),
            checkOut: new \DateTimeImmutable('2025-06-16'),
            createdAt: new \DateTimeImmutable(),
        ));

        self::assertNotNull($this->repository->get('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));
        self::assertNotNull($this->repository->get('b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22'));
    }

    #[Test]
    public function itAllowsSamePeriodOnDifferentRooms(): void
    {
        ($this->handler)(new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440001',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable(),
        ));

        ($this->handler)(new BlockPeriodCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            roomId: '550e8400-e29b-41d4-a716-446655440002',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable(),
        ));

        self::assertNotNull($this->repository->get('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));
        self::assertNotNull($this->repository->get('b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22'));
    }
}

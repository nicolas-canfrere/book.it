<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\BlockPeriod;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommandHandler;
use App\Availability\Domain\Exception\BlockedPeriodOverlapException;
use App\Availability\Domain\Exception\RoomNotFoundException;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Availability\Domain\Port\RoomExistsInterface;
use App\Shared\Domain\Event\BlockedPeriodCreated;
use App\Tests\Availability\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Availability\Infrastructure\Persistence\InMemory\InMemoryBlockedPeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

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
        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->handler = new BlockPeriodCommandHandler($this->repository, $this->roomExists, $dispatcher);
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

    #[Test]
    public function itDispatchesBlockedPeriodCreated(): void
    {
        $repository = $this->createStub(BlockedPeriodRepositoryInterface::class);
        $roomExists = $this->createStub(RoomExistsInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $roomExists->method('exists')->willReturn(true);
        $repository->method('hasOverlap')->willReturn(false);

        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event) use ($checkIn, $checkOut): bool {
                return $event instanceof BlockedPeriodCreated
                    && 'bp-id-1' === $event->blockedPeriodId
                    && 'room-id-1' === $event->roomId
                    && $event->checkIn == $checkIn
                    && $event->checkOut == $checkOut;
            }));

        $handler = new BlockPeriodCommandHandler($repository, $roomExists, $dispatcher);

        ($handler)(new BlockPeriodCommand(
            id: 'bp-id-1',
            roomId: 'room-id-1',
            checkIn: $checkIn,
            checkOut: $checkOut,
            createdAt: new \DateTimeImmutable('2026-05-31T00:00:00Z'),
        ));
    }

    #[Test]
    public function itDoesNotDispatchWhenRoomDoesNotExist(): void
    {
        $repository = $this->createStub(BlockedPeriodRepositoryInterface::class);
        $roomExists = $this->createStub(RoomExistsInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $roomExists->method('exists')->willReturn(false);
        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new BlockPeriodCommandHandler($repository, $roomExists, $dispatcher);

        $this->expectException(RoomNotFoundException::class);

        ($handler)(new BlockPeriodCommand(
            id: 'bp-id-2',
            roomId: 'missing-room',
            checkIn: new \DateTimeImmutable('2026-07-01'),
            checkOut: new \DateTimeImmutable('2026-07-05'),
            createdAt: new \DateTimeImmutable('2026-05-31T00:00:00Z'),
        ));
    }
}

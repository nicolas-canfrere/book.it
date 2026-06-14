<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\DeleteBlockedPeriod;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommandHandler;
use App\Availability\Application\UseCase\DeleteBlockedPeriod\DeleteBlockedPeriodCommand;
use App\Availability\Application\UseCase\DeleteBlockedPeriod\DeleteBlockedPeriodCommandHandler;
use App\Availability\Domain\Exception\BlockedPeriodNotFoundException;
use App\Availability\Domain\Model\BlockedPeriod;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Availability\Domain\ValueObject\DatePeriod;
use App\Shared\Domain\Event\BlockedPeriodDeleted;
use App\Shared\Domain\ValueObject\BlockedPeriodId;
use App\Shared\Domain\ValueObject\RoomId;
use App\Tests\Availability\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Availability\Infrastructure\Persistence\InMemory\InMemoryBlockedPeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class DeleteBlockedPeriodCommandHandlerTest extends TestCase
{
    private InMemoryBlockedPeriodRepository $repository;
    private DeleteBlockedPeriodCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryBlockedPeriodRepository();
        $this->handler = new DeleteBlockedPeriodCommandHandler($this->repository, $this->createStub(EventDispatcherInterface::class));

        $blockHandler = new BlockPeriodCommandHandler($this->repository, new FakeRoomExistenceChecker(), $this->createStub(EventDispatcherInterface::class));
        ($blockHandler)(new BlockPeriodCommand(
            id: new BlockedPeriodId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'),
            roomId: new RoomId('550e8400-e29b-41d4-a716-446655440000'),
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itRemovesTheBlockedPeriod(): void
    {
        ($this->handler)(new DeleteBlockedPeriodCommand(new BlockedPeriodId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11')));

        self::assertNull($this->repository->get(new BlockedPeriodId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11')));
    }

    #[Test]
    public function itThrowsWhenBlockedPeriodDoesNotExist(): void
    {
        $this->expectException(BlockedPeriodNotFoundException::class);

        ($this->handler)(new DeleteBlockedPeriodCommand(new BlockedPeriodId('00000000-0000-4000-8000-000000000000')));
    }

    #[Test]
    public function itDispatchesBlockedPeriodDeleted(): void
    {
        $repository = $this->createStub(BlockedPeriodRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        $blockedPeriod = new BlockedPeriod(
            id: new BlockedPeriodId('bp-id-1'),
            roomId: new RoomId('room-id-1'),
            period: new DatePeriod($checkIn, $checkOut),
            createdAt: new \DateTimeImmutable('2026-05-31T00:00:00Z'),
        );

        $repository->method('get')->willReturn($blockedPeriod);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event) use ($checkIn, $checkOut): bool {
                return $event instanceof BlockedPeriodDeleted
                    && 'room-id-1' === $event->roomId
                    && $event->checkIn == $checkIn
                    && $event->checkOut == $checkOut;
            }));

        $handler = new DeleteBlockedPeriodCommandHandler($repository, $dispatcher);

        ($handler)(new DeleteBlockedPeriodCommand(id: new BlockedPeriodId('bp-id-1')));
    }

    #[Test]
    public function itDoesNotDispatchWhenNotFound(): void
    {
        $repository = $this->createStub(BlockedPeriodRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('get')->willReturn(null);
        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new DeleteBlockedPeriodCommandHandler($repository, $dispatcher);

        $this->expectException(BlockedPeriodNotFoundException::class);

        ($handler)(new DeleteBlockedPeriodCommand(id: new BlockedPeriodId('missing-id')));
    }
}

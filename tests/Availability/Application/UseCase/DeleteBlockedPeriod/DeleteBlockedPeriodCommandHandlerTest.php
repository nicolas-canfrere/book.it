<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\DeleteBlockedPeriod;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommandHandler;
use App\Availability\Application\UseCase\DeleteBlockedPeriod\DeleteBlockedPeriodCommand;
use App\Availability\Application\UseCase\DeleteBlockedPeriod\DeleteBlockedPeriodCommandHandler;
use App\Availability\Domain\Exception\BlockedPeriodNotFoundException;
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
        $this->handler = new DeleteBlockedPeriodCommandHandler($this->repository);

        $blockHandler = new BlockPeriodCommandHandler($this->repository, new FakeRoomExistenceChecker(), $this->createMock(EventDispatcherInterface::class));
        ($blockHandler)(new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itRemovesTheBlockedPeriod(): void
    {
        ($this->handler)(new DeleteBlockedPeriodCommand('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));

        self::assertNull($this->repository->get('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));
    }

    #[Test]
    public function itThrowsWhenBlockedPeriodDoesNotExist(): void
    {
        $this->expectException(BlockedPeriodNotFoundException::class);

        ($this->handler)(new DeleteBlockedPeriodCommand('00000000-0000-4000-8000-000000000000'));
    }
}

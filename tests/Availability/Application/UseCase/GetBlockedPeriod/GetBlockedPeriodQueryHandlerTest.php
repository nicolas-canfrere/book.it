<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\GetBlockedPeriod;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommandHandler;
use App\Availability\Application\UseCase\GetBlockedPeriod\GetBlockedPeriodQuery;
use App\Availability\Application\UseCase\GetBlockedPeriod\GetBlockedPeriodQueryHandler;
use App\Shared\Domain\ValueObject\BlockedPeriodId;
use App\Shared\Domain\ValueObject\RoomId;
use App\Tests\Availability\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Availability\Infrastructure\Persistence\InMemory\InMemoryBlockedPeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class GetBlockedPeriodQueryHandlerTest extends TestCase
{
    private InMemoryBlockedPeriodRepository $repository;
    private GetBlockedPeriodQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryBlockedPeriodRepository();
        $this->handler = new GetBlockedPeriodQueryHandler($this->repository);

        $blockHandler = new BlockPeriodCommandHandler($this->repository, new FakeRoomExistenceChecker(), $this->createStub(EventDispatcherInterface::class));
        ($blockHandler)(new BlockPeriodCommand(
            id: new BlockedPeriodId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'),
            roomId: new RoomId('550e8400-e29b-41d4-a716-446655440000'),
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));
    }

    #[Test]
    public function itReturnsTheBlockedPeriod(): void
    {
        $result = ($this->handler)(new GetBlockedPeriodQuery(new BlockedPeriodId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11')));

        self::assertNotNull($result);
        self::assertSame('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', $result->id->value);
    }

    #[Test]
    public function itReturnsNullWhenNotFound(): void
    {
        $result = ($this->handler)(new GetBlockedPeriodQuery(new BlockedPeriodId('00000000-0000-4000-8000-000000000000')));

        self::assertNull($result);
    }
}

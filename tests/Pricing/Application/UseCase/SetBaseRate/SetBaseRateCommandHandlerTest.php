<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\SetBaseRate;

use App\Pricing\Application\UseCase\SetBaseRate\SetBaseRateCommand;
use App\Pricing\Application\UseCase\SetBaseRate\SetBaseRateCommandHandler;
use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Tests\Pricing\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryBaseRateRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SetBaseRateCommandHandlerTest extends TestCase
{
    private InMemoryBaseRateRepository $repository;
    private FakeRoomExistenceChecker $roomExists;
    private SetBaseRateCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryBaseRateRepository();
        $this->roomExists = new FakeRoomExistenceChecker();
        $this->handler = new SetBaseRateCommandHandler($this->repository, $this->roomExists);
    }

    #[Test]
    public function itPersistsTheBaseRate(): void
    {
        $command = new SetBaseRateCommand(
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            amountCents: 10000,
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        );

        ($this->handler)($command);

        $baseRate = $this->repository->findByRoomId($command->roomId);
        self::assertNotNull($baseRate);
        self::assertSame($command->roomId, $baseRate->roomId);
        self::assertSame(10000, $baseRate->amountCents);
    }

    #[Test]
    public function itReplacesExistingBaseRate(): void
    {
        ($this->handler)(new SetBaseRateCommand(
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            amountCents: 10000,
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        ($this->handler)(new SetBaseRateCommand(
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            amountCents: 20000,
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        $baseRate = $this->repository->findByRoomId('550e8400-e29b-41d4-a716-446655440000');
        self::assertNotNull($baseRate);
        self::assertSame(20000, $baseRate->amountCents);
    }

    #[Test]
    public function itThrowsWhenRoomDoesNotExist(): void
    {
        $this->roomExists->setExists(false);
        $this->expectException(RoomNotFoundException::class);

        ($this->handler)(new SetBaseRateCommand(
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            amountCents: 10000,
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));
    }
}

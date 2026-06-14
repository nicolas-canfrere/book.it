<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\CreateRatePeriod;

use App\Pricing\Application\UseCase\CreateRatePeriod\CreateRatePeriodCommand;
use App\Pricing\Application\UseCase\CreateRatePeriod\CreateRatePeriodCommandHandler;
use App\Pricing\Domain\Exception\RatePeriodOverlapException;
use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Shared\Domain\ValueObject\RoomId;
use App\Tests\Pricing\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryRatePeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CreateRatePeriodCommandHandlerTest extends TestCase
{
    private InMemoryRatePeriodRepository $repository;
    private FakeRoomExistenceChecker $roomExists;
    private CreateRatePeriodCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRatePeriodRepository();
        $this->roomExists = new FakeRoomExistenceChecker();
        $this->handler = new CreateRatePeriodCommandHandler($this->repository, $this->roomExists);
    }

    #[Test]
    public function itPersistsTheRatePeriod(): void
    {
        $command = new CreateRatePeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: new RoomId('550e8400-e29b-41d4-a716-446655440000'),
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            amountCents: 12000,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        );

        ($this->handler)($command);

        $period = $this->repository->findById($command->id);
        self::assertNotNull($period);
        self::assertSame($command->id, $period->id);
        self::assertSame($command->roomId->value, $period->roomId->value);
        self::assertSame('2025-06-10', $period->checkIn->format('Y-m-d'));
        self::assertSame('2025-06-15', $period->checkOut->format('Y-m-d'));
        self::assertSame(12000, $period->amountCents);
    }

    #[Test]
    public function itThrowsWhenRoomDoesNotExist(): void
    {
        $this->roomExists->setExists(false);
        $this->expectException(RoomNotFoundException::class);

        ($this->handler)(new CreateRatePeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: new RoomId('550e8400-e29b-41d4-a716-446655440000'),
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            amountCents: 12000,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));
    }

    #[Test]
    public function itThrowsWhenPeriodOverlaps(): void
    {
        ($this->handler)(new CreateRatePeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: new RoomId('550e8400-e29b-41d4-a716-446655440000'),
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            amountCents: 12000,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        $this->expectException(RatePeriodOverlapException::class);

        ($this->handler)(new CreateRatePeriodCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            roomId: new RoomId('550e8400-e29b-41d4-a716-446655440000'),
            checkIn: new \DateTimeImmutable('2025-06-12'),
            checkOut: new \DateTimeImmutable('2025-06-17'),
            amountCents: 13000,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));
    }

    #[Test]
    public function itAllowsAdjacentPeriods(): void
    {
        ($this->handler)(new CreateRatePeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: new RoomId('550e8400-e29b-41d4-a716-446655440000'),
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            amountCents: 12000,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        ($this->handler)(new CreateRatePeriodCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            roomId: new RoomId('550e8400-e29b-41d4-a716-446655440000'),
            checkIn: new \DateTimeImmutable('2025-06-15'),
            checkOut: new \DateTimeImmutable('2025-06-20'),
            amountCents: 13000,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        self::assertNotNull($this->repository->findById('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));
        self::assertNotNull($this->repository->findById('b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22'));
    }
}

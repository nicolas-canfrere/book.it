<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\UpdateRatePeriod;

use App\Pricing\Application\UseCase\UpdateRatePeriod\UpdateRatePeriodCommand;
use App\Pricing\Application\UseCase\UpdateRatePeriod\UpdateRatePeriodCommandHandler;
use App\Pricing\Domain\Exception\RatePeriodNotFoundException;
use App\Pricing\Domain\Exception\RatePeriodOverlapException;
use App\Pricing\Domain\Model\RatePeriod;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryRatePeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UpdateRatePeriodCommandHandlerTest extends TestCase
{
    private InMemoryRatePeriodRepository $repository;
    private UpdateRatePeriodCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRatePeriodRepository();
        $this->handler = new UpdateRatePeriodCommandHandler($this->repository);
    }

    #[Test]
    public function itUpdatesTheRatePeriod(): void
    {
        $this->repository->save(new RatePeriod(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            amountCents: 12000,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        ($this->handler)(new UpdateRatePeriodCommand(
            ratePeriodId: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-10'),
            amountCents: 25000,
            updatedAt: new \DateTimeImmutable('2025-02-01 10:00:00'),
        ));

        $updated = $this->repository->findById('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11');
        self::assertNotNull($updated);
        self::assertSame('2025-07-01', $updated->checkIn->format('Y-m-d'));
        self::assertSame('2025-07-10', $updated->checkOut->format('Y-m-d'));
        self::assertSame(25000, $updated->amountCents);
    }

    #[Test]
    public function itPreservesCreatedAt(): void
    {
        $originalCreatedAt = new \DateTimeImmutable('2025-01-01 10:00:00');

        $this->repository->save(new RatePeriod(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            amountCents: 12000,
            createdAt: $originalCreatedAt,
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        ($this->handler)(new UpdateRatePeriodCommand(
            ratePeriodId: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-10'),
            amountCents: 25000,
            updatedAt: new \DateTimeImmutable('2025-02-01 10:00:00'),
        ));

        $updated = $this->repository->findById('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11');
        self::assertNotNull($updated);
        self::assertSame($originalCreatedAt->format('Y-m-d H:i:s'), $updated->createdAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function itThrowsWhenRatePeriodNotFound(): void
    {
        $this->expectException(RatePeriodNotFoundException::class);

        ($this->handler)(new UpdateRatePeriodCommand(
            ratePeriodId: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-10'),
            amountCents: 25000,
            updatedAt: new \DateTimeImmutable('2025-02-01 10:00:00'),
        ));
    }

    #[Test]
    public function itThrowsWhenNewDatesOverlapAnotherPeriod(): void
    {
        $this->repository->save(new RatePeriod(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            amountCents: 12000,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        $this->repository->save(new RatePeriod(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-10'),
            amountCents: 20000,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        $this->expectException(RatePeriodOverlapException::class);

        ($this->handler)(new UpdateRatePeriodCommand(
            ratePeriodId: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-07-05'),
            checkOut: new \DateTimeImmutable('2025-07-15'),
            amountCents: 12000,
            updatedAt: new \DateTimeImmutable('2025-02-01 10:00:00'),
        ));
    }

    #[Test]
    public function itDoesNotThrowWhenUpdatingToSameDates(): void
    {
        $this->repository->save(new RatePeriod(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            amountCents: 12000,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        ($this->handler)(new UpdateRatePeriodCommand(
            ratePeriodId: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            amountCents: 15000,
            updatedAt: new \DateTimeImmutable('2025-02-01 10:00:00'),
        ));

        $updated = $this->repository->findById('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11');
        self::assertNotNull($updated);
        self::assertSame(15000, $updated->amountCents);
    }
}

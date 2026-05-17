<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\DeleteRatePeriod;

use App\Pricing\Application\UseCase\DeleteRatePeriod\DeleteRatePeriodCommand;
use App\Pricing\Application\UseCase\DeleteRatePeriod\DeleteRatePeriodCommandHandler;
use App\Pricing\Domain\Exception\RatePeriodNotFoundException;
use App\Pricing\Domain\Model\RatePeriod;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryRatePeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeleteRatePeriodCommandHandlerTest extends TestCase
{
    private InMemoryRatePeriodRepository $repository;
    private DeleteRatePeriodCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRatePeriodRepository();
        $this->handler = new DeleteRatePeriodCommandHandler($this->repository);
    }

    #[Test]
    public function itDeletesTheRatePeriod(): void
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

        ($this->handler)(new DeleteRatePeriodCommand(
            ratePeriodId: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
        ));

        self::assertNull($this->repository->findById('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));
    }

    #[Test]
    public function itThrowsWhenRatePeriodNotFound(): void
    {
        $this->expectException(RatePeriodNotFoundException::class);

        ($this->handler)(new DeleteRatePeriodCommand(
            ratePeriodId: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
        ));
    }
}

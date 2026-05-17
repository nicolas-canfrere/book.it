<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\GetRatePeriods;

use App\Pricing\Application\UseCase\GetRatePeriods\GetRatePeriodsQuery;
use App\Pricing\Application\UseCase\GetRatePeriods\GetRatePeriodsQueryHandler;
use App\Pricing\Domain\Model\RatePeriod;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryRatePeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetRatePeriodsQueryHandlerTest extends TestCase
{
    private InMemoryRatePeriodRepository $repository;
    private GetRatePeriodsQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRatePeriodRepository();
        $this->handler = new GetRatePeriodsQueryHandler($this->repository);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoPeriods(): void
    {
        $result = ($this->handler)(new GetRatePeriodsQuery('550e8400-e29b-41d4-a716-446655440000'));

        self::assertSame([], $result);
    }

    #[Test]
    public function itReturnsPeriodsForRoomSortedByCheckIn(): void
    {
        $this->repository->save(new RatePeriod(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-05'),
            amountCents: 20000,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        $this->repository->save(new RatePeriod(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            amountCents: 12000,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        $result = ($this->handler)(new GetRatePeriodsQuery('550e8400-e29b-41d4-a716-446655440000'));

        self::assertCount(2, $result);
        self::assertSame('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', $result[0]->id);
        self::assertSame('b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22', $result[1]->id);
    }
}

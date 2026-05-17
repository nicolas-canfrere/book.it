<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\GetPromotions;

use App\Pricing\Application\UseCase\GetPromotions\GetPromotionsQuery;
use App\Pricing\Application\UseCase\GetPromotions\GetPromotionsQueryHandler;
use App\Pricing\Domain\Model\Promotion;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryPromotionRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetPromotionsQueryHandlerTest extends TestCase
{
    private InMemoryPromotionRepository $repository;
    private GetPromotionsQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPromotionRepository();
        $this->handler = new GetPromotionsQueryHandler($this->repository);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoPromotions(): void
    {
        $result = ($this->handler)(new GetPromotionsQuery('550e8400-e29b-41d4-a716-446655440000'));

        self::assertSame([], $result);
    }

    #[Test]
    public function itReturnsPromotionsForRoomSortedByCheckIn(): void
    {
        $this->repository->save(new Promotion(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-05'),
            discountPercent: 20,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        $this->repository->save(new Promotion(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            discountPercent: 10,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        $result = ($this->handler)(new GetPromotionsQuery('550e8400-e29b-41d4-a716-446655440000'));

        self::assertCount(2, $result);
        self::assertSame('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', $result[0]->id);
        self::assertSame('b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22', $result[1]->id);
    }

    #[Test]
    public function itDoesNotReturnPromotionsForOtherRooms(): void
    {
        $this->repository->save(new Promotion(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            discountPercent: 15,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        $result = ($this->handler)(new GetPromotionsQuery('550e8400-e29b-41d4-a716-446655440000'));

        self::assertSame([], $result);
    }
}

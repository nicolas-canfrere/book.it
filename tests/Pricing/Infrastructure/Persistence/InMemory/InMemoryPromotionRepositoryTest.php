<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Infrastructure\Persistence\InMemory;

use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\ValueObject\DatePeriod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class InMemoryPromotionRepositoryTest extends TestCase
{
    private const string ROOM_ID = '550e8400-e29b-41d4-a716-446655440000';

    private InMemoryPromotionRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPromotionRepository();
    }

    #[Test]
    public function itReturnsOverlappingPromotions(): void
    {
        $this->repository->save($this->makePromotion('promo-1', '2025-07-09', '2025-07-12')); // overlaps from left
        $this->repository->save($this->makePromotion('promo-2', '2025-07-11', '2025-07-14')); // overlaps from right
        $this->repository->save($this->makePromotion('promo-3', '2025-07-11', '2025-07-12')); // contained within
        $this->repository->save($this->makePromotion('promo-4', '2025-07-05', '2025-07-08')); // before — excluded
        $this->repository->save($this->makePromotion('promo-5', '2025-07-14', '2025-07-16')); // after — excluded

        $stay = new DatePeriod(new \DateTimeImmutable('2025-07-10'), new \DateTimeImmutable('2025-07-13'));
        $result = $this->repository->findOverlappingByRoomId(self::ROOM_ID, $stay);

        self::assertCount(3, $result);
        $ids = array_map(static fn(Promotion $p) => $p->id, $result);
        self::assertContains('promo-1', $ids);
        self::assertContains('promo-2', $ids);
        self::assertContains('promo-3', $ids);
    }

    #[Test]
    public function itExcludesPromotionsTouchingBoundariesOnly(): void
    {
        $this->repository->save($this->makePromotion('promo-adj-before', '2025-07-08', '2025-07-10'));
        $this->repository->save($this->makePromotion('promo-adj-after', '2025-07-13', '2025-07-15'));

        $stay = new DatePeriod(new \DateTimeImmutable('2025-07-10'), new \DateTimeImmutable('2025-07-13'));
        $result = $this->repository->findOverlappingByRoomId(self::ROOM_ID, $stay);

        self::assertCount(0, $result);
    }

    #[Test]
    public function itFiltersOnlyByRoomId(): void
    {
        $this->repository->save($this->makePromotion('promo-same-room', '2025-07-10', '2025-07-13', self::ROOM_ID));
        $this->repository->save($this->makePromotion('promo-other-room', '2025-07-10', '2025-07-13', 'other-room-id'));

        $stay = new DatePeriod(new \DateTimeImmutable('2025-07-10'), new \DateTimeImmutable('2025-07-13'));
        $result = $this->repository->findOverlappingByRoomId(self::ROOM_ID, $stay);

        self::assertCount(1, $result);
        self::assertSame('promo-same-room', $result[0]->id);
    }

    private function makePromotion(string $id, string $checkIn, string $checkOut, string $roomId = self::ROOM_ID): Promotion
    {
        return new Promotion(
            id: $id,
            roomId: $roomId,
            checkIn: new \DateTimeImmutable($checkIn),
            checkOut: new \DateTimeImmutable($checkOut),
            discountPercent: 10,
            createdAt: new \DateTimeImmutable('2025-01-01'),
            updatedAt: new \DateTimeImmutable('2025-01-01'),
        );
    }
}

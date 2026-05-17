<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\UpdatePromotion;

use App\Pricing\Application\UseCase\UpdatePromotion\UpdatePromotionCommand;
use App\Pricing\Application\UseCase\UpdatePromotion\UpdatePromotionCommandHandler;
use App\Pricing\Domain\Exception\PromotionNotFoundException;
use App\Pricing\Domain\Exception\PromotionOverlapException;
use App\Pricing\Domain\Model\Promotion;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryPromotionRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UpdatePromotionCommandHandlerTest extends TestCase
{
    private InMemoryPromotionRepository $repository;
    private UpdatePromotionCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPromotionRepository();
        $this->handler = new UpdatePromotionCommandHandler($this->repository);
    }

    #[Test]
    public function itUpdatesThePromotion(): void
    {
        $this->repository->save(new Promotion(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            discountPercent: 10,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        ($this->handler)(new UpdatePromotionCommand(
            promotionId: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-10'),
            discountPercent: 25,
            updatedAt: new \DateTimeImmutable('2025-02-01 10:00:00'),
        ));

        $updated = $this->repository->findById('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11');
        self::assertNotNull($updated);
        self::assertSame('2025-07-10', $updated->getCheckOut()->format('Y-m-d'));
        self::assertSame(25, $updated->getDiscountPercent());
    }

    #[Test]
    public function itThrowsWhenPromotionNotFound(): void
    {
        $this->expectException(PromotionNotFoundException::class);

        ($this->handler)(new UpdatePromotionCommand(
            promotionId: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-10'),
            discountPercent: 25,
            updatedAt: new \DateTimeImmutable('2025-02-01 10:00:00'),
        ));
    }

    #[Test]
    public function itThrowsWhenNewDatesOverlapAnotherPromotion(): void
    {
        $this->repository->save(new Promotion(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            discountPercent: 10,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        $this->repository->save(new Promotion(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-10'),
            discountPercent: 20,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        $this->expectException(PromotionOverlapException::class);

        ($this->handler)(new UpdatePromotionCommand(
            promotionId: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-07-05'),
            checkOut: new \DateTimeImmutable('2025-07-15'),
            discountPercent: 10,
            updatedAt: new \DateTimeImmutable('2025-02-01 10:00:00'),
        ));
    }
}

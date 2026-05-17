<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\DeletePromotion;

use App\Pricing\Application\UseCase\DeletePromotion\DeletePromotionCommand;
use App\Pricing\Application\UseCase\DeletePromotion\DeletePromotionCommandHandler;
use App\Pricing\Domain\Exception\PromotionNotFoundException;
use App\Pricing\Domain\Model\Promotion;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryPromotionRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeletePromotionCommandHandlerTest extends TestCase
{
    private InMemoryPromotionRepository $repository;
    private DeletePromotionCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPromotionRepository();
        $this->handler = new DeletePromotionCommandHandler($this->repository);
    }

    #[Test]
    public function itDeletesThePromotion(): void
    {
        $this->repository->save(new Promotion(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            discountPercent: 20,
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        ($this->handler)(new DeletePromotionCommand(
            promotionId: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
        ));

        self::assertNull($this->repository->findById('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));
    }

    #[Test]
    public function itThrowsWhenPromotionNotFound(): void
    {
        $this->expectException(PromotionNotFoundException::class);

        ($this->handler)(new DeletePromotionCommand(
            promotionId: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
        ));
    }
}

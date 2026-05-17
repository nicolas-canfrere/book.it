<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\CreatePromotion;

use App\Pricing\Application\UseCase\CreatePromotion\CreatePromotionCommand;
use App\Pricing\Application\UseCase\CreatePromotion\CreatePromotionCommandHandler;
use App\Pricing\Domain\Exception\PromotionOverlapException;
use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Tests\Pricing\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryPromotionRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CreatePromotionCommandHandlerTest extends TestCase
{
    private const ROOM_ID = 'f1e2d3c4-b5a6-4978-8766-554433221100';
    private const PROMOTION_ID = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';

    private InMemoryPromotionRepository $repository;
    private FakeRoomExistenceChecker $roomExists;
    private CreatePromotionCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPromotionRepository();
        $this->roomExists = new FakeRoomExistenceChecker();
        $this->handler = new CreatePromotionCommandHandler($this->repository, $this->roomExists);
    }

    #[Test]
    public function testCreatesPromotion(): void
    {
        ($this->handler)(new CreatePromotionCommand(
            id: self::PROMOTION_ID,
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-15'),
            discountPercent: 10,
            createdAt: new \DateTimeImmutable('2025-01-01 00:00:00'),
        ));

        $promotion = $this->repository->findById(self::PROMOTION_ID);
        self::assertNotNull($promotion);
        self::assertSame(self::ROOM_ID, $promotion->roomId);
        self::assertSame('2025-07-01', $promotion->getCheckIn()->format('Y-m-d'));
        self::assertSame('2025-07-15', $promotion->getCheckOut()->format('Y-m-d'));
        self::assertSame(10, $promotion->getDiscountPercent());
    }

    #[Test]
    public function testThrowsWhenRoomNotFound(): void
    {
        $this->roomExists->setExists(false);
        $this->expectException(RoomNotFoundException::class);
        ($this->handler)(new CreatePromotionCommand(
            id: self::PROMOTION_ID,
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-15'),
            discountPercent: 10,
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function testThrowsWhenOverlap(): void
    {
        ($this->handler)(new CreatePromotionCommand(
            id: self::PROMOTION_ID,
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-15'),
            discountPercent: 10,
            createdAt: new \DateTimeImmutable(),
        ));
        $this->expectException(PromotionOverlapException::class);
        ($this->handler)(new CreatePromotionCommand(
            id: 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e',
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-07-10'),
            checkOut: new \DateTimeImmutable('2025-07-20'),
            discountPercent: 15,
            createdAt: new \DateTimeImmutable(),
        ));
    }
}

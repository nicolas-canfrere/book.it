<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Infrastructure\Persistence\InMemory;

use App\Pricing\Domain\Model\RatePeriod;
use App\Pricing\Domain\ValueObject\DatePeriod;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class InMemoryRatePeriodRepositoryTest extends TestCase
{
    private const string ROOM_ID = '550e8400-e29b-41d4-a716-446655440000';

    private InMemoryRatePeriodRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRatePeriodRepository();
    }

    #[Test]
    public function itReturnsOverlappingPeriods(): void
    {
        $this->repository->save($this->makePeriod('rp-1', '2025-07-09', '2025-07-12')); // overlaps from left
        $this->repository->save($this->makePeriod('rp-2', '2025-07-11', '2025-07-14')); // overlaps from right
        $this->repository->save($this->makePeriod('rp-3', '2025-07-11', '2025-07-12')); // contained within
        $this->repository->save($this->makePeriod('rp-4', '2025-07-05', '2025-07-08')); // before — excluded
        $this->repository->save($this->makePeriod('rp-5', '2025-07-14', '2025-07-16')); // after — excluded

        $stay = new DatePeriod(new \DateTimeImmutable('2025-07-10'), new \DateTimeImmutable('2025-07-13'));
        $result = $this->repository->findOverlappingByRoomId(new RoomId(self::ROOM_ID), $stay);

        self::assertCount(3, $result);
        $ids = array_map(static fn(RatePeriod $rp) => $rp->id, $result);
        self::assertContains('rp-1', $ids);
        self::assertContains('rp-2', $ids);
        self::assertContains('rp-3', $ids);
    }

    #[Test]
    public function itExcludesPeriodsTouchingBoundariesOnly(): void
    {
        $this->repository->save($this->makePeriod('rp-adj-before', '2025-07-08', '2025-07-10')); // check_out == stay check_in
        $this->repository->save($this->makePeriod('rp-adj-after', '2025-07-13', '2025-07-15'));  // check_in == stay check_out

        $stay = new DatePeriod(new \DateTimeImmutable('2025-07-10'), new \DateTimeImmutable('2025-07-13'));
        $result = $this->repository->findOverlappingByRoomId(new RoomId(self::ROOM_ID), $stay);

        self::assertCount(0, $result);
    }

    #[Test]
    public function itFiltersOnlyByRoomId(): void
    {
        $this->repository->save($this->makePeriod('rp-same-room', '2025-07-10', '2025-07-13', self::ROOM_ID));
        $this->repository->save($this->makePeriod('rp-other-room', '2025-07-10', '2025-07-13', 'other-room-id'));

        $stay = new DatePeriod(new \DateTimeImmutable('2025-07-10'), new \DateTimeImmutable('2025-07-13'));
        $result = $this->repository->findOverlappingByRoomId(new RoomId(self::ROOM_ID), $stay);

        self::assertCount(1, $result);
        self::assertSame('rp-same-room', $result[0]->id);
    }

    private function makePeriod(string $id, string $checkIn, string $checkOut, string $roomId = self::ROOM_ID): RatePeriod
    {
        return new RatePeriod(
            id: $id,
            roomId: new RoomId($roomId),
            checkIn: new \DateTimeImmutable($checkIn),
            checkOut: new \DateTimeImmutable($checkOut),
            amountCents: 10000,
            createdAt: new \DateTimeImmutable('2025-01-01'),
            updatedAt: new \DateTimeImmutable('2025-01-01'),
        );
    }
}

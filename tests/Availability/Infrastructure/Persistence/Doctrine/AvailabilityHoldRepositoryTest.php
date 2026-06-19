<?php

declare(strict_types=1);

namespace App\Tests\Availability\Infrastructure\Persistence\InMemory;

use App\Availability\Domain\Model\AvailabilityHold;
use App\Availability\Domain\ValueObject\DatePeriod;
use App\Shared\Domain\ValueObject\AvailabilityHoldId;
use App\Shared\Domain\ValueObject\ReservationId;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AvailabilityHoldRepositoryTest extends TestCase
{
    private InMemoryAvailabilityHoldRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryAvailabilityHoldRepository();
    }

    #[Test]
    public function itAddAndHasActiveOverlap(): void
    {
        $roomId = 'room-1';
        $checkIn = new \DateTimeImmutable('2030-06-01');
        $checkOut = new \DateTimeImmutable('2030-06-05');

        $this->repository->add(new AvailabilityHold(
            id: new AvailabilityHoldId('hold-1'),
            roomId: new RoomId($roomId),
            reservationId: new ReservationId('res-1'),
            period: new DatePeriod($checkIn, $checkOut),
            expiresAt: new \DateTimeImmutable('+15 minutes'),
            createdAt: new \DateTimeImmutable(),
        ));

        self::assertTrue($this->repository->hasActiveOverlap(new RoomId($roomId), $checkIn, $checkOut));
    }

    #[Test]
    public function itExpiredHoldDoesNotCountAsOverlap(): void
    {
        $roomId = 'room-2';
        $checkIn = new \DateTimeImmutable('2030-07-01');
        $checkOut = new \DateTimeImmutable('2030-07-05');

        $this->repository->add(new AvailabilityHold(
            id: new AvailabilityHoldId('hold-2'),
            roomId: new RoomId($roomId),
            reservationId: new ReservationId('res-2'),
            period: new DatePeriod($checkIn, $checkOut),
            expiresAt: new \DateTimeImmutable('-1 second'),
            createdAt: new \DateTimeImmutable('-20 minutes'),
        ));

        self::assertFalse($this->repository->hasActiveOverlap(new RoomId($roomId), $checkIn, $checkOut));
    }

    #[Test]
    public function itDeleteByReservationIdRemovesHold(): void
    {
        $roomId = 'room-3';
        $checkIn = new \DateTimeImmutable('2030-08-01');
        $checkOut = new \DateTimeImmutable('2030-08-05');

        $this->repository->add(new AvailabilityHold(
            id: new AvailabilityHoldId('hold-3'),
            roomId: new RoomId($roomId),
            reservationId: new ReservationId('res-3'),
            period: new DatePeriod($checkIn, $checkOut),
            expiresAt: new \DateTimeImmutable('+15 minutes'),
            createdAt: new \DateTimeImmutable(),
        ));

        $this->repository->deleteByReservationId(new ReservationId('res-3'));

        self::assertFalse($this->repository->hasActiveOverlap(new RoomId($roomId), $checkIn, $checkOut));
    }

    #[Test]
    public function itNoOverlapWhenRepositoryIsEmpty(): void
    {
        self::assertFalse($this->repository->hasActiveOverlap(
            new RoomId('room-4'),
            new \DateTimeImmutable('2030-09-01'),
            new \DateTimeImmutable('2030-09-05'),
        ));
    }
}

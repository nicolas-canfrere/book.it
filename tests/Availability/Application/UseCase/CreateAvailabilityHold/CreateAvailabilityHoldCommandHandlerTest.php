<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\CreateAvailabilityHold;

use App\Availability\Application\UseCase\CreateAvailabilityHold\CreateAvailabilityHoldCommand;
use App\Availability\Application\UseCase\CreateAvailabilityHold\CreateAvailabilityHoldCommandHandler;
use App\Availability\Domain\Exception\AvailabilityHoldOverlapException;
use App\Availability\Domain\Model\AvailabilityHold;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CreateAvailabilityHoldCommandHandlerTest extends TestCase
{
    private MockObject&AvailabilityHoldRepositoryInterface $repository;
    private CreateAvailabilityHoldCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AvailabilityHoldRepositoryInterface::class);
        $this->handler = new CreateAvailabilityHoldCommandHandler($this->repository);
    }

    public function test_creates_hold_when_no_active_overlap(): void
    {
        $this->repository->method('hasActiveOverlap')->willReturn(false);
        $this->repository->expects(self::once())->method('add')
            ->with(self::isInstanceOf(AvailabilityHold::class));

        ($this->handler)(new CreateAvailabilityHoldCommand(
            id: 'hold-uuid',
            roomId: 'room-uuid',
            reservationId: 'res-uuid',
            checkIn: new \DateTimeImmutable('2030-06-01'),
            checkOut: new \DateTimeImmutable('2030-06-05'),
            expiresAt: new \DateTimeImmutable('+15 minutes'),
            createdAt: new \DateTimeImmutable(),
        ));
    }

    public function test_throws_when_active_overlap_exists(): void
    {
        $this->repository->method('hasActiveOverlap')->willReturn(true);
        $this->repository->expects(self::never())->method('add');

        $this->expectException(AvailabilityHoldOverlapException::class);

        ($this->handler)(new CreateAvailabilityHoldCommand(
            id: 'hold-uuid',
            roomId: 'room-uuid',
            reservationId: 'res-uuid',
            checkIn: new \DateTimeImmutable('2030-06-01'),
            checkOut: new \DateTimeImmutable('2030-06-05'),
            expiresAt: new \DateTimeImmutable('+15 minutes'),
            createdAt: new \DateTimeImmutable(),
        ));
    }
}

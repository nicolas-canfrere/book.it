<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\CreateAvailabilityHold;

use App\Availability\Application\UseCase\CreateAvailabilityHold\CreateAvailabilityHoldCommand;
use App\Availability\Application\UseCase\CreateAvailabilityHold\CreateAvailabilityHoldCommandHandler;
use App\Availability\Domain\Exception\AvailabilityHoldOverlapException;
use App\Availability\Domain\Model\AvailabilityHold;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Shared\Domain\Event\AvailabilityHoldCreated;
use App\Shared\Domain\ValueObject\AvailabilityHoldId;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class CreateAvailabilityHoldCommandHandlerTest extends TestCase
{
    #[Test]
    public function itCreatesHoldWhenNoActiveOverlap(): void
    {
        /** @var AvailabilityHoldRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(AvailabilityHoldRepositoryInterface::class);
        $repository->method('hasActiveOverlap')->willReturn(false);
        $repository->expects(self::once())->method('add')
            ->with(self::isInstanceOf(AvailabilityHold::class));

        $handler = new CreateAvailabilityHoldCommandHandler($repository, $this->createStub(EventDispatcherInterface::class));

        ($handler)(new CreateAvailabilityHoldCommand(
            id: new AvailabilityHoldId('hold-uuid'),
            roomId: new RoomId('room-uuid'),
            reservationId: 'res-uuid',
            checkIn: new \DateTimeImmutable('2030-06-01'),
            checkOut: new \DateTimeImmutable('2030-06-05'),
            expiresAt: new \DateTimeImmutable('+15 minutes'),
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itThrowsWhenActiveOverlapExists(): void
    {
        /** @var AvailabilityHoldRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(AvailabilityHoldRepositoryInterface::class);
        $repository->method('hasActiveOverlap')->willReturn(true);
        $repository->expects(self::never())->method('add');

        $handler = new CreateAvailabilityHoldCommandHandler($repository, $this->createStub(EventDispatcherInterface::class));

        $this->expectException(AvailabilityHoldOverlapException::class);

        ($handler)(new CreateAvailabilityHoldCommand(
            id: new AvailabilityHoldId('hold-uuid'),
            roomId: new RoomId('room-uuid'),
            reservationId: 'res-uuid',
            checkIn: new \DateTimeImmutable('2030-06-01'),
            checkOut: new \DateTimeImmutable('2030-06-05'),
            expiresAt: new \DateTimeImmutable('+15 minutes'),
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itDispatchesAvailabilityHoldCreated(): void
    {
        $repository = $this->createStub(AvailabilityHoldRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('hasActiveOverlap')->willReturn(false);

        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');
        $expiresAt = new \DateTimeImmutable('2026-05-31T00:15:00Z');

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event) use ($checkIn, $checkOut, $expiresAt): bool {
                return $event instanceof AvailabilityHoldCreated
                    && 'hold-id-1' === $event->holdId
                    && 'room-id-1' === $event->roomId
                    && 'res-id-1' === $event->reservationId
                    && $event->checkIn == $checkIn
                    && $event->checkOut == $checkOut
                    && $event->expiresAt == $expiresAt;
            }));

        $handler = new CreateAvailabilityHoldCommandHandler($repository, $dispatcher);

        ($handler)(new CreateAvailabilityHoldCommand(
            id: new AvailabilityHoldId('hold-id-1'),
            roomId: new RoomId('room-id-1'),
            reservationId: 'res-id-1',
            checkIn: $checkIn,
            checkOut: $checkOut,
            expiresAt: $expiresAt,
            createdAt: new \DateTimeImmutable('2026-05-31T00:00:00Z'),
        ));
    }
}

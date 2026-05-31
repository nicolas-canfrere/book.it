<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure\EventListener;

use App\Notification\Application\UseCase\SendCancellationConfirmationEmail\SendCancellationConfirmationEmailCommand;
use App\Notification\Infrastructure\EventListener\ReservationCancelledListener;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\ReservationCancelled;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationCancelledListenerTest extends TestCase
{
    private AsyncCommandDispatcherInterface&MockObject $commandDispatcher;
    private ReservationCancelledListener $listener;

    protected function setUp(): void
    {
        $this->commandDispatcher = $this->createMock(AsyncCommandDispatcherInterface::class);
        $this->listener = new ReservationCancelledListener($this->commandDispatcher);
    }

    public function testDispatchesSendCancellationConfirmationEmailCommand(): void
    {
        $event = new ReservationCancelled(
            reservationId: 'res-1',
            roomId: 'room-1',
            bookerId: 'booker-1',
            refundAmountCents: 12000,
            checkIn: new \DateTimeImmutable('2026-07-01'),
            checkOut: new \DateTimeImmutable('2026-07-05'),
        );

        $this->commandDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendCancellationConfirmationEmailCommand $cmd) {
                return 'res-1' === $cmd->reservationId
                    && 'booker-1' === $cmd->bookerId
                    && 12000 === $cmd->refundAmountCents;
            }));

        ($this->listener)($event);
    }
}

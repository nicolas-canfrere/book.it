<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\EventListener;

use App\Reservation\Application\UseCase\CancelPendingReservation\CancelPendingReservationCommand;
use App\Reservation\Infrastructure\EventListener\PaymentCancelledListener;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Domain\Event\PaymentCancelled;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PaymentCancelledListenerTest extends TestCase
{
    #[Test]
    public function itDispatchesCancelPendingReservationCommand(): void
    {
        $bus = $this->createMock(SyncCommandBusInterface::class);
        $bus
            ->expects($this->once())
            ->method('execute')
            ->with(new CancelPendingReservationCommand('reservation-id-456'));

        $listener = new PaymentCancelledListener($bus);
        $listener(new PaymentCancelled('reservation-id-456'));
    }
}

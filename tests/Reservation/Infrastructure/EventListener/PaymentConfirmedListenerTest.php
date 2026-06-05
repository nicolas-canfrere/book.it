<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\EventListener;

use App\Reservation\Application\UseCase\ConfirmReservation\ConfirmReservationCommand;
use App\Reservation\Infrastructure\EventListener\PaymentConfirmedListener;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Domain\Event\PaymentConfirmed;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PaymentConfirmedListenerTest extends TestCase
{
    #[Test]
    public function itDispatchesConfirmReservationCommand(): void
    {
        $bus = $this->createMock(SyncCommandBusInterface::class);
        $bus
            ->expects($this->once())
            ->method('execute')
            ->with(new ConfirmReservationCommand('reservation-id-123'));

        $listener = new PaymentConfirmedListener($bus);
        $listener(new PaymentConfirmed('reservation-id-123'));
    }
}

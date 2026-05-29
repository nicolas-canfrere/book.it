<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure\EventListener;

use App\Notification\Application\UseCase\SendBookingConfirmationEmail\SendBookingConfirmationEmailCommand;
use App\Notification\Infrastructure\EventListener\ReservationConfirmedListener;
use App\Shared\Domain\Event\ReservationConfirmed;
use App\Tests\Fake\FakeAsyncCommandDispatcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationConfirmedListenerTest extends TestCase
{
    public function test_dispatches_send_booking_confirmation_email_command(): void
    {
        $dispatcher = new FakeAsyncCommandDispatcher();
        $listener = new ReservationConfirmedListener($dispatcher);

        $listener(new ReservationConfirmed(
            reservationId: 'res-001',
            roomId: 'room-001',
            bookerId: 'booker-001',
            checkIn: new \DateTimeImmutable('2026-07-01'),
            checkOut: new \DateTimeImmutable('2026-07-05'),
        ));

        $command = $dispatcher->getLastDispatched();
        self::assertInstanceOf(SendBookingConfirmationEmailCommand::class, $command);
        self::assertSame('res-001', $command->reservationId);
        self::assertSame('booker-001', $command->bookerId);
    }
}

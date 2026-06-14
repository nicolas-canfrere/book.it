<?php

declare(strict_types=1);

namespace App\Tests\Availability\Integration\EventListener;

use App\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod\DeleteBlockedPeriodByRoomAndPeriodCommand;
use App\Availability\Infrastructure\EventListener\ReservationCheckedOutListener;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Domain\Event\ReservationCheckedOut;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationCheckedOutListenerTest extends TestCase
{
    #[Test]
    public function it_dispatches_delete_command_with_room_and_period(): void
    {
        $checkIn = new \DateTimeImmutable('2025-06-10');
        $checkOut = new \DateTimeImmutable('2025-06-15');

        $commandBus = $this->createMock(SyncCommandBusInterface::class);
        $commandBus->expects(self::once())
            ->method('execute')
            ->with(self::callback(static function (object $cmd) use ($checkIn, $checkOut): bool {
                return $cmd instanceof DeleteBlockedPeriodByRoomAndPeriodCommand
                    && 'room-1' === $cmd->roomId->value
                    && $cmd->checkIn == $checkIn
                    && $cmd->checkOut == $checkOut;
            }));

        $listener = new ReservationCheckedOutListener($commandBus);

        $listener(new ReservationCheckedOut(
            reservationId: 'res-1',
            roomId: 'room-1',
            bookerId: 'booker-1',
            checkIn: $checkIn,
            checkOut: $checkOut,
            actualDepartureDate: new \DateTimeImmutable('2025-06-13'),
        ));
    }
}

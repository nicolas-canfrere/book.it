<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod;

use App\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod\DeleteBlockedPeriodByRoomAndPeriodCommand;
use App\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod\DeleteBlockedPeriodByRoomAndPeriodCommandHandler;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Domain\Event\BlockedPeriodDeleted;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class DeleteBlockedPeriodByRoomAndPeriodCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesBlockedPeriodDeleted(): void
    {
        $repository = $this->createMock(BlockedPeriodRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event) use ($checkIn, $checkOut): bool {
                return $event instanceof BlockedPeriodDeleted
                    && 'room-id-1' === $event->roomId
                    && $event->checkIn == $checkIn
                    && $event->checkOut == $checkOut;
            }));

        $handler = new DeleteBlockedPeriodByRoomAndPeriodCommandHandler($repository, $dispatcher);

        ($handler)(new DeleteBlockedPeriodByRoomAndPeriodCommand(
            roomId: 'room-id-1',
            checkIn: $checkIn,
            checkOut: $checkOut,
        ));
    }
}

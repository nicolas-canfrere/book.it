<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\DeleteAvailabilityHold;

use App\Availability\Application\UseCase\DeleteAvailabilityHold\DeleteAvailabilityHoldCommand;
use App\Availability\Application\UseCase\DeleteAvailabilityHold\DeleteAvailabilityHoldCommandHandler;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Shared\Domain\Event\AvailabilityHoldDeleted;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class DeleteAvailabilityHoldCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDeletesHoldByReservationId(): void
    {
        /** @var AvailabilityHoldRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(AvailabilityHoldRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('deleteByReservationId')
            ->with('res-uuid');

        $handler = new DeleteAvailabilityHoldCommandHandler($repository, $this->createStub(EventDispatcherInterface::class));

        ($handler)(new DeleteAvailabilityHoldCommand(reservationId: 'res-uuid'));
    }

    #[Test]
    public function itDispatchesAvailabilityHoldDeleted(): void
    {
        $repository = $this->createStub(AvailabilityHoldRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof AvailabilityHoldDeleted
                    && 'res-id-1' === $event->reservationId;
            }));

        $handler = new DeleteAvailabilityHoldCommandHandler($repository, $dispatcher);

        ($handler)(new DeleteAvailabilityHoldCommand(reservationId: 'res-id-1'));
    }
}

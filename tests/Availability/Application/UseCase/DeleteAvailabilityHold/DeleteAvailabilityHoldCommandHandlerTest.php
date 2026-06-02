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
    private MockObject&AvailabilityHoldRepositoryInterface $repository;
    private MockObject&EventDispatcherInterface $dispatcher;
    private DeleteAvailabilityHoldCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AvailabilityHoldRepositoryInterface::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->handler = new DeleteAvailabilityHoldCommandHandler($this->repository, $this->dispatcher);
    }

    #[Test]
    public function itDeletesHoldByReservationId(): void
    {
        $this->repository->expects(self::once())
            ->method('deleteByReservationId')
            ->with('res-uuid');

        ($this->handler)(new DeleteAvailabilityHoldCommand(reservationId: 'res-uuid'));
    }

    #[Test]
    public function itDispatchesAvailabilityHoldDeleted(): void
    {
        $repository = $this->createMock(AvailabilityHoldRepositoryInterface::class);
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

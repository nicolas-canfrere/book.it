<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\DeleteAvailabilityHold;

use App\Availability\Application\UseCase\DeleteAvailabilityHold\DeleteAvailabilityHoldCommand;
use App\Availability\Application\UseCase\DeleteAvailabilityHold\DeleteAvailabilityHoldCommandHandler;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeleteAvailabilityHoldCommandHandlerTest extends TestCase
{
    private MockObject&AvailabilityHoldRepositoryInterface $repository;
    private DeleteAvailabilityHoldCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AvailabilityHoldRepositoryInterface::class);
        $this->handler = new DeleteAvailabilityHoldCommandHandler($this->repository);
    }

    public function test_deletes_hold_by_reservation_id(): void
    {
        $this->repository->expects(self::once())
            ->method('deleteByReservationId')
            ->with('res-uuid');

        ($this->handler)(new DeleteAvailabilityHoldCommand(reservationId: 'res-uuid'));
    }
}

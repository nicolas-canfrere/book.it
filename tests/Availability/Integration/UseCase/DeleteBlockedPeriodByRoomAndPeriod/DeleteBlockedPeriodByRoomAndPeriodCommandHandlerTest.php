<?php

declare(strict_types=1);

namespace App\Tests\Availability\Integration\UseCase\DeleteBlockedPeriodByRoomAndPeriod;

use App\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod\DeleteBlockedPeriodByRoomAndPeriodCommand;
use App\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod\DeleteBlockedPeriodByRoomAndPeriodCommandHandler;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class DeleteBlockedPeriodByRoomAndPeriodCommandHandlerTest extends TestCase
{
    #[Test]
    public function it_calls_remove_on_the_repository(): void
    {
        $repo = $this->createMock(BlockedPeriodRepositoryInterface::class);
        $checkIn = new \DateTimeImmutable('2025-06-10');
        $checkOut = new \DateTimeImmutable('2025-06-15');

        $repo->expects(self::once())
            ->method('removeByRoomAndPeriod')
            ->with(new RoomId('room-1'), $checkIn, $checkOut);

        $handler = new DeleteBlockedPeriodByRoomAndPeriodCommandHandler($repo, $this->createStub(EventDispatcherInterface::class));

        ($handler)(new DeleteBlockedPeriodByRoomAndPeriodCommand(new RoomId('room-1'), $checkIn, $checkOut));
    }
}

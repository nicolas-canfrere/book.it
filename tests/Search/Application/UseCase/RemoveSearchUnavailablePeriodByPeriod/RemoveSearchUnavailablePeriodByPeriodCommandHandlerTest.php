<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\RemoveSearchUnavailablePeriodByPeriod;

use App\Search\Application\UseCase\RemoveSearchUnavailablePeriodByPeriod\RemoveSearchUnavailablePeriodByPeriodCommand;
use App\Search\Application\UseCase\RemoveSearchUnavailablePeriodByPeriod\RemoveSearchUnavailablePeriodByPeriodCommandHandler;
use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RemoveSearchUnavailablePeriodByPeriodCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesRemoveByPeriodToWriter(): void
    {
        $writer = $this->createMock(UnavailablePeriodWriterInterface::class);
        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        $writer->expects($this->once())
            ->method('removeByPeriod')
            ->with(new RoomId('room-id-1'), $checkIn, $checkOut);

        $handler = new RemoveSearchUnavailablePeriodByPeriodCommandHandler($writer);
        ($handler)(new RemoveSearchUnavailablePeriodByPeriodCommand(
            roomId: new RoomId('room-id-1'),
            checkIn: $checkIn,
            checkOut: $checkOut,
        ));
    }
}

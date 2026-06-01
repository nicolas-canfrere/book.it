<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\AddSearchUnavailablePeriod;

use App\Search\Application\UseCase\AddSearchUnavailablePeriod\AddSearchUnavailablePeriodCommand;
use App\Search\Application\UseCase\AddSearchUnavailablePeriod\AddSearchUnavailablePeriodCommandHandler;
use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AddSearchUnavailablePeriodCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesAddToWriter(): void
    {
        $writer = $this->createMock(UnavailablePeriodWriterInterface::class);
        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        $writer->expects($this->once())
            ->method('add')
            ->with('source-id-1', 'room-id-1', $checkIn, $checkOut);

        $handler = new AddSearchUnavailablePeriodCommandHandler($writer);
        ($handler)(new AddSearchUnavailablePeriodCommand(
            sourceId: 'source-id-1',
            roomId: 'room-id-1',
            checkIn: $checkIn,
            checkOut: $checkOut,
        ));
    }
}

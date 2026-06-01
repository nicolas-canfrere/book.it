<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\UpdateSearchBaseRate;

use App\Search\Application\UseCase\UpdateSearchBaseRate\UpdateSearchBaseRateCommand;
use App\Search\Application\UseCase\UpdateSearchBaseRate\UpdateSearchBaseRateCommandHandler;
use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UpdateSearchBaseRateCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesBaseRateUpdateToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('updateBaseRateByRoom')
            ->with('room-id-1', 15000);

        $handler = new UpdateSearchBaseRateCommandHandler($writer);
        ($handler)(new UpdateSearchBaseRateCommand(roomId: 'room-id-1', amountCents: 15000));
    }
}

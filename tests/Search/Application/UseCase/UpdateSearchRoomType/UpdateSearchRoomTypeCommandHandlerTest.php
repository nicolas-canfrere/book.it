<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\UpdateSearchRoomType;

use App\Search\Application\UseCase\UpdateSearchRoomType\UpdateSearchRoomTypeCommand;
use App\Search\Application\UseCase\UpdateSearchRoomType\UpdateSearchRoomTypeCommandHandler;
use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Domain\ValueObject\RoomTypeId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UpdateSearchRoomTypeCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesUpdateToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('updateRoomType')
            ->with(new RoomTypeId('rt-id-1'), 'Standard Plus', 3, [['type' => 'king', 'count' => 1]]);

        $handler = new UpdateSearchRoomTypeCommandHandler($writer);
        ($handler)(new UpdateSearchRoomTypeCommand(
            roomTypeId: new RoomTypeId('rt-id-1'),
            name: 'Standard Plus',
            guestCapacity: 3,
            bedComposition: [['type' => 'king', 'count' => 1]],
        ));
    }
}

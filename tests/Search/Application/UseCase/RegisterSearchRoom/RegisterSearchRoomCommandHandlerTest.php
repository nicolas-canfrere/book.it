<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\RegisterSearchRoom;

use App\Search\Application\UseCase\RegisterSearchRoom\RegisterSearchRoomCommand;
use App\Search\Application\UseCase\RegisterSearchRoom\RegisterSearchRoomCommandHandler;
use App\Search\Domain\Port\RoomIndexWriterInterface;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegisterSearchRoomCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesUpsertToWriter(): void
    {
        $writer = $this->createMock(RoomIndexWriterInterface::class);
        $writer->expects($this->once())
            ->method('upsert')
            ->with(new RoomId('room-id-1'), 'rt-id-1', new HotelId('hotel-id-1'));

        $handler = new RegisterSearchRoomCommandHandler($writer);
        ($handler)(new RegisterSearchRoomCommand(roomId: new RoomId('room-id-1'), hotelId: new HotelId('hotel-id-1'), roomTypeId: 'rt-id-1'));
    }
}

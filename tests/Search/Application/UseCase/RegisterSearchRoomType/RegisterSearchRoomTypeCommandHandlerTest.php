<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\RegisterSearchRoomType;

use App\Search\Application\UseCase\RegisterSearchRoomType\RegisterSearchRoomTypeCommand;
use App\Search\Application\UseCase\RegisterSearchRoomType\RegisterSearchRoomTypeCommandHandler;
use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Domain\ValueObject\HotelId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegisterSearchRoomTypeCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesUpsertToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('upsertRoomType')
            ->with('rt-id-1', new HotelId('hotel-id-1'), 'Standard', 2, [['type' => 'double', 'count' => 1]]);

        $handler = new RegisterSearchRoomTypeCommandHandler($writer);
        ($handler)(new RegisterSearchRoomTypeCommand(
            roomTypeId: 'rt-id-1',
            hotelId: new HotelId('hotel-id-1'),
            name: 'Standard',
            guestCapacity: 2,
            bedComposition: [['type' => 'double', 'count' => 1]],
        ));
    }
}

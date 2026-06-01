<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\UpdateSearchRoomTypeAmenities;

use App\Search\Application\UseCase\UpdateSearchRoomTypeAmenities\UpdateSearchRoomTypeAmenitiesCommand;
use App\Search\Application\UseCase\UpdateSearchRoomTypeAmenities\UpdateSearchRoomTypeAmenitiesCommandHandler;
use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UpdateSearchRoomTypeAmenitiesCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesAmenitiesUpdateToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('updateRoomAmenities')
            ->with('rt-id-1', ['wifi', 'tv']);

        $handler = new UpdateSearchRoomTypeAmenitiesCommandHandler($writer);
        ($handler)(new UpdateSearchRoomTypeAmenitiesCommand(roomTypeId: 'rt-id-1', amenities: ['wifi', 'tv']));
    }
}

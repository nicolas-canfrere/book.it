<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\UpdateSearchHotelAmenities;

use App\Search\Application\UseCase\UpdateSearchHotelAmenities\UpdateSearchHotelAmenitiesCommand;
use App\Search\Application\UseCase\UpdateSearchHotelAmenities\UpdateSearchHotelAmenitiesCommandHandler;
use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UpdateSearchHotelAmenitiesCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesAmenitiesUpdateToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('updateHotelAmenities')
            ->with('hotel-id-1', ['pool', 'gym']);

        $handler = new UpdateSearchHotelAmenitiesCommandHandler($writer);
        ($handler)(new UpdateSearchHotelAmenitiesCommand(hotelId: 'hotel-id-1', amenities: ['pool', 'gym']));
    }
}

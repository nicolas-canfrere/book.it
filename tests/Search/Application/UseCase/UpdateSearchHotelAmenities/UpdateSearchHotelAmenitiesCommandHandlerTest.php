<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\UpdateSearchHotelAmenities;

use App\Search\Application\UseCase\UpdateSearchHotelAmenities\UpdateSearchHotelAmenitiesCommand;
use App\Search\Application\UseCase\UpdateSearchHotelAmenities\UpdateSearchHotelAmenitiesCommandHandler;
use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Domain\ValueObject\HotelId;
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
            ->with(new HotelId('hotel-id-1'), ['pool', 'gym']);

        $handler = new UpdateSearchHotelAmenitiesCommandHandler($writer);
        ($handler)(new UpdateSearchHotelAmenitiesCommand(hotelId: new HotelId('hotel-id-1'), amenities: ['pool', 'gym']));
    }
}

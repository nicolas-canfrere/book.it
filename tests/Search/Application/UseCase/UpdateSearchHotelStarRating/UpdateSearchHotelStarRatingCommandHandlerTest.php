<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\UpdateSearchHotelStarRating;

use App\Search\Application\UseCase\UpdateSearchHotelStarRating\UpdateSearchHotelStarRatingCommand;
use App\Search\Application\UseCase\UpdateSearchHotelStarRating\UpdateSearchHotelStarRatingCommandHandler;
use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Domain\ValueObject\HotelId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UpdateSearchHotelStarRatingCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesStarRatingUpdateToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('updateStarRating')
            ->with(new HotelId('hotel-id-1'), 4);

        $handler = new UpdateSearchHotelStarRatingCommandHandler($writer);
        ($handler)(new UpdateSearchHotelStarRatingCommand(hotelId: new HotelId('hotel-id-1'), starRating: 4));
    }

    #[Test]
    public function itDelegatesNullStarRatingToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('updateStarRating')
            ->with(new HotelId('hotel-id-1'), null);

        $handler = new UpdateSearchHotelStarRatingCommandHandler($writer);
        ($handler)(new UpdateSearchHotelStarRatingCommand(hotelId: new HotelId('hotel-id-1'), starRating: null));
    }
}

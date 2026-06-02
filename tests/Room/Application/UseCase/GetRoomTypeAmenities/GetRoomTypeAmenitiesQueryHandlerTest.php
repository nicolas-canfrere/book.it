<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\GetRoomTypeAmenities;

use App\Room\Application\UseCase\GetRoomTypeAmenities\GetRoomTypeAmenitiesQuery;
use App\Room\Application\UseCase\GetRoomTypeAmenities\GetRoomTypeAmenitiesQueryHandler;
use App\Room\Domain\ValueObject\RoomAmenity;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetRoomTypeAmenitiesQueryHandlerTest extends TestCase
{
    #[Test]
    public function itReturnsAllRoomAmenityValues(): void
    {
        $handler = new GetRoomTypeAmenitiesQueryHandler();

        $result = ($handler)(new GetRoomTypeAmenitiesQuery());

        self::assertSame(RoomAmenity::values(), $result);
        self::assertContains('wifi', $result);
        self::assertContains('balcony', $result);
    }
}

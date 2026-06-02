<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\GetHotelAmenities;

use App\Hotel\Application\UseCase\GetHotelAmenities\GetHotelAmenitiesQuery;
use App\Hotel\Application\UseCase\GetHotelAmenities\GetHotelAmenitiesQueryHandler;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetHotelAmenitiesQueryHandlerTest extends TestCase
{
    #[Test]
    public function itReturnsAllHotelAmenityValues(): void
    {
        $handler = new GetHotelAmenitiesQueryHandler();

        $result = ($handler)(new GetHotelAmenitiesQuery());

        self::assertSame(HotelAmenity::values(), $result);
        self::assertContains('pool', $result);
        self::assertContains('parking', $result);
    }
}

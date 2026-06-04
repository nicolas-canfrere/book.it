<?php

declare(strict_types=1);

namespace Tests\Room\Infrastructure\Persistence\Doctrine;

use App\Hotel\Application\Contract\HotelFinderInterface;
use App\Hotel\Application\Contract\HotelView;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Infrastructure\Persistence\Doctrine\HotelExistenceChecker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class HotelExistenceCheckerTest extends TestCase
{
    private HotelFinderInterface&Stub $hotelFinder;
    private HotelExistsInterface $checker;

    protected function setUp(): void
    {
        $this->hotelFinder = $this->createStub(HotelFinderInterface::class);
        $this->checker = new HotelExistenceChecker($this->hotelFinder);
    }

    #[Test]
    public function itReturnsTrueWhenHotelExists(): void
    {
        $this->hotelFinder->method('find')->willReturn(new HotelView('hotel-1'));

        self::assertTrue($this->checker->exists('hotel-1'));
    }

    #[Test]
    public function itReturnsFalseWhenHotelDoesNotExist(): void
    {
        $this->hotelFinder->method('find')->willReturn(null);

        self::assertFalse($this->checker->exists('unknown'));
    }
}

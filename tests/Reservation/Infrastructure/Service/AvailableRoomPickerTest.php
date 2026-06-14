<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;
use App\Reservation\Infrastructure\Service\AvailableRoomPicker;
use App\Room\Application\Contract\RoomsByTypeFinderInterface;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AvailableRoomPickerTest extends TestCase
{
    private RoomTypeId $roomTypeId;
    private \DateTimeImmutable $checkIn;
    private \DateTimeImmutable $checkOut;

    protected function setUp(): void
    {
        $this->roomTypeId = new RoomTypeId('aaaaaaaa-0000-4000-8000-000000000001');
        $this->checkIn = new \DateTimeImmutable('2026-08-01');
        $this->checkOut = new \DateTimeImmutable('2026-08-05');
    }

    #[Test]
    public function itReturnsFirstAvailableRoom(): void
    {
        $roomId1 = new RoomId('bbbbbbbb-0000-4000-8000-000000000001');
        $roomId2 = new RoomId('bbbbbbbb-0000-4000-8000-000000000002');

        $roomsByTypeFinder = $this->createMock(RoomsByTypeFinderInterface::class);
        $roomsByTypeFinder->method('findByType')->willReturn([$roomId1, $roomId2]);

        $availabilityChecker = $this->createMock(RoomAvailabilityCheckerInterface::class);
        $availabilityChecker
            ->method('isAvailable')
            ->willReturnMap([
                [$roomId1, $this->checkIn, $this->checkOut, false],
                [$roomId2, $this->checkIn, $this->checkOut, true],
            ]);

        $picker = new AvailableRoomPicker($roomsByTypeFinder, $availabilityChecker);
        $result = $picker->pick($this->roomTypeId, $this->checkIn, $this->checkOut);

        $this->assertEquals($roomId2, $result);
    }

    #[Test]
    public function itReturnsNullWhenNoRoomAvailable(): void
    {
        $roomId = new RoomId('bbbbbbbb-0000-4000-8000-000000000001');

        $roomsByTypeFinder = $this->createMock(RoomsByTypeFinderInterface::class);
        $roomsByTypeFinder->method('findByType')->willReturn([$roomId]);

        $availabilityChecker = $this->createMock(RoomAvailabilityCheckerInterface::class);
        $availabilityChecker->method('isAvailable')->willReturn(false);

        $picker = new AvailableRoomPicker($roomsByTypeFinder, $availabilityChecker);
        $result = $picker->pick($this->roomTypeId, $this->checkIn, $this->checkOut);

        $this->assertNull($result);
    }

    #[Test]
    public function itReturnsNullWhenRoomTypeHasNoRooms(): void
    {
        $roomsByTypeFinder = $this->createMock(RoomsByTypeFinderInterface::class);
        $roomsByTypeFinder->method('findByType')->willReturn([]);

        $availabilityChecker = $this->createMock(RoomAvailabilityCheckerInterface::class);
        $availabilityChecker->expects($this->never())->method('isAvailable');

        $picker = new AvailableRoomPicker($roomsByTypeFinder, $availabilityChecker);
        $result = $picker->pick($this->roomTypeId, $this->checkIn, $this->checkOut);

        $this->assertNull($result);
    }
}

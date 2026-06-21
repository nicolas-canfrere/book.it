<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\ListRooms;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomPage;
use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use App\Room\UI\Http\Controller\ListRooms\RoomCatalogueSerializer;
use App\Room\UI\Http\Controller\RoomSerializer;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RoomCatalogueSerializerTest extends TestCase
{
    private RoomCatalogueSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new RoomCatalogueSerializer(new RoomSerializer());
    }

    #[Test]
    public function itIncludesBaseRateAmountCentsWhenProvided(): void
    {
        $room = $this->makeRoom('room-1');
        $roomPage = new RoomPage([$room], 1);

        $result = $this->serializer->serialize($roomPage, ['room-1' => 12000], 1, 20);

        self::assertSame(12000, $result['data'][0]['baseRateAmountCents']);
    }

    #[Test]
    public function itReturnsNullBaseRateAmountCentsWhenNotProvided(): void
    {
        $room = $this->makeRoom('room-1');
        $roomPage = new RoomPage([$room], 1);

        $result = $this->serializer->serialize($roomPage, [], 1, 20);

        self::assertNull($result['data'][0]['baseRateAmountCents']);
    }

    private function makeRoom(string $id): Room
    {
        return new Room(
            new RoomId($id),
            new HotelId('550e8400-e29b-41d4-a716-446655440000'),
            new RoomNumber('101'),
            new RoomFloor(1),
            new RoomTypeId('cccccccc-0000-4000-8000-000000000001'),
            new \DateTimeImmutable('2024-01-01'),
        );
    }
}

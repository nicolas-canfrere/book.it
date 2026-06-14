<?php

declare(strict_types=1);

namespace App\Tests\Room\Infrastructure\Contract;

use App\Room\Infrastructure\Contract\DoctrineRoomsByTypeFinder;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineRoomsByTypeFinderTest extends TestCase
{
    #[Test]
    public function itReturnsRoomIdsForGivenType(): void
    {
        $roomTypeId = new RoomTypeId('aaaaaaaa-0000-4000-8000-000000000001');
        $roomId1 = 'bbbbbbbb-0000-4000-8000-000000000001';
        $roomId2 = 'bbbbbbbb-0000-4000-8000-000000000002';

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                'SELECT id FROM rooms WHERE room_type_id = :roomTypeId',
                ['roomTypeId' => $roomTypeId->value],
            )
            ->willReturn([['id' => $roomId1], ['id' => $roomId2]]);

        $finder = new DoctrineRoomsByTypeFinder($connection);
        $result = $finder->findByType($roomTypeId);

        $this->assertCount(2, $result);
        $this->assertEquals(new RoomId($roomId1), $result[0]);
        $this->assertEquals(new RoomId($roomId2), $result[1]);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoRoomsForType(): void
    {
        $roomTypeId = new RoomTypeId('aaaaaaaa-0000-4000-8000-000000000001');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $finder = new DoctrineRoomsByTypeFinder($connection);
        $result = $finder->findByType($roomTypeId);

        $this->assertSame([], $result);
    }
}

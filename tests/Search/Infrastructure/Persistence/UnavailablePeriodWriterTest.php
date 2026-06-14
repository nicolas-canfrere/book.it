<?php

declare(strict_types=1);

namespace App\Tests\Search\Infrastructure\Persistence;

use App\Search\Infrastructure\Persistence\UnavailablePeriodWriter;
use App\Shared\Domain\ValueObject\RoomId;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UnavailablePeriodWriterTest extends TestCase
{
    #[Test]
    public function itInsertsUnavailablePeriodLookingUpRoomIndex(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                'SELECT room_type_id, hotel_id FROM room_index WHERE room_id = :roomId',
                ['roomId' => 'room-id-1'],
            )
            ->willReturn(['room_type_id' => 'rt-id-1', 'hotel_id' => 'hotel-id-1']);

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('INSERT INTO unavailable_periods'),
                $this->callback(static function (array $p): bool {
                    return 'source-id-1' === $p['id']
                        && 'room-id-1' === $p['roomId']
                        && 'rt-id-1' === $p['roomTypeId']
                        && 'hotel-id-1' === $p['hotelId']
                        && '2026-07-01' === $p['checkIn']
                        && '2026-07-05' === $p['checkOut']
                        && 'source-id-1' === $p['sourceId'];
                }),
            );

        (new UnavailablePeriodWriter($connection))->add(
            'source-id-1',
            new RoomId('room-id-1'),
            new \DateTimeImmutable('2026-07-01'),
            new \DateTimeImmutable('2026-07-05'),
        );
    }

    #[Test]
    public function itSkipsInsertWhenRoomNotIndexed(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        $connection->expects($this->never())->method('executeStatement');

        (new UnavailablePeriodWriter($connection))->add(
            'source-id-1',
            new RoomId('unknown-room'),
            new \DateTimeImmutable('2026-07-01'),
            new \DateTimeImmutable('2026-07-05'),
        );
    }

    #[Test]
    public function itRemovesByPeriod(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('DELETE FROM unavailable_periods'),
                ['roomId' => 'room-id-1', 'checkIn' => '2026-07-01', 'checkOut' => '2026-07-05'],
            );

        (new UnavailablePeriodWriter($connection))->removeByPeriod(
            new RoomId('room-id-1'),
            new \DateTimeImmutable('2026-07-01'),
            new \DateTimeImmutable('2026-07-05'),
        );
    }

    #[Test]
    public function itRemovesBySource(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'DELETE FROM unavailable_periods WHERE source_id = :sourceId',
                ['sourceId' => 'res-id-1'],
            );

        (new UnavailablePeriodWriter($connection))->removeBySource('res-id-1');
    }
}

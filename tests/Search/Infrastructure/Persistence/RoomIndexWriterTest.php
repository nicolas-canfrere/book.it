<?php

declare(strict_types=1);

namespace App\Tests\Search\Infrastructure\Persistence;

use App\Search\Infrastructure\Persistence\RoomIndexWriter;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RoomIndexWriterTest extends TestCase
{
    #[Test]
    public function itUpsertsRoom(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('INSERT INTO room_index'),
                ['roomId' => 'room-id-1', 'roomTypeId' => 'rt-id-1', 'hotelId' => 'hotel-id-1'],
            );

        (new RoomIndexWriter($connection))->upsert('room-id-1', 'rt-id-1', 'hotel-id-1');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\GetRoomCapacity;

use App\Room\Application\UseCase\GetRoomCapacity\GetRoomCapacityQuery;
use App\Room\Application\UseCase\GetRoomCapacity\GetRoomCapacityQueryHandler;
use App\Tests\Room\Infrastructure\FakeRoomCapacityFinder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetRoomCapacityQueryHandlerTest extends TestCase
{
    private const string ROOM_ID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    private FakeRoomCapacityFinder $finder;
    private GetRoomCapacityQueryHandler $handler;

    protected function setUp(): void
    {
        $this->finder = new FakeRoomCapacityFinder();
        $this->handler = new GetRoomCapacityQueryHandler($this->finder);
    }

    #[Test]
    public function itReturnsTheGuestCapacityOfTheRoom(): void
    {
        $this->finder->withCapacity(self::ROOM_ID, 2);

        $result = ($this->handler)(new GetRoomCapacityQuery(self::ROOM_ID));

        self::assertSame(2, $result);
    }

    #[Test]
    public function itReturnsZeroWhenRoomNotFound(): void
    {
        $result = ($this->handler)(new GetRoomCapacityQuery('00000000-0000-4000-8000-000000000000'));

        self::assertSame(0, $result);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\GetRoomType;

use App\Room\Application\UseCase\GetRoomType\GetRoomTypeQuery;
use App\Room\Application\UseCase\GetRoomType\GetRoomTypeQueryHandler;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommandHandler;
use App\Shared\Domain\ValueObject\RoomTypeId;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetRoomTypeQueryHandlerTest extends TestCase
{
    private InMemoryRoomTypeRepository $repository;
    private GetRoomTypeQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRoomTypeRepository();
        $this->handler = new GetRoomTypeQueryHandler($this->repository);

        $registerHandler = new RegisterRoomTypeCommandHandler($this->repository, new FakeHotelExistenceChecker(), new FakeEventDispatcher());
        ($registerHandler)(new RegisterRoomTypeCommand(
            id: new RoomTypeId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'),
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            name: 'Single',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 1,
            isAccessible: false,
            bedEntries: [['type' => 'single', 'count' => 1]],
            createdAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
        ));
    }

    #[Test]
    public function itReturnsTheRoomType(): void
    {
        $result = ($this->handler)(new GetRoomTypeQuery(new RoomTypeId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11')));
        self::assertNotNull($result);
        self::assertSame('Single', $result->name);
    }

    #[Test]
    public function itReturnsNullWhenNotFound(): void
    {
        $result = ($this->handler)(new GetRoomTypeQuery(new RoomTypeId('00000000-0000-4000-8000-000000000000')));
        self::assertNull($result);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\ListRoomTypes;

use App\Room\Application\UseCase\ListRoomTypes\ListRoomTypesQuery;
use App\Room\Application\UseCase\ListRoomTypes\ListRoomTypesQueryHandler;
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
final class ListRoomTypesQueryHandlerTest extends TestCase
{
    private InMemoryRoomTypeRepository $repository;
    private ListRoomTypesQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRoomTypeRepository();
        $this->handler = new ListRoomTypesQueryHandler($this->repository);

        $registerHandler = new RegisterRoomTypeCommandHandler($this->repository, new FakeHotelExistenceChecker(), new FakeEventDispatcher());
        foreach (['Suite', 'Double', 'Single'] as $i => $name) {
            ($registerHandler)(new RegisterRoomTypeCommand(
                id: new RoomTypeId(sprintf('a0eebc99-9c0b-4ef8-bb6d-6bb9bd38%04d', $i)),
                hotelId: '550e8400-e29b-41d4-a716-446655440000',
                name: $name,
                livingSpaceCount: 1,
                surfaceM2: null,
                guestCapacity: 1,
                isAccessible: false,
                bedEntries: [['type' => 'single', 'count' => 1]],
                createdAt: new \DateTimeImmutable(),
            ));
        }
    }

    #[Test]
    public function itReturnsRoomTypesSortedByName(): void
    {
        $page = ($this->handler)(new ListRoomTypesQuery('550e8400-e29b-41d4-a716-446655440000', 1, 20));
        self::assertSame(3, $page->total);
        self::assertSame('Double', $page->roomTypes[0]->name);
        self::assertSame('Single', $page->roomTypes[1]->name);
        self::assertSame('Suite', $page->roomTypes[2]->name);
    }

    #[Test]
    public function itPaginates(): void
    {
        $page = ($this->handler)(new ListRoomTypesQuery('550e8400-e29b-41d4-a716-446655440000', 1, 2));
        self::assertSame(3, $page->total);
        self::assertCount(2, $page->roomTypes);
    }
}

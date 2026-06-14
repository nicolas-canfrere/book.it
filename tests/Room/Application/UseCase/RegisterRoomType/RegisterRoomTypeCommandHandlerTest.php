<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\RegisterRoomType;

use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommandHandler;
use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomTypeAlreadyExistsException;
use App\Shared\Domain\Event\RoomTypeRegistered;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegisterRoomTypeCommandHandlerTest extends TestCase
{
    private InMemoryRoomTypeRepository $repository;
    private FakeHotelExistenceChecker $hotelChecker;
    private FakeEventDispatcher $dispatcher;
    private RegisterRoomTypeCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRoomTypeRepository();
        $this->hotelChecker = new FakeHotelExistenceChecker();
        $this->dispatcher = new FakeEventDispatcher();
        $this->handler = new RegisterRoomTypeCommandHandler($this->repository, $this->hotelChecker, $this->dispatcher);
    }

    #[Test]
    public function itPersistsTheRoomType(): void
    {
        ($this->handler)($this->makeCommand());

        $roomType = $this->repository->get(new RoomTypeId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));
        self::assertNotNull($roomType);
        self::assertSame('Suite Royale', $roomType->name);
        self::assertSame(2, $roomType->livingSpaceCount);
        self::assertSame(80, $roomType->surfaceM2);
        self::assertSame(2, $roomType->guestCapacity);
        self::assertFalse($roomType->isAccessible);
        self::assertSame([['type' => 'king', 'count' => 1]], $roomType->bedComposition->toArray());
    }

    #[Test]
    public function itDispatchesRoomTypeRegistered(): void
    {
        ($this->handler)($this->makeCommand());

        $event = $this->dispatcher->getLastDispatched();
        self::assertInstanceOf(RoomTypeRegistered::class, $event);
        self::assertSame('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', $event->roomTypeId);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $event->hotelId);
        self::assertSame('Suite Royale', $event->name);
        self::assertSame(2, $event->guestCapacity);
        self::assertSame([['type' => 'king', 'count' => 1]], $event->bedComposition);
    }

    #[Test]
    public function itThrowsWhenHotelDoesNotExist(): void
    {
        $this->hotelChecker->setExists(false);
        $this->expectException(HotelNotFoundException::class);

        ($this->handler)($this->makeCommand());
    }

    #[Test]
    public function itDoesNotDispatchWhenHotelNotFound(): void
    {
        $this->hotelChecker->setExists(false);

        try {
            ($this->handler)($this->makeCommand());
        } catch (HotelNotFoundException) {
            // Expected
        }

        self::assertEmpty($this->dispatcher->getDispatched());
    }

    #[Test]
    public function itThrowsWhenNameAlreadyExistsInHotel(): void
    {
        ($this->handler)($this->makeCommand('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));

        $this->expectException(RoomTypeAlreadyExistsException::class);

        ($this->handler)($this->makeCommand('b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22'));
    }

    #[Test]
    public function itAllowsSameNameInDifferentHotels(): void
    {
        ($this->handler)($this->makeCommand('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));

        $cmd2 = new RegisterRoomTypeCommand(
            id: new RoomTypeId('b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22'),
            hotelId: new HotelId('550e8400-e29b-41d4-a716-000000000001'),
            name: 'Suite Royale',
            livingSpaceCount: 2,
            surfaceM2: null,
            guestCapacity: 2,
            isAccessible: false,
            bedEntries: [['type' => 'king', 'count' => 1]],
            createdAt: new \DateTimeImmutable(),
        );
        ($this->handler)($cmd2);

        self::assertNotNull($this->repository->get(new RoomTypeId('b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22')));
    }

    private function makeCommand(string $id = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', string $name = 'Suite Royale'): RegisterRoomTypeCommand
    {
        return new RegisterRoomTypeCommand(
            id: new RoomTypeId($id),
            hotelId: new HotelId('550e8400-e29b-41d4-a716-446655440000'),
            name: $name,
            livingSpaceCount: 2,
            surfaceM2: 80,
            guestCapacity: 2,
            isAccessible: false,
            bedEntries: [['type' => 'king', 'count' => 1]],
            createdAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\RegisterRoom;

use App\Room\Application\UseCase\RegisterRoom\RegisterRoomCommand;
use App\Room\Application\UseCase\RegisterRoom\RegisterRoomCommandHandler;
use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomAlreadyExistsException;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegisterRoomCommandHandlerTest extends TestCase
{
    private InMemoryRoomRepository $roomRepository;
    private FakeHotelExistenceChecker $hotelExistenceChecker;
    private RegisterRoomCommandHandler $handler;

    protected function setUp(): void
    {
        $this->roomRepository = new InMemoryRoomRepository();
        $this->hotelExistenceChecker = new FakeHotelExistenceChecker();
        $this->handler = new RegisterRoomCommandHandler($this->roomRepository, $this->hotelExistenceChecker);
    }

    #[Test]
    public function itPersistsTheRoom(): void
    {
        $command = new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 1,
            createdAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
        );

        ($this->handler)($command);

        $room = $this->roomRepository->get($command->id);
        self::assertNotNull($room);
        self::assertSame($command->id, $room->id);
        self::assertSame($command->hotelId, $room->hotelId);
        self::assertSame('101', $room->number->value);
        self::assertSame(1, $room->floor->value);
        self::assertEquals($command->createdAt, $room->createdAt);
    }

    #[Test]
    public function itThrowsWhenHotelDoesNotExist(): void
    {
        $this->hotelExistenceChecker->setExists(false);
        $this->expectException(HotelNotFoundException::class);

        ($this->handler)(new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 1,
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itThrowsWhenRoomNumberAlreadyExistsInHotel(): void
    {
        $command = new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 1,
            createdAt: new \DateTimeImmutable(),
        );
        ($this->handler)($command);

        $this->expectException(RoomAlreadyExistsException::class);

        ($this->handler)(new RegisterRoomCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 2,
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itAllowsSameNumberInDifferentHotels(): void
    {
        $command1 = new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440001',
            number: '101',
            floor: 1,
            createdAt: new \DateTimeImmutable(),
        );
        $command2 = new RegisterRoomCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            hotelId: '550e8400-e29b-41d4-a716-446655440002',
            number: '101',
            floor: 1,
            createdAt: new \DateTimeImmutable(),
        );

        ($this->handler)($command1);
        ($this->handler)($command2);

        self::assertNotNull($this->roomRepository->get($command1->id));
        self::assertNotNull($this->roomRepository->get($command2->id));
    }
}

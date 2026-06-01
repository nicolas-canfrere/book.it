<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\GetAvailabilityCalendar;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommandHandler;
use App\Availability\Application\UseCase\GetAvailabilityCalendar\GetAvailabilityCalendarQuery;
use App\Availability\Application\UseCase\GetAvailabilityCalendar\GetAvailabilityCalendarQueryHandler;
use App\Tests\Availability\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Availability\Infrastructure\Persistence\InMemory\InMemoryBlockedPeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class GetAvailabilityCalendarQueryHandlerTest extends TestCase
{
    private const string ROOM_ID = '550e8400-e29b-41d4-a716-446655440000';

    private InMemoryBlockedPeriodRepository $repository;
    private GetAvailabilityCalendarQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryBlockedPeriodRepository();
        $this->handler = new GetAvailabilityCalendarQueryHandler($this->repository);

        $blockHandler = new BlockPeriodCommandHandler($this->repository, new FakeRoomExistenceChecker(), $this->createMock(EventDispatcherInterface::class));
        ($blockHandler)(new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-06-15'),
            checkOut: new \DateTimeImmutable('2025-06-18'),
            createdAt: new \DateTimeImmutable(),
        ));
        ($blockHandler)(new BlockPeriodCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itReturnsBlockedPeriodsOrderedByCheckIn(): void
    {
        $result = ($this->handler)(new GetAvailabilityCalendarQuery(self::ROOM_ID));

        self::assertCount(2, $result);
        self::assertSame('2025-06-10', $result[0]->period->checkIn->format('Y-m-d'));
        self::assertSame('2025-06-15', $result[1]->period->checkIn->format('Y-m-d'));
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoBlocks(): void
    {
        $result = ($this->handler)(new GetAvailabilityCalendarQuery('00000000-0000-4000-8000-000000000000'));

        self::assertSame([], $result);
    }
}

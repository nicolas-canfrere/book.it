<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\SetBaseRate;

use App\Pricing\Application\UseCase\SetBaseRate\SetBaseRateCommand;
use App\Pricing\Application\UseCase\SetBaseRate\SetBaseRateCommandHandler;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;
use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Shared\Domain\Event\BaseRateSet;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class SetBaseRateCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesBaseRateSet(): void
    {
        $repository = $this->createMock(BaseRateRepositoryInterface::class);
        $repository->expects($this->once())->method('save');
        $roomExists = $this->createStub(RoomExistsInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $roomExists->method('exists')->willReturn(true);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof BaseRateSet
                    && 'room-id-1' === $event->roomId
                    && 15000 === $event->amountCents;
            }));

        $handler = new SetBaseRateCommandHandler($repository, $roomExists, $dispatcher);

        ($handler)(new SetBaseRateCommand(
            roomId: new RoomId('room-id-1'),
            amountCents: 15000,
            updatedAt: new \DateTimeImmutable('2026-06-01T00:00:00Z'),
        ));
    }

    #[Test]
    public function itDoesNotDispatchWhenRoomNotFound(): void
    {
        $repository = $this->createStub(BaseRateRepositoryInterface::class);
        $roomExists = $this->createStub(RoomExistsInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $roomExists->method('exists')->willReturn(false);
        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new SetBaseRateCommandHandler($repository, $roomExists, $dispatcher);

        $this->expectException(\App\Pricing\Domain\Exception\RoomNotFoundException::class);

        ($handler)(new SetBaseRateCommand(
            roomId: new RoomId('missing-room'),
            amountCents: 15000,
            updatedAt: new \DateTimeImmutable('2026-06-01T00:00:00Z'),
        ));
    }
}

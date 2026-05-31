<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\SetCancellationPolicy;

use App\Pricing\Application\UseCase\SetCancellationPolicy\SetCancellationPolicyCommand;
use App\Pricing\Application\UseCase\SetCancellationPolicy\SetCancellationPolicyCommandHandler;
use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryCancellationPolicyRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SetCancellationPolicyCommandHandlerTest extends TestCase
{
    private InMemoryCancellationPolicyRepository $repository;
    private SetCancellationPolicyCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryCancellationPolicyRepository();
        $this->handler = $this->makeHandler(true);
    }

    #[Test]
    public function itCreatesCancellationPolicy(): void
    {
        $roomId = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

        ($this->handler)(new SetCancellationPolicyCommand($roomId, 14));

        $policy = $this->repository->findByRoomId($roomId);
        self::assertNotNull($policy);
        self::assertSame($roomId, $policy->roomId);
        self::assertSame(14, $policy->daysThreshold);
    }

    #[Test]
    public function itUpsetsExistingPolicy(): void
    {
        $roomId = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

        ($this->handler)(new SetCancellationPolicyCommand($roomId, 7));
        ($this->handler)(new SetCancellationPolicyCommand($roomId, 30));

        $policy = $this->repository->findByRoomId($roomId);
        self::assertNotNull($policy);
        self::assertSame(30, $policy->daysThreshold);
    }

    #[Test]
    public function itThrowsWhenRoomDoesNotExist(): void
    {
        $handler = $this->makeHandler(false);

        $this->expectException(RoomNotFoundException::class);
        ($handler)(new SetCancellationPolicyCommand('f47ac10b-58cc-4372-a567-0e02b2c3d479', 14));
    }

    private function makeHandler(bool $roomExists): SetCancellationPolicyCommandHandler
    {
        $mock = $this->createStub(RoomExistsInterface::class);
        $mock->method('exists')->willReturn($roomExists);

        return new SetCancellationPolicyCommandHandler($this->repository, $mock);
    }
}

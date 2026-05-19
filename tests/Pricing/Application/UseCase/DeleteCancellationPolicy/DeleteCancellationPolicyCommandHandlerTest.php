<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\DeleteCancellationPolicy;

use App\Pricing\Application\UseCase\DeleteCancellationPolicy\DeleteCancellationPolicyCommand;
use App\Pricing\Application\UseCase\DeleteCancellationPolicy\DeleteCancellationPolicyCommandHandler;
use App\Pricing\Domain\Exception\CancellationPolicyNotFoundException;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryCancellationPolicyRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeleteCancellationPolicyCommandHandlerTest extends TestCase
{
    private InMemoryCancellationPolicyRepository $repository;
    private DeleteCancellationPolicyCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryCancellationPolicyRepository();
        $this->handler = new DeleteCancellationPolicyCommandHandler($this->repository);
    }

    #[Test]
    public function itDeletesExistingPolicy(): void
    {
        $roomId = '550e8400-e29b-41d4-a716-446655440000';

        $this->repository->save(new CancellationPolicy(
            roomId: $roomId,
            daysThreshold: 3,
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        ($this->handler)(new DeleteCancellationPolicyCommand(roomId: $roomId));

        self::assertNull($this->repository->findByRoomId($roomId));
    }

    #[Test]
    public function itThrowsWhenPolicyNotFound(): void
    {
        $this->expectException(CancellationPolicyNotFoundException::class);

        ($this->handler)(new DeleteCancellationPolicyCommand(
            roomId: '550e8400-e29b-41d4-a716-446655440000',
        ));
    }
}

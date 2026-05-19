<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\GetCancellationPolicy;

use App\Pricing\Application\UseCase\GetCancellationPolicy\GetCancellationPolicyQuery;
use App\Pricing\Application\UseCase\GetCancellationPolicy\GetCancellationPolicyQueryHandler;
use App\Pricing\Domain\Exception\CancellationPolicyNotFoundException;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryCancellationPolicyRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetCancellationPolicyQueryHandlerTest extends TestCase
{
    private InMemoryCancellationPolicyRepository $repository;
    private GetCancellationPolicyQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryCancellationPolicyRepository();
        $this->handler = new GetCancellationPolicyQueryHandler($this->repository);
    }

    #[Test]
    public function itReturnsExistingPolicy(): void
    {
        $roomId = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

        $this->repository->save(new CancellationPolicy(
            roomId: $roomId,
            daysThreshold: 14,
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        $result = ($this->handler)(new GetCancellationPolicyQuery($roomId));

        self::assertSame($roomId, $result->roomId);
        self::assertSame(14, $result->daysThreshold);
    }

    #[Test]
    public function itThrowsWhenPolicyNotFound(): void
    {
        $this->expectException(CancellationPolicyNotFoundException::class);

        ($this->handler)(new GetCancellationPolicyQuery('f47ac10b-58cc-4372-a567-0e02b2c3d479'));
    }
}

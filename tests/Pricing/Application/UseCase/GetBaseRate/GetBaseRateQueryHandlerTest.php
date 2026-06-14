<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\GetBaseRate;

use App\Pricing\Application\UseCase\GetBaseRate\GetBaseRateQuery;
use App\Pricing\Application\UseCase\GetBaseRate\GetBaseRateQueryHandler;
use App\Pricing\Domain\Exception\BaseRateNotFoundException;
use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Pricing\Domain\Model\BaseRate;
use App\Shared\Domain\ValueObject\RoomId;
use App\Tests\Pricing\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Pricing\Infrastructure\Persistence\InMemory\InMemoryBaseRateRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetBaseRateQueryHandlerTest extends TestCase
{
    private InMemoryBaseRateRepository $repository;
    private FakeRoomExistenceChecker $roomExists;
    private GetBaseRateQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryBaseRateRepository();
        $this->roomExists = new FakeRoomExistenceChecker();
        $this->handler = new GetBaseRateQueryHandler($this->repository, $this->roomExists);
    }

    #[Test]
    public function itReturnsBaseRateForRoom(): void
    {
        $roomId = new RoomId('550e8400-e29b-41d4-a716-446655440000');

        $this->repository->save(new BaseRate(
            roomId: $roomId,
            amountCents: 15000,
            updatedAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));

        $result = ($this->handler)(new GetBaseRateQuery($roomId));

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $result->roomId->value);
        self::assertSame(15000, $result->amountCents);
    }

    #[Test]
    public function itThrowsWhenRoomDoesNotExist(): void
    {
        $this->roomExists->setExists(false);
        $this->expectException(RoomNotFoundException::class);

        ($this->handler)(new GetBaseRateQuery(new RoomId('550e8400-e29b-41d4-a716-446655440000')));
    }

    #[Test]
    public function itThrowsWhenBaseRateNotConfigured(): void
    {
        $this->expectException(BaseRateNotFoundException::class);

        ($this->handler)(new GetBaseRateQuery(new RoomId('550e8400-e29b-41d4-a716-446655440000')));
    }
}

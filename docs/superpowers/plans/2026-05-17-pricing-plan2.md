# Pricing Plan 2 — Promotions & Pricing Quote Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the Promotion CRUD endpoints and the Pricing Quote calculation endpoint for the `Pricing` bounded context.

**Architecture:** Plan 2 builds on top of the Plan 1 infrastructure (BaseRate, RatePeriod, DoctrineRepositories, DI config in `pricing.yaml`). It adds a new `Promotion` aggregate with its own DBAL repository, plus a `GetPricingQuote` use case that computes a per-night price breakdown by combining BaseRate, RatePeriods, and Promotions.

**Tech Stack:** PHP 8.4, Symfony 8.0, PostgreSQL 16 (DBAL named connection `bookit`), Symfony Messenger (sync buses), PHPUnit functional tests via `dama/doctrine-test-bundle`.

**Pre-condition:** Plan 1 is fully implemented and all Plan 1 tests pass (`make test`).

---

## File Map

### New files — Domain

| Path | Responsibility |
|---|---|
| `src/Pricing/Domain/ValueObject/DiscountPercent.php` | Wraps `int` 1–99, throws on invalid value |
| `src/Pricing/Domain/Model/Promotion.php` | Promotion entity (mutable) |
| `src/Pricing/Domain/Port/PromotionRepositoryInterface.php` | Repository contract |
| `src/Pricing/Domain/Exception/PromotionNotFoundException.php` | 404 |
| `src/Pricing/Domain/Exception/PromotionOverlapException.php` | 409 |
| `src/Pricing/Domain/Exception/RoomHasNoBaseRateException.php` | 422 |

### New files — Application

| Path | Responsibility |
|---|---|
| `src/Pricing/Application/UseCase/CreatePromotion/CreatePromotionCommand.php` | Command DTO |
| `src/Pricing/Application/UseCase/CreatePromotion/CreatePromotionCommandHandler.php` | Creates and persists a Promotion |
| `src/Pricing/Application/UseCase/GetPromotions/GetPromotionsQuery.php` | Query DTO |
| `src/Pricing/Application/UseCase/GetPromotions/GetPromotionsQueryHandler.php` | Returns list of Promotions for a Room |
| `src/Pricing/Application/UseCase/UpdatePromotion/UpdatePromotionCommand.php` | Command DTO |
| `src/Pricing/Application/UseCase/UpdatePromotion/UpdatePromotionCommandHandler.php` | Updates a Promotion |
| `src/Pricing/Application/UseCase/DeletePromotion/DeletePromotionCommand.php` | Command DTO |
| `src/Pricing/Application/UseCase/DeletePromotion/DeletePromotionCommandHandler.php` | Deletes a Promotion |
| `src/Pricing/Application/UseCase/GetPricingQuote/GetPricingQuoteQuery.php` | Query DTO (roomId + checkIn/checkOut as DateTimeImmutable) |
| `src/Pricing/Application/UseCase/GetPricingQuote/GetPricingQuoteQueryHandler.php` | Computes per-night breakdown |
| `src/Pricing/Application/Service/CreatePromotionCommandFactory.php` | Injects IdGenerator + Clock |
| `src/Pricing/Application/Service/UpdatePromotionCommandFactory.php` | Injects Clock |

### New files — Infrastructure

| Path | Responsibility |
|---|---|
| `src/Pricing/Infrastructure/Persistence/Doctrine/DoctrinePromotionRepository.php` | Raw SQL DBAL implementation |
| `migrations/Version{timestamp}.php` | Creates `pricing_promotion` table |

### New files — UI

| Path | Responsibility |
|---|---|
| `src/Pricing/UI/Http/Controller/PromotionSerializer.php` | Serializes Promotion to array |
| `src/Pricing/UI/Http/Controller/CreatePromotion/CreatePromotionController.php` | POST /api/rooms/{roomId}/promotions |
| `src/Pricing/UI/Http/Controller/CreatePromotion/CreatePromotionRequest.php` | Request DTO (MapRequestPayload) |
| `src/Pricing/UI/Http/Controller/GetPromotions/GetPromotionsController.php` | GET /api/rooms/{roomId}/promotions |
| `src/Pricing/UI/Http/Controller/UpdatePromotion/UpdatePromotionController.php` | PUT /api/rooms/{roomId}/promotions/{id} |
| `src/Pricing/UI/Http/Controller/UpdatePromotion/UpdatePromotionRequest.php` | Request DTO |
| `src/Pricing/UI/Http/Controller/DeletePromotion/DeletePromotionController.php` | DELETE /api/rooms/{roomId}/promotions/{id} |
| `src/Pricing/UI/Http/Controller/GetPricingQuote/GetPricingQuoteController.php` | GET /api/rooms/{roomId}/pricing-quote |
| `src/Pricing/UI/Http/Controller/GetPricingQuote/GetPricingQuoteRequest.php` | Query string DTO (MapQueryString) |

### New files — Tests

| Path | Responsibility |
|---|---|
| `tests/Pricing/Infrastructure/Persistence/Doctrine/InMemoryPromotionRepository.php` | In-memory implementation for unit tests |
| `tests/Pricing/Application/UseCase/CreatePromotion/CreatePromotionCommandHandlerTest.php` | Unit tests |
| `tests/Pricing/Application/UseCase/GetPromotions/GetPromotionsQueryHandlerTest.php` | Unit tests |
| `tests/Pricing/Application/UseCase/UpdatePromotion/UpdatePromotionCommandHandlerTest.php` | Unit tests |
| `tests/Pricing/Application/UseCase/DeletePromotion/DeletePromotionCommandHandlerTest.php` | Unit tests |
| `tests/Pricing/Application/UseCase/GetPricingQuote/GetPricingQuoteQueryHandlerTest.php` | Unit tests |
| `tests/Pricing/UI/Http/Controller/CreatePromotionControllerTest.php` | Functional tests |
| `tests/Pricing/UI/Http/Controller/GetPromotionsControllerTest.php` | Functional tests |
| `tests/Pricing/UI/Http/Controller/UpdatePromotionControllerTest.php` | Functional tests |
| `tests/Pricing/UI/Http/Controller/DeletePromotionControllerTest.php` | Functional tests |
| `tests/Pricing/UI/Http/Controller/GetPricingQuoteControllerTest.php` | Functional tests |

### Existing files to modify

| Path | Change |
|---|---|
| `config/services/pricing.yaml` | Add Promotion repository alias + factory services |
| `config/services/exceptions.yaml` | Add 3 new exception → HTTP mappings |

---

## Task 0: Create feature branch

- [ ] **Create and switch to the feature branch**

```bash
git checkout -b feat/pricing-plan-2
```

- [ ] **Verify you are on the new branch**

```bash
git branch --show-current
# Expected: feat/pricing-plan-2
```

---

## Task 1: Domain — DiscountPercent, Promotion, PromotionRepositoryInterface, exceptions

**Files:**
- Create: `src/Pricing/Domain/ValueObject/DiscountPercent.php`
- Create: `src/Pricing/Domain/Model/Promotion.php`
- Create: `src/Pricing/Domain/Port/PromotionRepositoryInterface.php`
- Create: `src/Pricing/Domain/Exception/PromotionNotFoundException.php`
- Create: `src/Pricing/Domain/Exception/PromotionOverlapException.php`
- Create: `src/Pricing/Domain/Exception/RoomHasNoBaseRateException.php`

- [ ] **Create `DiscountPercent` value object**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

final class DiscountPercent
{
    public readonly int $value;

    public function __construct(int $value)
    {
        if ($value < 1 || $value > 99) {
            throw new \InvalidArgumentException(
                sprintf('Discount percent must be between 1 and 99, %d given.', $value)
            );
        }
        $this->value = $value;
    }
}
```

- [ ] **Create `Promotion` entity**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

final class Promotion
{
    public function __construct(
        public readonly string $id,
        public readonly string $roomId,
        private \DateTimeImmutable $checkIn,
        private \DateTimeImmutable $checkOut,
        private int $discountPercent,
        public readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    public function getCheckIn(): \DateTimeImmutable
    {
        return $this->checkIn;
    }

    public function getCheckOut(): \DateTimeImmutable
    {
        return $this->checkOut;
    }

    public function getDiscountPercent(): int
    {
        return $this->discountPercent;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
        int $discountPercent,
        \DateTimeImmutable $updatedAt,
    ): void {
        $this->checkIn = $checkIn;
        $this->checkOut = $checkOut;
        $this->discountPercent = $discountPercent;
        $this->updatedAt = $updatedAt;
    }
}
```

- [ ] **Create `PromotionRepositoryInterface`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Port;

use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\ValueObject\DatePeriod;

interface PromotionRepositoryInterface
{
    public function save(Promotion $promotion): void;

    public function findById(string $id): ?Promotion;

    /** @return list<Promotion> */
    public function findByRoomId(string $roomId): array;

    public function hasOverlap(string $roomId, DatePeriod $period, ?string $excludeId = null): bool;

    public function delete(Promotion $promotion): void;
}
```

- [ ] **Create the three new exceptions**

```php
<?php
// src/Pricing/Domain/Exception/PromotionNotFoundException.php
declare(strict_types=1);

namespace App\Pricing\Domain\Exception;

final class PromotionNotFoundException extends \DomainException
{
    public function __construct(string $promotionId)
    {
        parent::__construct(sprintf('Promotion "%s" not found.', $promotionId));
    }
}
```

```php
<?php
// src/Pricing/Domain/Exception/PromotionOverlapException.php
declare(strict_types=1);

namespace App\Pricing\Domain\Exception;

final class PromotionOverlapException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The promotion period overlaps an existing promotion for this room.');
    }
}
```

```php
<?php
// src/Pricing/Domain/Exception/RoomHasNoBaseRateException.php
declare(strict_types=1);

namespace App\Pricing\Domain\Exception;

final class RoomHasNoBaseRateException extends \DomainException
{
    public function __construct(string $roomId)
    {
        parent::__construct(sprintf('Room "%s" has no base rate configured.', $roomId));
    }
}
```

- [ ] **Commit**

```bash
git add src/Pricing/Domain/
git commit -m "feat(pricing): add Promotion entity, DiscountPercent VO, PromotionRepository interface, and Plan 2 exceptions"
```

---

## Task 2: Database migration — pricing_promotion table

**Files:**
- Create: `migrations/Version{timestamp}.php` (generate via make command)

- [ ] **Generate the migration file**

```bash
make migration
# or: docker compose exec php php bin/console doctrine:migrations:generate
```

This creates an empty migration in `migrations/`. Open it and fill in `up()` and `down()`.

- [ ] **Fill in the migration content**

```php
public function up(Schema $schema): void
{
    $this->addSql(<<<'SQL'
        CREATE TABLE pricing_promotion (
            id UUID NOT NULL,
            room_id UUID NOT NULL,
            check_in DATE NOT NULL,
            check_out DATE NOT NULL,
            discount_percent SMALLINT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )
    SQL);
    $this->addSql('CREATE INDEX idx_pricing_promotion_room_id ON pricing_promotion (room_id)');
}

public function down(Schema $schema): void
{
    $this->addSql('DROP INDEX idx_pricing_promotion_room_id');
    $this->addSql('DROP TABLE pricing_promotion');
}
```

- [ ] **Run the migration**

```bash
make migrate
# or: docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

- [ ] **Commit**

```bash
git add migrations/
git commit -m "feat(pricing): add pricing_promotion table migration"
```

---

## Task 3: InMemoryPromotionRepository + DoctrinePromotionRepository

**Files:**
- Create: `tests/Pricing/Infrastructure/Persistence/Doctrine/InMemoryPromotionRepository.php`
- Create: `src/Pricing/Infrastructure/Persistence/Doctrine/DoctrinePromotionRepository.php`

- [ ] **Create `InMemoryPromotionRepository` (used by all unit tests)**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Infrastructure\Persistence\Doctrine;

use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\DatePeriod;

final class InMemoryPromotionRepository implements PromotionRepositoryInterface
{
    /** @var array<string, Promotion> */
    private array $store = [];

    public function save(Promotion $promotion): void
    {
        $this->store[$promotion->id] = $promotion;
    }

    public function findById(string $id): ?Promotion
    {
        return $this->store[$id] ?? null;
    }

    /** @return list<Promotion> */
    public function findByRoomId(string $roomId): array
    {
        $results = array_values(array_filter(
            $this->store,
            fn(Promotion $p) => $p->roomId === $roomId,
        ));

        usort($results, fn(Promotion $a, Promotion $b) => $a->getCheckIn() <=> $b->getCheckIn());

        return $results;
    }

    public function hasOverlap(string $roomId, DatePeriod $period, ?string $excludeId = null): bool
    {
        foreach ($this->store as $promotion) {
            if ($promotion->roomId !== $roomId) {
                continue;
            }
            if (null !== $excludeId && $promotion->id === $excludeId) {
                continue;
            }
            if ($promotion->getCheckIn() < $period->checkOut && $promotion->getCheckOut() > $period->checkIn) {
                return true;
            }
        }

        return false;
    }

    public function delete(Promotion $promotion): void
    {
        unset($this->store[$promotion->id]);
    }
}
```

- [ ] **Create `DoctrinePromotionRepository`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine;

use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\DatePeriod;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DoctrinePromotionRepository implements PromotionRepositoryInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.bookit_connection')]
        private Connection $connection,
    ) {}

    public function save(Promotion $promotion): void
    {
        if (null === $this->findById($promotion->id)) {
            $this->connection->insert('pricing_promotion', [
                'id' => $promotion->id,
                'room_id' => $promotion->roomId,
                'check_in' => $promotion->getCheckIn()->format('Y-m-d'),
                'check_out' => $promotion->getCheckOut()->format('Y-m-d'),
                'discount_percent' => $promotion->getDiscountPercent(),
                'created_at' => $promotion->createdAt->format('Y-m-d H:i:s'),
                'updated_at' => $promotion->getUpdatedAt()->format('Y-m-d H:i:s'),
            ]);

            return;
        }

        $this->connection->update('pricing_promotion', [
            'check_in' => $promotion->getCheckIn()->format('Y-m-d'),
            'check_out' => $promotion->getCheckOut()->format('Y-m-d'),
            'discount_percent' => $promotion->getDiscountPercent(),
            'updated_at' => $promotion->getUpdatedAt()->format('Y-m-d H:i:s'),
        ], ['id' => $promotion->id]);
    }

    public function findById(string $id): ?Promotion
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM pricing_promotion WHERE id = :id',
            ['id' => $id],
        );

        return $row ? $this->hydrate($row) : null;
    }

    /** @return list<Promotion> */
    public function findByRoomId(string $roomId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM pricing_promotion WHERE room_id = :roomId ORDER BY check_in ASC',
            ['roomId' => $roomId],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function hasOverlap(string $roomId, DatePeriod $period, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM pricing_promotion'
            .' WHERE room_id = :roomId AND check_in < :checkOut AND check_out > :checkIn';
        $params = [
            'roomId' => $roomId,
            'checkIn' => $period->checkIn->format('Y-m-d'),
            'checkOut' => $period->checkOut->format('Y-m-d'),
        ];

        if (null !== $excludeId) {
            $sql .= ' AND id != :excludeId';
            $params['excludeId'] = $excludeId;
        }

        return (int) $this->connection->fetchOne($sql, $params) > 0;
    }

    public function delete(Promotion $promotion): void
    {
        $this->connection->delete('pricing_promotion', ['id' => $promotion->id]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Promotion
    {
        return new Promotion(
            id: $row['id'],
            roomId: $row['room_id'],
            checkIn: new \DateTimeImmutable($row['check_in']),
            checkOut: new \DateTimeImmutable($row['check_out']),
            discountPercent: (int) $row['discount_percent'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }
}
```

- [ ] **Commit**

```bash
git add src/Pricing/Infrastructure/Persistence/Doctrine/DoctrinePromotionRepository.php
git add tests/Pricing/Infrastructure/Persistence/Doctrine/InMemoryPromotionRepository.php
git commit -m "feat(pricing): add DoctrinePromotionRepository and InMemoryPromotionRepository"
```

---

## Task 4: CreatePromotion use case (TDD)

**Files:**
- Create: `src/Pricing/Application/UseCase/CreatePromotion/CreatePromotionCommand.php`
- Create: `src/Pricing/Application/UseCase/CreatePromotion/CreatePromotionCommandHandler.php`
- Create: `src/Pricing/Application/Service/CreatePromotionCommandFactory.php`
- Create: `tests/Pricing/Application/UseCase/CreatePromotion/CreatePromotionCommandHandlerTest.php`

- [ ] **Write the failing unit tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\CreatePromotion;

use App\Pricing\Application\UseCase\CreatePromotion\CreatePromotionCommand;
use App\Pricing\Application\UseCase\CreatePromotion\CreatePromotionCommandHandler;
use App\Pricing\Domain\Exception\PromotionOverlapException;
use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Tests\Pricing\Infrastructure\Persistence\Doctrine\InMemoryPromotionRepository;
use App\Tests\Pricing\Infrastructure\Persistence\Doctrine\FakeRoomExistenceChecker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CreatePromotionCommandHandlerTest extends TestCase
{
    private const ROOM_ID = 'f1e2d3c4-b5a6-4978-8766-554433221100';
    private const PROMOTION_ID = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';

    private InMemoryPromotionRepository $repository;
    private FakeRoomExistenceChecker $roomExists;
    private CreatePromotionCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPromotionRepository();
        $this->roomExists = new FakeRoomExistenceChecker(true);
        $this->handler = new CreatePromotionCommandHandler($this->roomExists, $this->repository);
    }

    public function testCreatesPromotion(): void
    {
        $command = new CreatePromotionCommand(
            id: self::PROMOTION_ID,
            roomId: self::ROOM_ID,
            checkIn: '2025-07-01',
            checkOut: '2025-07-15',
            discountPercent: 10,
            createdAt: new \DateTimeImmutable('2025-01-01 00:00:00'),
        );

        $promotion = ($this->handler)($command);

        self::assertSame(self::PROMOTION_ID, $promotion->id);
        self::assertSame(self::ROOM_ID, $promotion->roomId);
        self::assertSame('2025-07-01', $promotion->getCheckIn()->format('Y-m-d'));
        self::assertSame('2025-07-15', $promotion->getCheckOut()->format('Y-m-d'));
        self::assertSame(10, $promotion->getDiscountPercent());
        self::assertNotNull($this->repository->findById(self::PROMOTION_ID));
    }

    public function testThrowsWhenRoomNotFound(): void
    {
        $this->roomExists = new FakeRoomExistenceChecker(false);
        $this->handler = new CreatePromotionCommandHandler($this->roomExists, $this->repository);

        $this->expectException(RoomNotFoundException::class);

        ($this->handler)(new CreatePromotionCommand(
            id: self::PROMOTION_ID,
            roomId: self::ROOM_ID,
            checkIn: '2025-07-01',
            checkOut: '2025-07-15',
            discountPercent: 10,
            createdAt: new \DateTimeImmutable(),
        ));
    }

    public function testThrowsWhenOverlap(): void
    {
        ($this->handler)(new CreatePromotionCommand(
            id: self::PROMOTION_ID,
            roomId: self::ROOM_ID,
            checkIn: '2025-07-01',
            checkOut: '2025-07-15',
            discountPercent: 10,
            createdAt: new \DateTimeImmutable(),
        ));

        $this->expectException(PromotionOverlapException::class);

        ($this->handler)(new CreatePromotionCommand(
            id: 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e',
            roomId: self::ROOM_ID,
            checkIn: '2025-07-10',
            checkOut: '2025-07-20',
            discountPercent: 15,
            createdAt: new \DateTimeImmutable(),
        ));
    }
}
```

- [ ] **Run the test to verify it fails**

```bash
make test
# or: docker compose exec php vendor/bin/phpunit --group unit tests/Pricing/Application/UseCase/CreatePromotion/
# Expected: FAIL — class CreatePromotionCommand not found
```

- [ ] **Create `CreatePromotionCommand`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\CreatePromotion;

final readonly class CreatePromotionCommand
{
    public function __construct(
        public string $id,
        public string $roomId,
        public string $checkIn,
        public string $checkOut,
        public int $discountPercent,
        public \DateTimeImmutable $createdAt,
    ) {}
}
```

- [ ] **Create `CreatePromotionCommandHandler`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\CreatePromotion;

use App\Pricing\Domain\Exception\PromotionOverlapException;
use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Pricing\Domain\ValueObject\DatePeriod;
use App\Pricing\Domain\ValueObject\DiscountPercent;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class CreatePromotionCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomExistsInterface $roomExists,
        private PromotionRepositoryInterface $promotionRepository,
    ) {}

    public function __invoke(CreatePromotionCommand $command): Promotion
    {
        if (!$this->roomExists->exists($command->roomId)) {
            throw new RoomNotFoundException($command->roomId);
        }

        $period = new DatePeriod(
            new \DateTimeImmutable($command->checkIn),
            new \DateTimeImmutable($command->checkOut),
        );

        if ($this->promotionRepository->hasOverlap($command->roomId, $period)) {
            throw new PromotionOverlapException();
        }

        $promotion = new Promotion(
            id: $command->id,
            roomId: $command->roomId,
            checkIn: $period->checkIn,
            checkOut: $period->checkOut,
            discountPercent: (new DiscountPercent($command->discountPercent))->value,
            createdAt: $command->createdAt,
            updatedAt: $command->createdAt,
        );

        $this->promotionRepository->save($promotion);

        return $promotion;
    }
}
```

- [ ] **Create `CreatePromotionCommandFactory`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Pricing\Application\UseCase\CreatePromotion\CreatePromotionCommand;
use Psr\Clock\ClockInterface;

final readonly class CreatePromotionCommandFactory
{
    public function __construct(
        private IdGeneratorInterface $idGenerator,
        private ClockInterface $clock,
    ) {}

    public function create(
        string $roomId,
        string $checkIn,
        string $checkOut,
        int $discountPercent,
    ): CreatePromotionCommand {
        return new CreatePromotionCommand(
            id: $this->idGenerator->generate(),
            roomId: $roomId,
            checkIn: $checkIn,
            checkOut: $checkOut,
            discountPercent: $discountPercent,
            createdAt: $this->clock->now(),
        );
    }
}
```

- [ ] **Run tests to verify they pass**

```bash
make test
# or: docker compose exec php vendor/bin/phpunit --group unit tests/Pricing/Application/UseCase/CreatePromotion/
# Expected: 3 tests, 3 passed
```

- [ ] **Commit**

```bash
git add src/Pricing/Application/UseCase/CreatePromotion/
git add src/Pricing/Application/Service/CreatePromotionCommandFactory.php
git add tests/Pricing/Application/UseCase/CreatePromotion/
git commit -m "feat(pricing): add CreatePromotion use case"
```

---

## Task 5: GetPromotions use case (TDD)

**Files:**
- Create: `src/Pricing/Application/UseCase/GetPromotions/GetPromotionsQuery.php`
- Create: `src/Pricing/Application/UseCase/GetPromotions/GetPromotionsQueryHandler.php`
- Create: `tests/Pricing/Application/UseCase/GetPromotions/GetPromotionsQueryHandlerTest.php`

- [ ] **Write the failing unit tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\GetPromotions;

use App\Pricing\Application\UseCase\GetPromotions\GetPromotionsQuery;
use App\Pricing\Application\UseCase\GetPromotions\GetPromotionsQueryHandler;
use App\Pricing\Domain\Model\Promotion;
use App\Tests\Pricing\Infrastructure\Persistence\Doctrine\InMemoryPromotionRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetPromotionsQueryHandlerTest extends TestCase
{
    private const ROOM_ID = 'f1e2d3c4-b5a6-4978-8766-554433221100';

    private InMemoryPromotionRepository $repository;
    private GetPromotionsQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPromotionRepository();
        $this->handler = new GetPromotionsQueryHandler($this->repository);
    }

    public function testReturnsEmptyListWhenNoPromotions(): void
    {
        $result = ($this->handler)(new GetPromotionsQuery(self::ROOM_ID));

        self::assertSame([], $result);
    }

    public function testReturnsPromotionsSortedByCheckIn(): void
    {
        $later = new Promotion(
            id: 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e',
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-08-01'),
            checkOut: new \DateTimeImmutable('2025-08-15'),
            discountPercent: 20,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
        $earlier = new Promotion(
            id: 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d',
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-15'),
            discountPercent: 10,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
        $this->repository->save($later);
        $this->repository->save($earlier);

        $result = ($this->handler)(new GetPromotionsQuery(self::ROOM_ID));

        self::assertCount(2, $result);
        self::assertSame('2025-07-01', $result[0]->getCheckIn()->format('Y-m-d'));
        self::assertSame('2025-08-01', $result[1]->getCheckIn()->format('Y-m-d'));
    }

    public function testDoesNotReturnPromotionsForOtherRooms(): void
    {
        $otherRoomId = 'c3d4e5f6-a7b8-4c9d-0e1f-2a3b4c5d6e7f';
        $this->repository->save(new Promotion(
            id: 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d',
            roomId: $otherRoomId,
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-15'),
            discountPercent: 10,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        ));

        $result = ($this->handler)(new GetPromotionsQuery(self::ROOM_ID));

        self::assertSame([], $result);
    }
}
```

- [ ] **Run to verify it fails**

```bash
docker compose exec php vendor/bin/phpunit --group unit tests/Pricing/Application/UseCase/GetPromotions/
# Expected: FAIL — class GetPromotionsQuery not found
```

- [ ] **Create `GetPromotionsQuery`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetPromotions;

final readonly class GetPromotionsQuery
{
    public function __construct(
        public string $roomId,
    ) {}
}
```

- [ ] **Create `GetPromotionsQueryHandler`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetPromotions;

use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetPromotionsQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository,
    ) {}

    /** @return list<Promotion> */
    public function __invoke(GetPromotionsQuery $query): array
    {
        return $this->promotionRepository->findByRoomId($query->roomId);
    }
}
```

- [ ] **Run to verify tests pass**

```bash
docker compose exec php vendor/bin/phpunit --group unit tests/Pricing/Application/UseCase/GetPromotions/
# Expected: 3 tests, 3 passed
```

- [ ] **Commit**

```bash
git add src/Pricing/Application/UseCase/GetPromotions/
git add tests/Pricing/Application/UseCase/GetPromotions/
git commit -m "feat(pricing): add GetPromotions use case"
```

---

## Task 6: UpdatePromotion use case (TDD)

**Files:**
- Create: `src/Pricing/Application/UseCase/UpdatePromotion/UpdatePromotionCommand.php`
- Create: `src/Pricing/Application/UseCase/UpdatePromotion/UpdatePromotionCommandHandler.php`
- Create: `src/Pricing/Application/Service/UpdatePromotionCommandFactory.php`
- Create: `tests/Pricing/Application/UseCase/UpdatePromotion/UpdatePromotionCommandHandlerTest.php`

- [ ] **Write the failing unit tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\UpdatePromotion;

use App\Pricing\Application\UseCase\UpdatePromotion\UpdatePromotionCommand;
use App\Pricing\Application\UseCase\UpdatePromotion\UpdatePromotionCommandHandler;
use App\Pricing\Domain\Exception\PromotionNotFoundException;
use App\Pricing\Domain\Exception\PromotionOverlapException;
use App\Pricing\Domain\Model\Promotion;
use App\Tests\Pricing\Infrastructure\Persistence\Doctrine\InMemoryPromotionRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UpdatePromotionCommandHandlerTest extends TestCase
{
    private const ROOM_ID = 'f1e2d3c4-b5a6-4978-8766-554433221100';
    private const PROMOTION_ID = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';

    private InMemoryPromotionRepository $repository;
    private UpdatePromotionCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPromotionRepository();
        $this->handler = new UpdatePromotionCommandHandler($this->repository);

        $this->repository->save(new Promotion(
            id: self::PROMOTION_ID,
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-15'),
            discountPercent: 10,
            createdAt: new \DateTimeImmutable('2025-01-01'),
            updatedAt: new \DateTimeImmutable('2025-01-01'),
        ));
    }

    public function testUpdatesPromotion(): void
    {
        $updatedAt = new \DateTimeImmutable('2025-02-01');

        $promotion = ($this->handler)(new UpdatePromotionCommand(
            promotionId: self::PROMOTION_ID,
            roomId: self::ROOM_ID,
            checkIn: '2025-07-01',
            checkOut: '2025-09-01',
            discountPercent: 20,
            updatedAt: $updatedAt,
        ));

        self::assertSame('2025-09-01', $promotion->getCheckOut()->format('Y-m-d'));
        self::assertSame(20, $promotion->getDiscountPercent());
        self::assertSame($updatedAt->getTimestamp(), $promotion->getUpdatedAt()->getTimestamp());
    }

    public function testThrowsWhenPromotionNotFound(): void
    {
        $this->expectException(PromotionNotFoundException::class);

        ($this->handler)(new UpdatePromotionCommand(
            promotionId: 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e',
            roomId: self::ROOM_ID,
            checkIn: '2025-07-01',
            checkOut: '2025-08-01',
            discountPercent: 15,
            updatedAt: new \DateTimeImmutable(),
        ));
    }

    public function testThrowsWhenOverlapWithAnotherPromotion(): void
    {
        $this->repository->save(new Promotion(
            id: 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e',
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-08-01'),
            checkOut: new \DateTimeImmutable('2025-08-31'),
            discountPercent: 5,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        ));

        $this->expectException(PromotionOverlapException::class);

        ($this->handler)(new UpdatePromotionCommand(
            promotionId: self::PROMOTION_ID,
            roomId: self::ROOM_ID,
            checkIn: '2025-07-01',
            checkOut: '2025-08-15',
            discountPercent: 10,
            updatedAt: new \DateTimeImmutable(),
        ));
    }
}
```

- [ ] **Run to verify it fails**

```bash
docker compose exec php vendor/bin/phpunit --group unit tests/Pricing/Application/UseCase/UpdatePromotion/
# Expected: FAIL — class UpdatePromotionCommand not found
```

- [ ] **Create `UpdatePromotionCommand`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\UpdatePromotion;

final readonly class UpdatePromotionCommand
{
    public function __construct(
        public string $promotionId,
        public string $roomId,
        public string $checkIn,
        public string $checkOut,
        public int $discountPercent,
        public \DateTimeImmutable $updatedAt,
    ) {}
}
```

- [ ] **Create `UpdatePromotionCommandHandler`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\UpdatePromotion;

use App\Pricing\Domain\Exception\PromotionNotFoundException;
use App\Pricing\Domain\Exception\PromotionOverlapException;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\DatePeriod;
use App\Pricing\Domain\ValueObject\DiscountPercent;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class UpdatePromotionCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository,
    ) {}

    public function __invoke(UpdatePromotionCommand $command): Promotion
    {
        $promotion = $this->promotionRepository->findById($command->promotionId);
        if (null === $promotion) {
            throw new PromotionNotFoundException($command->promotionId);
        }

        $period = new DatePeriod(
            new \DateTimeImmutable($command->checkIn),
            new \DateTimeImmutable($command->checkOut),
        );

        if ($this->promotionRepository->hasOverlap($command->roomId, $period, $command->promotionId)) {
            throw new PromotionOverlapException();
        }

        $promotion->update(
            $period->checkIn,
            $period->checkOut,
            (new DiscountPercent($command->discountPercent))->value,
            $command->updatedAt,
        );

        $this->promotionRepository->save($promotion);

        return $promotion;
    }
}
```

- [ ] **Create `UpdatePromotionCommandFactory`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Pricing\Application\UseCase\UpdatePromotion\UpdatePromotionCommand;
use Psr\Clock\ClockInterface;

final readonly class UpdatePromotionCommandFactory
{
    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function create(
        string $promotionId,
        string $roomId,
        string $checkIn,
        string $checkOut,
        int $discountPercent,
    ): UpdatePromotionCommand {
        return new UpdatePromotionCommand(
            promotionId: $promotionId,
            roomId: $roomId,
            checkIn: $checkIn,
            checkOut: $checkOut,
            discountPercent: $discountPercent,
            updatedAt: $this->clock->now(),
        );
    }
}
```

- [ ] **Run to verify tests pass**

```bash
docker compose exec php vendor/bin/phpunit --group unit tests/Pricing/Application/UseCase/UpdatePromotion/
# Expected: 3 tests, 3 passed
```

- [ ] **Commit**

```bash
git add src/Pricing/Application/UseCase/UpdatePromotion/
git add src/Pricing/Application/Service/UpdatePromotionCommandFactory.php
git add tests/Pricing/Application/UseCase/UpdatePromotion/
git commit -m "feat(pricing): add UpdatePromotion use case"
```

---

## Task 7: DeletePromotion use case (TDD)

**Files:**
- Create: `src/Pricing/Application/UseCase/DeletePromotion/DeletePromotionCommand.php`
- Create: `src/Pricing/Application/UseCase/DeletePromotion/DeletePromotionCommandHandler.php`
- Create: `tests/Pricing/Application/UseCase/DeletePromotion/DeletePromotionCommandHandlerTest.php`

- [ ] **Write the failing unit tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\DeletePromotion;

use App\Pricing\Application\UseCase\DeletePromotion\DeletePromotionCommand;
use App\Pricing\Application\UseCase\DeletePromotion\DeletePromotionCommandHandler;
use App\Pricing\Domain\Exception\PromotionNotFoundException;
use App\Pricing\Domain\Model\Promotion;
use App\Tests\Pricing\Infrastructure\Persistence\Doctrine\InMemoryPromotionRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeletePromotionCommandHandlerTest extends TestCase
{
    private const ROOM_ID = 'f1e2d3c4-b5a6-4978-8766-554433221100';
    private const PROMOTION_ID = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';

    private InMemoryPromotionRepository $repository;
    private DeletePromotionCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPromotionRepository();
        $this->handler = new DeletePromotionCommandHandler($this->repository);
    }

    public function testDeletesPromotion(): void
    {
        $this->repository->save(new Promotion(
            id: self::PROMOTION_ID,
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-07-01'),
            checkOut: new \DateTimeImmutable('2025-07-15'),
            discountPercent: 10,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        ));

        ($this->handler)(new DeletePromotionCommand(self::PROMOTION_ID));

        self::assertNull($this->repository->findById(self::PROMOTION_ID));
    }

    public function testThrowsWhenPromotionNotFound(): void
    {
        $this->expectException(PromotionNotFoundException::class);

        ($this->handler)(new DeletePromotionCommand('b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e'));
    }
}
```

- [ ] **Run to verify it fails**

```bash
docker compose exec php vendor/bin/phpunit --group unit tests/Pricing/Application/UseCase/DeletePromotion/
# Expected: FAIL — class DeletePromotionCommand not found
```

- [ ] **Create `DeletePromotionCommand`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\DeletePromotion;

final readonly class DeletePromotionCommand
{
    public function __construct(
        public string $promotionId,
    ) {}
}
```

- [ ] **Create `DeletePromotionCommandHandler`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\DeletePromotion;

use App\Pricing\Domain\Exception\PromotionNotFoundException;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class DeletePromotionCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository,
    ) {}

    public function __invoke(DeletePromotionCommand $command): void
    {
        $promotion = $this->promotionRepository->findById($command->promotionId);
        if (null === $promotion) {
            throw new PromotionNotFoundException($command->promotionId);
        }

        $this->promotionRepository->delete($promotion);
    }
}
```

- [ ] **Run to verify tests pass**

```bash
docker compose exec php vendor/bin/phpunit --group unit tests/Pricing/Application/UseCase/DeletePromotion/
# Expected: 2 tests, 2 passed
```

- [ ] **Commit**

```bash
git add src/Pricing/Application/UseCase/DeletePromotion/
git add tests/Pricing/Application/UseCase/DeletePromotion/
git commit -m "feat(pricing): add DeletePromotion use case"
```

---

## Task 8: GetPricingQuote use case (TDD)

**Files:**
- Create: `src/Pricing/Application/UseCase/GetPricingQuote/GetPricingQuoteQuery.php`
- Create: `src/Pricing/Application/UseCase/GetPricingQuote/GetPricingQuoteQueryHandler.php`
- Create: `tests/Pricing/Application/UseCase/GetPricingQuote/GetPricingQuoteQueryHandlerTest.php`

The handler uses `findByRoomId()` from `RatePeriodRepositoryInterface` and `PromotionRepositoryInterface` (no new methods needed). For the unit test, use:
- `InMemoryBaseRateRepository` from `tests/Pricing/Infrastructure/Persistence/Doctrine/` (created in Plan 1)
- `InMemoryRatePeriodRepository` from `tests/Pricing/Infrastructure/Persistence/Doctrine/` (created in Plan 1)
- `FakeRoomExistenceChecker` from `tests/Pricing/Infrastructure/Persistence/Doctrine/` (created in Plan 1)
- `InMemoryPromotionRepository` (Task 3 of this plan)

- [ ] **Write the failing unit tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\GetPricingQuote;

use App\Pricing\Application\UseCase\GetPricingQuote\GetPricingQuoteQuery;
use App\Pricing\Application\UseCase\GetPricingQuote\GetPricingQuoteQueryHandler;
use App\Pricing\Domain\Exception\RoomHasNoBaseRateException;
use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Pricing\Domain\Model\BaseRate;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Model\RatePeriod;
use App\Tests\Pricing\Infrastructure\Persistence\Doctrine\FakeRoomExistenceChecker;
use App\Tests\Pricing\Infrastructure\Persistence\Doctrine\InMemoryBaseRateRepository;
use App\Tests\Pricing\Infrastructure\Persistence\Doctrine\InMemoryPromotionRepository;
use App\Tests\Pricing\Infrastructure\Persistence\Doctrine\InMemoryRatePeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetPricingQuoteQueryHandlerTest extends TestCase
{
    private const ROOM_ID = 'f1e2d3c4-b5a6-4978-8766-554433221100';

    private InMemoryBaseRateRepository $baseRateRepository;
    private InMemoryRatePeriodRepository $ratePeriodRepository;
    private InMemoryPromotionRepository $promotionRepository;
    private FakeRoomExistenceChecker $roomExists;
    private GetPricingQuoteQueryHandler $handler;

    protected function setUp(): void
    {
        $this->baseRateRepository = new InMemoryBaseRateRepository();
        $this->ratePeriodRepository = new InMemoryRatePeriodRepository();
        $this->promotionRepository = new InMemoryPromotionRepository();
        $this->roomExists = new FakeRoomExistenceChecker(true);
        $this->handler = new GetPricingQuoteQueryHandler(
            $this->roomExists,
            $this->baseRateRepository,
            $this->ratePeriodRepository,
            $this->promotionRepository,
        );
    }

    public function testQuoteWithBaseRateOnlyNoPromotion(): void
    {
        // BaseRate = 10000 cents (€100/night), 3 nights: total = 30000
        $this->baseRateRepository->save(new BaseRate(self::ROOM_ID, 10000, new \DateTimeImmutable()));

        $result = ($this->handler)(new GetPricingQuoteQuery(
            self::ROOM_ID,
            new \DateTimeImmutable('2025-07-10'),
            new \DateTimeImmutable('2025-07-13'),
        ));

        self::assertSame(self::ROOM_ID, $result['roomId']);
        self::assertSame('2025-07-10', $result['checkIn']);
        self::assertSame('2025-07-13', $result['checkOut']);
        self::assertSame(30000, $result['totalAmountCents']);
        self::assertCount(3, $result['nights']);

        $night = $result['nights'][0];
        self::assertSame('2025-07-10', $night['date']);
        self::assertSame(10000, $night['rateAmountCents']);
        self::assertNull($night['discountPercent']);
        self::assertSame(10000, $night['effectiveAmountCents']);
    }

    public function testQuoteRatePeriodOverridesBaseRate(): void
    {
        // BaseRate = 10000, RatePeriod on 11th and 12th = 15000
        // Night 10: 10000, Night 11: 15000, Night 12: 15000 → total = 40000
        $this->baseRateRepository->save(new BaseRate(self::ROOM_ID, 10000, new \DateTimeImmutable()));
        $this->ratePeriodRepository->save(new RatePeriod(
            id: 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d',
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-07-11'),
            checkOut: new \DateTimeImmutable('2025-07-13'),
            amountCents: 15000,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        ));

        $result = ($this->handler)(new GetPricingQuoteQuery(
            self::ROOM_ID,
            new \DateTimeImmutable('2025-07-10'),
            new \DateTimeImmutable('2025-07-13'),
        ));

        self::assertSame(40000, $result['totalAmountCents']);
        self::assertSame(10000, $result['nights'][0]['rateAmountCents']); // 10th: base rate
        self::assertSame(15000, $result['nights'][1]['rateAmountCents']); // 11th: rate period
        self::assertSame(15000, $result['nights'][2]['rateAmountCents']); // 12th: rate period
    }

    public function testQuotePromotionAppliesDiscount(): void
    {
        // BaseRate = 10000, 10% discount on all 3 nights → 9000 each → total = 27000
        $this->baseRateRepository->save(new BaseRate(self::ROOM_ID, 10000, new \DateTimeImmutable()));
        $this->promotionRepository->save(new Promotion(
            id: 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d',
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-07-10'),
            checkOut: new \DateTimeImmutable('2025-07-13'),
            discountPercent: 10,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        ));

        $result = ($this->handler)(new GetPricingQuoteQuery(
            self::ROOM_ID,
            new \DateTimeImmutable('2025-07-10'),
            new \DateTimeImmutable('2025-07-13'),
        ));

        self::assertSame(27000, $result['totalAmountCents']);

        $night = $result['nights'][0];
        self::assertSame(10000, $night['rateAmountCents']);
        self::assertSame(10, $night['discountPercent']);
        self::assertSame(9000, $night['effectiveAmountCents']);
    }

    public function testQuoteRatePeriodAndPromotionCombined(): void
    {
        // Night 10: base 10000, no promo → 10000
        // Night 11: rate period 20000, 25% promo → round(20000 * 0.75) = 15000
        // Total = 25000
        $this->baseRateRepository->save(new BaseRate(self::ROOM_ID, 10000, new \DateTimeImmutable()));
        $this->ratePeriodRepository->save(new RatePeriod(
            id: 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d',
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-07-11'),
            checkOut: new \DateTimeImmutable('2025-07-12'),
            amountCents: 20000,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        ));
        $this->promotionRepository->save(new Promotion(
            id: 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e',
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-07-11'),
            checkOut: new \DateTimeImmutable('2025-07-12'),
            discountPercent: 25,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        ));

        $result = ($this->handler)(new GetPricingQuoteQuery(
            self::ROOM_ID,
            new \DateTimeImmutable('2025-07-10'),
            new \DateTimeImmutable('2025-07-12'),
        ));

        self::assertSame(25000, $result['totalAmountCents']);
        self::assertSame(10000, $result['nights'][0]['effectiveAmountCents']);
        self::assertSame(15000, $result['nights'][1]['effectiveAmountCents']);
    }

    public function testThrowsWhenRoomNotFound(): void
    {
        $this->roomExists = new FakeRoomExistenceChecker(false);
        $this->handler = new GetPricingQuoteQueryHandler(
            $this->roomExists,
            $this->baseRateRepository,
            $this->ratePeriodRepository,
            $this->promotionRepository,
        );

        $this->expectException(RoomNotFoundException::class);

        ($this->handler)(new GetPricingQuoteQuery(
            self::ROOM_ID,
            new \DateTimeImmutable('2025-07-10'),
            new \DateTimeImmutable('2025-07-13'),
        ));
    }

    public function testThrowsWhenRoomHasNoBaseRate(): void
    {
        $this->expectException(RoomHasNoBaseRateException::class);

        ($this->handler)(new GetPricingQuoteQuery(
            self::ROOM_ID,
            new \DateTimeImmutable('2025-07-10'),
            new \DateTimeImmutable('2025-07-13'),
        ));
    }
}
```

**Note:** The test uses `new RatePeriod(id:, roomId:, checkIn:, checkOut:, amountCents:, createdAt:, updatedAt:)` and `new BaseRate(roomId:, amountCents:, updatedAt:)`. Verify these constructor signatures match the existing Plan 1 entities in `src/Pricing/Domain/Model/`.

- [ ] **Run to verify it fails**

```bash
docker compose exec php vendor/bin/phpunit --group unit tests/Pricing/Application/UseCase/GetPricingQuote/
# Expected: FAIL — class GetPricingQuoteQuery not found
```

- [ ] **Create `GetPricingQuoteQuery`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetPricingQuote;

final readonly class GetPricingQuoteQuery
{
    public function __construct(
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {}
}
```

- [ ] **Create `GetPricingQuoteQueryHandler`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetPricingQuote;

use App\Pricing\Domain\Exception\RoomHasNoBaseRateException;
use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;
use App\Pricing\Domain\Port\PromotionRepositoryInterface;
use App\Pricing\Domain\Port\RatePeriodRepositoryInterface;
use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetPricingQuoteQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private RoomExistsInterface $roomExists,
        private BaseRateRepositoryInterface $baseRateRepository,
        private RatePeriodRepositoryInterface $ratePeriodRepository,
        private PromotionRepositoryInterface $promotionRepository,
    ) {}

    /** @return array{roomId: string, checkIn: string, checkOut: string, totalAmountCents: int, nights: list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}>} */
    public function __invoke(GetPricingQuoteQuery $query): array
    {
        if (!$this->roomExists->exists($query->roomId)) {
            throw new RoomNotFoundException($query->roomId);
        }

        $baseRate = $this->baseRateRepository->findByRoomId($query->roomId);
        if (null === $baseRate) {
            throw new RoomHasNoBaseRateException($query->roomId);
        }

        $ratePeriods = $this->ratePeriodRepository->findByRoomId($query->roomId);
        $promotions = $this->promotionRepository->findByRoomId($query->roomId);

        $nights = [];
        $totalAmountCents = 0;
        $current = $query->checkIn;

        while ($current < $query->checkOut) {
            $rateAmountCents = $baseRate->getAmountCents();
            foreach ($ratePeriods as $ratePeriod) {
                if ($ratePeriod->getCheckIn() <= $current && $current < $ratePeriod->getCheckOut()) {
                    $rateAmountCents = $ratePeriod->getAmountCents();
                    break;
                }
            }

            $discountPercent = null;
            foreach ($promotions as $promotion) {
                if ($promotion->getCheckIn() <= $current && $current < $promotion->getCheckOut()) {
                    $discountPercent = $promotion->getDiscountPercent();
                    break;
                }
            }

            $effectiveAmountCents = null !== $discountPercent
                ? (int) round($rateAmountCents * (1 - $discountPercent / 100))
                : $rateAmountCents;

            $nights[] = [
                'date' => $current->format('Y-m-d'),
                'rateAmountCents' => $rateAmountCents,
                'discountPercent' => $discountPercent,
                'effectiveAmountCents' => $effectiveAmountCents,
            ];

            $totalAmountCents += $effectiveAmountCents;
            $current = $current->modify('+1 day');
        }

        return [
            'roomId' => $query->roomId,
            'checkIn' => $query->checkIn->format('Y-m-d'),
            'checkOut' => $query->checkOut->format('Y-m-d'),
            'totalAmountCents' => $totalAmountCents,
            'nights' => $nights,
        ];
    }
}
```

**Note:** This uses `$baseRate->getAmountCents()`, `$ratePeriod->getCheckIn()`, `$ratePeriod->getCheckOut()`, `$ratePeriod->getAmountCents()`. Verify these getter names match what Plan 1 entities expose. If they are public properties, replace `->getXxx()` with `->xxx`.

- [ ] **Run to verify tests pass**

```bash
docker compose exec php vendor/bin/phpunit --group unit tests/Pricing/Application/UseCase/GetPricingQuote/
# Expected: 6 tests, 6 passed
```

- [ ] **Commit**

```bash
git add src/Pricing/Application/UseCase/GetPricingQuote/
git add tests/Pricing/Application/UseCase/GetPricingQuote/
git commit -m "feat(pricing): add GetPricingQuote use case with per-night breakdown"
```

---

## Task 9: DI config + exception mappings

**Files:**
- Modify: `config/services/pricing.yaml`
- Modify: `config/services/exceptions.yaml`

- [ ] **Add Promotion services to `config/services/pricing.yaml`**

Add the following entries alongside the existing Plan 1 entries (after the RatePeriod section):

```yaml
    App\Pricing\Domain\Port\PromotionRepositoryInterface:
        alias: App\Pricing\Infrastructure\Persistence\Doctrine\DoctrinePromotionRepository

    App\Pricing\Application\Service\CreatePromotionCommandFactory:
        arguments:
            $idGenerator: '@App\Pricing\Infrastructure\Service\UuidIdGenerator'
            $clock: '@Psr\Clock\ClockInterface'

    App\Pricing\Application\Service\UpdatePromotionCommandFactory:
        arguments:
            $clock: '@Psr\Clock\ClockInterface'
```

- [ ] **Add exception mappings to `config/services/exceptions.yaml`**

Add inside the existing `$map` block:

```yaml
            App\Pricing\Domain\Exception\PromotionNotFoundException:
                type: 'https://book.it/problems/promotion-not-found'
                title: 'Promotion Not Found'
                status: 404
            App\Pricing\Domain\Exception\PromotionOverlapException:
                type: 'https://book.it/problems/promotion-overlap'
                title: 'Promotion Overlap'
                status: 409
            App\Pricing\Domain\Exception\RoomHasNoBaseRateException:
                type: 'https://book.it/problems/room-has-no-base-rate'
                title: 'Room Has No Base Rate'
                status: 422
```

- [ ] **Verify container compiles**

```bash
docker compose exec php php bin/console cache:clear
# Expected: no errors
```

- [ ] **Commit**

```bash
git add config/services/pricing.yaml config/services/exceptions.yaml
git commit -m "feat(pricing): wire Promotion services and exception mappings"
```

---

## Task 10: PromotionSerializer + CreatePromotion controller (functional TDD)

**Files:**
- Create: `src/Pricing/UI/Http/Controller/PromotionSerializer.php`
- Create: `src/Pricing/UI/Http/Controller/CreatePromotion/CreatePromotionController.php`
- Create: `src/Pricing/UI/Http/Controller/CreatePromotion/CreatePromotionRequest.php`
- Create: `tests/Pricing/UI/Http/Controller/CreatePromotionControllerTest.php`

- [ ] **Write the failing functional test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class CreatePromotionControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function registerRoomAndGetId(KernelBrowser $client): string
    {
        // Copy exact implementation from BlockPeriodControllerTest::registerRoomAndGetId()
        // Creates a Hotel + Room, returns the roomId UUID
    }

    private function setBaseRate(KernelBrowser $client, string $roomId, float $amount): void
    {
        $client->request('PUT', "/api/rooms/{$roomId}/base-rate", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['amount' => $amount]));
    }

    public function testCreatesPromotion(): void
    {
        $roomId = $this->registerRoomAndGetId($this->client);
        $this->setBaseRate($this->client, $roomId, 100.00);

        $this->client->request(
            'POST',
            "/api/rooms/{$roomId}/promotions",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-07-15', 'discountPercent' => 10]),
        );

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('id', $data);
        self::assertSame($roomId, $data['roomId']);
        self::assertSame('2025-07-01', $data['checkIn']);
        self::assertSame('2025-07-15', $data['checkOut']);
        self::assertSame(10, $data['discountPercent']);
        self::assertIsInt($data['createdAt']);
    }

    public function testReturns404WhenRoomNotFound(): void
    {
        $this->client->request(
            'POST',
            '/api/rooms/00000000-0000-4000-8000-000000000000/promotions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-07-15', 'discountPercent' => 10]),
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testReturns409WhenOverlap(): void
    {
        $roomId = $this->registerRoomAndGetId($this->client);
        $this->setBaseRate($this->client, $roomId, 100.00);

        $payload = json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-07-15', 'discountPercent' => 10]);
        $this->client->request('POST', "/api/rooms/{$roomId}/promotions", [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('POST', "/api/rooms/{$roomId}/promotions", [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['checkIn' => '2025-07-10', 'checkOut' => '2025-07-20', 'discountPercent' => 15]),
        );
        self::assertResponseStatusCodeSame(409);
    }

    public function testReturns422OnValidationError(): void
    {
        $roomId = $this->registerRoomAndGetId($this->client);

        $this->client->request(
            'POST',
            "/api/rooms/{$roomId}/promotions",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-07-15', 'discountPercent' => 0]),
        );

        self::assertResponseStatusCodeSame(422);
    }
}
```

- [ ] **Run to verify it fails**

```bash
docker compose exec php vendor/bin/phpunit --group functional tests/Pricing/UI/Http/Controller/CreatePromotionControllerTest.php
# Expected: FAIL — route not found (404)
```

- [ ] **Create `PromotionSerializer`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller;

use App\Pricing\Domain\Model\Promotion;

final readonly class PromotionSerializer
{
    /** @return array{id: string, roomId: string, checkIn: string, checkOut: string, discountPercent: int, createdAt: int} */
    public function serialize(Promotion $promotion): array
    {
        return [
            'id' => $promotion->id,
            'roomId' => $promotion->roomId,
            'checkIn' => $promotion->getCheckIn()->format('Y-m-d'),
            'checkOut' => $promotion->getCheckOut()->format('Y-m-d'),
            'discountPercent' => $promotion->getDiscountPercent(),
            'createdAt' => $promotion->createdAt->getTimestamp(),
        ];
    }
}
```

- [ ] **Create `CreatePromotionRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\CreatePromotion;

use Symfony\Component\Validator\Constraints as Assert;

final class CreatePromotionRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Date]
        public readonly ?string $checkIn = null,

        #[Assert\NotNull]
        #[Assert\Date]
        public readonly ?string $checkOut = null,

        #[Assert\NotNull]
        #[Assert\Range(min: 1, max: 99)]
        public readonly ?int $discountPercent = null,
    ) {}
}
```

- [ ] **Create `CreatePromotionController`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\CreatePromotion;

use App\Pricing\Application\Service\CreatePromotionCommandFactory;
use App\Pricing\UI\Http\Controller\PromotionSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class CreatePromotionController
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private CreatePromotionCommandFactory $commandFactory,
        private PromotionSerializer $serializer,
    ) {}

    #[Route(
        path: '/api/rooms/{roomId}/promotions',
        name: 'pricing_create_promotion',
        requirements: ['roomId' => Requirement::UUID_V4],
        methods: [Request::METHOD_POST],
    )]
    public function __invoke(
        string $roomId,
        #[MapRequestPayload(acceptFormat: 'json')] CreatePromotionRequest $request,
    ): JsonResponse {
        $command = $this->commandFactory->create(
            $roomId,
            $request->checkIn,
            $request->checkOut,
            $request->discountPercent,
        );

        $envelope = $this->commandBus->dispatch($command);
        $promotion = $envelope->last(HandledStamp::class)->getResult();

        return new JsonResponse($this->serializer->serialize($promotion), Response::HTTP_CREATED);
    }
}
```

- [ ] **Run to verify tests pass**

```bash
docker compose exec php vendor/bin/phpunit --group functional tests/Pricing/UI/Http/Controller/CreatePromotionControllerTest.php
# Expected: 4 tests, 4 passed
```

- [ ] **Commit**

```bash
git add src/Pricing/UI/Http/Controller/PromotionSerializer.php
git add src/Pricing/UI/Http/Controller/CreatePromotion/
git add tests/Pricing/UI/Http/Controller/CreatePromotionControllerTest.php
git commit -m "feat(pricing): add CreatePromotion controller and PromotionSerializer"
```

---

## Task 11: GetPromotions controller (functional TDD)

**Files:**
- Create: `src/Pricing/UI/Http/Controller/GetPromotions/GetPromotionsController.php`
- Create: `tests/Pricing/UI/Http/Controller/GetPromotionsControllerTest.php`

- [ ] **Write the failing functional test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class GetPromotionsControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function registerRoomAndGetId(KernelBrowser $client): string
    {
        // Copy exact implementation from BlockPeriodControllerTest::registerRoomAndGetId()
    }

    private function createPromotion(KernelBrowser $client, string $roomId, string $checkIn, string $checkOut, int $discountPercent): void
    {
        $client->request(
            'POST',
            "/api/rooms/{$roomId}/promotions",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['checkIn' => $checkIn, 'checkOut' => $checkOut, 'discountPercent' => $discountPercent]),
        );
    }

    public function testReturnsEmptyListWhenNoPromotions(): void
    {
        $roomId = $this->registerRoomAndGetId($this->client);

        $this->client->request('GET', "/api/rooms/{$roomId}/promotions");

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(['promotions' => []], $data);
    }

    public function testReturnsPromotionsSortedByCheckIn(): void
    {
        $roomId = $this->registerRoomAndGetId($this->client);
        $this->createPromotion($this->client, $roomId, '2025-08-01', '2025-08-15', 20);
        $this->createPromotion($this->client, $roomId, '2025-07-01', '2025-07-15', 10);

        $this->client->request('GET', "/api/rooms/{$roomId}/promotions");

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(2, $data['promotions']);
        self::assertSame('2025-07-01', $data['promotions'][0]['checkIn']);
        self::assertSame('2025-08-01', $data['promotions'][1]['checkIn']);
    }
}
```

- [ ] **Run to verify it fails**

```bash
docker compose exec php vendor/bin/phpunit --group functional tests/Pricing/UI/Http/Controller/GetPromotionsControllerTest.php
# Expected: FAIL — route not found
```

- [ ] **Create `GetPromotionsController`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\GetPromotions;

use App\Pricing\Application\UseCase\GetPromotions\GetPromotionsQuery;
use App\Pricing\UI\Http\Controller\PromotionSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetPromotionsController
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private PromotionSerializer $serializer,
    ) {}

    #[Route(
        path: '/api/rooms/{roomId}/promotions',
        name: 'pricing_get_promotions',
        requirements: ['roomId' => Requirement::UUID_V4],
        methods: [Request::METHOD_GET],
    )]
    public function __invoke(string $roomId): JsonResponse
    {
        $envelope = $this->queryBus->dispatch(new GetPromotionsQuery($roomId));
        $promotions = $envelope->last(HandledStamp::class)->getResult();

        return new JsonResponse([
            'promotions' => array_map($this->serializer->serialize(...), $promotions),
        ]);
    }
}
```

- [ ] **Run to verify tests pass**

```bash
docker compose exec php vendor/bin/phpunit --group functional tests/Pricing/UI/Http/Controller/GetPromotionsControllerTest.php
# Expected: 2 tests, 2 passed
```

- [ ] **Commit**

```bash
git add src/Pricing/UI/Http/Controller/GetPromotions/
git add tests/Pricing/UI/Http/Controller/GetPromotionsControllerTest.php
git commit -m "feat(pricing): add GetPromotions controller"
```

---

## Task 12: UpdatePromotion controller (functional TDD)

**Files:**
- Create: `src/Pricing/UI/Http/Controller/UpdatePromotion/UpdatePromotionController.php`
- Create: `src/Pricing/UI/Http/Controller/UpdatePromotion/UpdatePromotionRequest.php`
- Create: `tests/Pricing/UI/Http/Controller/UpdatePromotionControllerTest.php`

- [ ] **Write the failing functional test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class UpdatePromotionControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function registerRoomAndGetId(KernelBrowser $client): string
    {
        // Copy exact implementation from BlockPeriodControllerTest::registerRoomAndGetId()
    }

    private function createPromotionAndGetId(KernelBrowser $client, string $roomId): string
    {
        $client->request(
            'POST',
            "/api/rooms/{$roomId}/promotions",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-07-15', 'discountPercent' => 10]),
        );
        return json_decode($client->getResponse()->getContent(), true)['id'];
    }

    public function testUpdatesPromotion(): void
    {
        $roomId = $this->registerRoomAndGetId($this->client);
        $promotionId = $this->createPromotionAndGetId($this->client, $roomId);

        $this->client->request(
            'PUT',
            "/api/rooms/{$roomId}/promotions/{$promotionId}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-09-01', 'discountPercent' => 20]),
        );

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('2025-09-01', $data['checkOut']);
        self::assertSame(20, $data['discountPercent']);
    }

    public function testReturns404WhenPromotionNotFound(): void
    {
        $roomId = $this->registerRoomAndGetId($this->client);

        $this->client->request(
            'PUT',
            "/api/rooms/{$roomId}/promotions/00000000-0000-4000-8000-000000000000",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-08-01', 'discountPercent' => 15]),
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testReturns409WhenOverlapWithAnotherPromotion(): void
    {
        $roomId = $this->registerRoomAndGetId($this->client);
        $promotionId = $this->createPromotionAndGetId($this->client, $roomId);

        // Create a second promotion that occupies August
        $this->client->request('POST', "/api/rooms/{$roomId}/promotions", [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['checkIn' => '2025-08-01', 'checkOut' => '2025-08-31', 'discountPercent' => 5]),
        );

        // Try to extend the first promotion into August
        $this->client->request(
            'PUT',
            "/api/rooms/{$roomId}/promotions/{$promotionId}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-08-15', 'discountPercent' => 10]),
        );

        self::assertResponseStatusCodeSame(409);
    }
}
```

- [ ] **Run to verify it fails**

```bash
docker compose exec php vendor/bin/phpunit --group functional tests/Pricing/UI/Http/Controller/UpdatePromotionControllerTest.php
# Expected: FAIL — route not found
```

- [ ] **Create `UpdatePromotionRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\UpdatePromotion;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdatePromotionRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Date]
        public readonly ?string $checkIn = null,

        #[Assert\NotNull]
        #[Assert\Date]
        public readonly ?string $checkOut = null,

        #[Assert\NotNull]
        #[Assert\Range(min: 1, max: 99)]
        public readonly ?int $discountPercent = null,
    ) {}
}
```

- [ ] **Create `UpdatePromotionController`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\UpdatePromotion;

use App\Pricing\Application\Service\UpdatePromotionCommandFactory;
use App\Pricing\UI\Http\Controller\PromotionSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class UpdatePromotionController
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private UpdatePromotionCommandFactory $commandFactory,
        private PromotionSerializer $serializer,
    ) {}

    #[Route(
        path: '/api/rooms/{roomId}/promotions/{promotionId}',
        name: 'pricing_update_promotion',
        requirements: ['roomId' => Requirement::UUID_V4, 'promotionId' => Requirement::UUID_V4],
        methods: [Request::METHOD_PUT],
    )]
    public function __invoke(
        string $roomId,
        string $promotionId,
        #[MapRequestPayload(acceptFormat: 'json')] UpdatePromotionRequest $request,
    ): JsonResponse {
        $command = $this->commandFactory->create(
            $promotionId,
            $roomId,
            $request->checkIn,
            $request->checkOut,
            $request->discountPercent,
        );

        $envelope = $this->commandBus->dispatch($command);
        $promotion = $envelope->last(HandledStamp::class)->getResult();

        return new JsonResponse($this->serializer->serialize($promotion), Response::HTTP_OK);
    }
}
```

- [ ] **Run to verify tests pass**

```bash
docker compose exec php vendor/bin/phpunit --group functional tests/Pricing/UI/Http/Controller/UpdatePromotionControllerTest.php
# Expected: 3 tests, 3 passed
```

- [ ] **Commit**

```bash
git add src/Pricing/UI/Http/Controller/UpdatePromotion/
git add tests/Pricing/UI/Http/Controller/UpdatePromotionControllerTest.php
git commit -m "feat(pricing): add UpdatePromotion controller"
```

---

## Task 13: DeletePromotion controller (functional TDD)

**Files:**
- Create: `src/Pricing/UI/Http/Controller/DeletePromotion/DeletePromotionController.php`
- Create: `tests/Pricing/UI/Http/Controller/DeletePromotionControllerTest.php`

- [ ] **Write the failing functional test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class DeletePromotionControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function registerRoomAndGetId(KernelBrowser $client): string
    {
        // Copy exact implementation from BlockPeriodControllerTest::registerRoomAndGetId()
    }

    private function createPromotionAndGetId(KernelBrowser $client, string $roomId): string
    {
        $client->request(
            'POST',
            "/api/rooms/{$roomId}/promotions",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['checkIn' => '2025-07-01', 'checkOut' => '2025-07-15', 'discountPercent' => 10]),
        );
        return json_decode($client->getResponse()->getContent(), true)['id'];
    }

    public function testDeletesPromotion(): void
    {
        $roomId = $this->registerRoomAndGetId($this->client);
        $promotionId = $this->createPromotionAndGetId($this->client, $roomId);

        $this->client->request('DELETE', "/api/rooms/{$roomId}/promotions/{$promotionId}");

        self::assertResponseStatusCodeSame(204);
    }

    public function testReturns404WhenPromotionNotFound(): void
    {
        $roomId = $this->registerRoomAndGetId($this->client);

        $this->client->request('DELETE', "/api/rooms/{$roomId}/promotions/00000000-0000-4000-8000-000000000000");

        self::assertResponseStatusCodeSame(404);
    }
}
```

- [ ] **Run to verify it fails**

```bash
docker compose exec php vendor/bin/phpunit --group functional tests/Pricing/UI/Http/Controller/DeletePromotionControllerTest.php
# Expected: FAIL — route not found
```

- [ ] **Create `DeletePromotionController`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\DeletePromotion;

use App\Pricing\Application\UseCase\DeletePromotion\DeletePromotionCommand;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class DeletePromotionController
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {}

    #[Route(
        path: '/api/rooms/{roomId}/promotions/{promotionId}',
        name: 'pricing_delete_promotion',
        requirements: ['roomId' => Requirement::UUID_V4, 'promotionId' => Requirement::UUID_V4],
        methods: [Request::METHOD_DELETE],
    )]
    public function __invoke(string $roomId, string $promotionId): JsonResponse
    {
        $this->commandBus->dispatch(new DeletePromotionCommand($promotionId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Run to verify tests pass**

```bash
docker compose exec php vendor/bin/phpunit --group functional tests/Pricing/UI/Http/Controller/DeletePromotionControllerTest.php
# Expected: 2 tests, 2 passed
```

- [ ] **Commit**

```bash
git add src/Pricing/UI/Http/Controller/DeletePromotion/
git add tests/Pricing/UI/Http/Controller/DeletePromotionControllerTest.php
git commit -m "feat(pricing): add DeletePromotion controller"
```

---

## Task 14: GetPricingQuote controller (functional TDD)

**Files:**
- Create: `src/Pricing/UI/Http/Controller/GetPricingQuote/GetPricingQuoteController.php`
- Create: `src/Pricing/UI/Http/Controller/GetPricingQuote/GetPricingQuoteRequest.php`
- Create: `tests/Pricing/UI/Http/Controller/GetPricingQuoteControllerTest.php`

- [ ] **Write the failing functional test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class GetPricingQuoteControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function registerRoomAndGetId(KernelBrowser $client): string
    {
        // Copy exact implementation from BlockPeriodControllerTest::registerRoomAndGetId()
    }

    private function setBaseRate(KernelBrowser $client, string $roomId, float $amount): void
    {
        $client->request('PUT', "/api/rooms/{$roomId}/base-rate", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['amount' => $amount]));
    }

    public function testReturnsQuoteWithBaseRateOnly(): void
    {
        $roomId = $this->registerRoomAndGetId($this->client);
        $this->setBaseRate($this->client, $roomId, 100.00); // 10000 cents/night

        $this->client->request('GET', "/api/rooms/{$roomId}/pricing-quote?checkIn=2025-07-10&checkOut=2025-07-13");

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame($roomId, $data['roomId']);
        self::assertSame('2025-07-10', $data['checkIn']);
        self::assertSame('2025-07-13', $data['checkOut']);
        self::assertSame(30000, $data['totalAmountCents']); // 3 nights × 10000
        self::assertCount(3, $data['nights']);

        $night = $data['nights'][0];
        self::assertSame('2025-07-10', $night['date']);
        self::assertSame(10000, $night['rateAmountCents']);
        self::assertNull($night['discountPercent']);
        self::assertSame(10000, $night['effectiveAmountCents']);
    }

    public function testReturnsQuoteWithPromotionDiscount(): void
    {
        $roomId = $this->registerRoomAndGetId($this->client);
        $this->setBaseRate($this->client, $roomId, 100.00); // 10000 cents/night

        // Apply 10% discount on 2 of the 3 nights
        $this->client->request('POST', "/api/rooms/{$roomId}/promotions", [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['checkIn' => '2025-07-11', 'checkOut' => '2025-07-13', 'discountPercent' => 10]),
        );

        $this->client->request('GET', "/api/rooms/{$roomId}/pricing-quote?checkIn=2025-07-10&checkOut=2025-07-13");

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        // Night 10: 10000, Night 11: 9000, Night 12: 9000 → 28000
        self::assertSame(28000, $data['totalAmountCents']);
        self::assertNull($data['nights'][0]['discountPercent']);
        self::assertSame(10, $data['nights'][1]['discountPercent']);
        self::assertSame(9000, $data['nights'][1]['effectiveAmountCents']);
    }

    public function testReturns404WhenRoomNotFound(): void
    {
        $this->client->request('GET', '/api/rooms/00000000-0000-4000-8000-000000000000/pricing-quote?checkIn=2025-07-10&checkOut=2025-07-13');

        self::assertResponseStatusCodeSame(404);
    }

    public function testReturns422WhenRoomHasNoBaseRate(): void
    {
        $roomId = $this->registerRoomAndGetId($this->client);

        $this->client->request('GET', "/api/rooms/{$roomId}/pricing-quote?checkIn=2025-07-10&checkOut=2025-07-13");

        self::assertResponseStatusCodeSame(422);
    }

    public function testReturns422WhenCheckInAfterCheckOut(): void
    {
        $roomId = $this->registerRoomAndGetId($this->client);
        $this->setBaseRate($this->client, $roomId, 100.00);

        $this->client->request('GET', "/api/rooms/{$roomId}/pricing-quote?checkIn=2025-07-15&checkOut=2025-07-10");

        self::assertResponseStatusCodeSame(422);
    }

    public function testReturns422WhenMissingQueryParams(): void
    {
        $roomId = $this->registerRoomAndGetId($this->client);
        $this->setBaseRate($this->client, $roomId, 100.00);

        $this->client->request('GET', "/api/rooms/{$roomId}/pricing-quote");

        self::assertResponseStatusCodeSame(422);
    }
}
```

- [ ] **Run to verify it fails**

```bash
docker compose exec php vendor/bin/phpunit --group functional tests/Pricing/UI/Http/Controller/GetPricingQuoteControllerTest.php
# Expected: FAIL — route not found
```

- [ ] **Create `GetPricingQuoteRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\GetPricingQuote;

use Symfony\Component\Validator\Constraints as Assert;

final class GetPricingQuoteRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        public readonly string $checkIn = '',

        #[Assert\NotBlank]
        #[Assert\Date]
        #[Assert\GreaterThan(propertyPath: 'checkIn', message: 'checkOut must be strictly after checkIn.')]
        public readonly string $checkOut = '',
    ) {}
}
```

- [ ] **Create `GetPricingQuoteController`**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\GetPricingQuote;

use App\Pricing\Application\UseCase\GetPricingQuote\GetPricingQuoteQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetPricingQuoteController
{
    public function __construct(
        private MessageBusInterface $queryBus,
    ) {}

    #[Route(
        path: '/api/rooms/{roomId}/pricing-quote',
        name: 'pricing_get_pricing_quote',
        requirements: ['roomId' => Requirement::UUID_V4],
        methods: [Request::METHOD_GET],
    )]
    public function __invoke(
        string $roomId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] GetPricingQuoteRequest $request,
    ): JsonResponse {
        $envelope = $this->queryBus->dispatch(new GetPricingQuoteQuery(
            $roomId,
            new \DateTimeImmutable($request->checkIn),
            new \DateTimeImmutable($request->checkOut),
        ));

        return new JsonResponse($envelope->last(HandledStamp::class)->getResult());
    }
}
```

- [ ] **Run to verify tests pass**

```bash
docker compose exec php vendor/bin/phpunit --group functional tests/Pricing/UI/Http/Controller/GetPricingQuoteControllerTest.php
# Expected: 6 tests, 6 passed
```

- [ ] **Run the full test suite to check for regressions**

```bash
make test
# Expected: all Plan 1 and Plan 2 tests pass
```

- [ ] **Commit**

```bash
git add src/Pricing/UI/Http/Controller/GetPricingQuote/
git add tests/Pricing/UI/Http/Controller/GetPricingQuoteControllerTest.php
git commit -m "feat(pricing): add GetPricingQuote controller"
```

---

## Task 15: Regenerate OpenAPI spec

- [ ] **Regenerate `openapi.yaml`**

```bash
make openapi
```

- [ ] **Verify the 5 new routes appear in `openapi.yaml`**

```bash
grep -E "pricing-quote|/promotions" openapi.yaml
# Expected: entries for POST/GET /promotions, PUT/DELETE /promotions/{promotionId}, GET /pricing-quote
```

- [ ] **Commit**

```bash
git add openapi.yaml
git commit -m "chore(api-doc): regenerate OpenAPI spec with Pricing Plan 2 endpoints"
```

---

## Self-review checklist

**Spec coverage:**
- [x] POST `/api/rooms/{roomId}/promotions` — Task 10
- [x] GET `/api/rooms/{roomId}/promotions` — Task 11
- [x] PUT `/api/rooms/{roomId}/promotions/{id}` — Task 12
- [x] DELETE `/api/rooms/{roomId}/promotions/{id}` — Task 13
- [x] GET `/api/rooms/{roomId}/pricing-quote` — Task 14
- [x] `DiscountPercent` VO (1–99) — Task 1
- [x] `Promotion` entity (mutable, no overlap) — Task 1
- [x] Overlap check SQL pattern — Task 3 (`DoctrinePromotionRepository.hasOverlap()`)
- [x] Per-night breakdown: BaseRate fallback → RatePeriod override → Promotion discount — Task 8
- [x] `round(rateAmountCents * (1 - discountPercent / 100))` — Task 8
- [x] 404 on room not found — Tasks 4, 10, 14
- [x] 409 on overlap — Tasks 4, 6, 10, 12
- [x] 422 on room has no base rate — Tasks 8, 14
- [x] 422 on validation error (invalid dates, checkIn >= checkOut) — Task 14
- [x] Exception mappings in `exceptions.yaml` — Task 9
- [x] DI wiring in `pricing.yaml` — Task 9
- [x] `make openapi` — Task 15

# Availability Context Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a new `Availability` bounded context that lets operators block rooms for specific date ranges, and lets any consumer check whether a room is available for a given period.

**Architecture:** Clean hexagonal architecture mirroring the existing `Room` context — Domain (model, value objects, ports, exceptions), Application (use cases as command/query handlers), Infrastructure (Doctrine DBAL repository, adapters), UI (Symfony HTTP controllers). The context lives in `src/Availability/` and references rooms by ID only via a port — no direct coupling to the Room context internals.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL (raw SQL, no ORM entity), PostgreSQL 16, PHPUnit with `#[Group('unit')]` / `#[Group('integration')]` / `#[Group('functional')]`, Symfony Messenger for command/query buses.

---

## File Map

**Domain**
- Create: `src/Availability/Domain/ValueObject/DatePeriod.php`
- Create: `src/Availability/Domain/Model/BlockedPeriod.php`
- Create: `src/Availability/Domain/Port/BlockedPeriodRepositoryInterface.php`
- Create: `src/Availability/Domain/Port/RoomExistsInterface.php`
- Create: `src/Availability/Domain/Exception/BlockedPeriodNotFoundException.php`
- Create: `src/Availability/Domain/Exception/BlockedPeriodOverlapException.php`
- Create: `src/Availability/Domain/Exception/RoomNotFoundException.php`

**Application**
- Create: `src/Availability/Application/Service/BlockedPeriodIdGeneratorInterface.php`
- Create: `src/Availability/Application/Service/BlockPeriodCommandFactory.php`
- Create: `src/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommand.php`
- Create: `src/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommandHandler.php`
- Create: `src/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommand.php`
- Create: `src/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommandHandler.php`
- Create: `src/Availability/Application/UseCase/GetBlockedPeriod/GetBlockedPeriodQuery.php`
- Create: `src/Availability/Application/UseCase/GetBlockedPeriod/GetBlockedPeriodQueryHandler.php`
- Create: `src/Availability/Application/UseCase/CheckAvailability/CheckAvailabilityQuery.php`
- Create: `src/Availability/Application/UseCase/CheckAvailability/CheckAvailabilityQueryHandler.php`
- Create: `src/Availability/Application/UseCase/GetAvailabilityCalendar/GetAvailabilityCalendarQuery.php`
- Create: `src/Availability/Application/UseCase/GetAvailabilityCalendar/GetAvailabilityCalendarQueryHandler.php`

**Infrastructure**
- Create: `src/Availability/Infrastructure/Service/BlockedPeriodIdGenerator.php`
- Create: `src/Availability/Infrastructure/Persistence/Doctrine/BlockedPeriodRepository.php`
- Create: `src/Availability/Infrastructure/Persistence/Doctrine/RoomExistenceChecker.php`
- Create: migration `blocked_period` table (generated via `make generate-migration`)

**UI**
- Create: `src/Availability/UI/Http/Controller/BlockedPeriodSerializer.php`
- Create: `src/Availability/UI/Http/Controller/BlockPeriod/BlockPeriodRequest.php`
- Create: `src/Availability/UI/Http/Controller/BlockPeriod/BlockPeriodController.php`
- Create: `src/Availability/UI/Http/Controller/DeleteBlockedPeriod/DeleteBlockedPeriodController.php`
- Create: `src/Availability/UI/Http/Controller/CheckAvailability/CheckAvailabilityRequest.php`
- Create: `src/Availability/UI/Http/Controller/CheckAvailability/CheckAvailabilityController.php`
- Create: `src/Availability/UI/Http/Controller/GetAvailabilityCalendar/GetAvailabilityCalendarController.php`

**Config**
- Create: `config/services/availability.yaml`
- Modify: `config/services/exceptions.yaml`

**Tests**
- Create: `tests/Availability/Domain/ValueObject/DatePeriodTest.php`
- Create: `tests/Availability/Infrastructure/FakeRoomExistenceChecker.php`
- Create: `tests/Availability/Infrastructure/Persistence/InMemory/InMemoryBlockedPeriodRepository.php`
- Create: `tests/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommandHandlerTest.php`
- Create: `tests/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommandHandlerTest.php`
- Create: `tests/Availability/Application/UseCase/GetBlockedPeriod/GetBlockedPeriodQueryHandlerTest.php`
- Create: `tests/Availability/Application/UseCase/CheckAvailability/CheckAvailabilityQueryHandlerTest.php`
- Create: `tests/Availability/Application/UseCase/GetAvailabilityCalendar/GetAvailabilityCalendarQueryHandlerTest.php`
- Create: `tests/Availability/UI/Http/Controller/BlockPeriod/BlockPeriodControllerTest.php`
- Create: `tests/Availability/UI/Http/Controller/DeleteBlockedPeriod/DeleteBlockedPeriodControllerTest.php`
- Create: `tests/Availability/UI/Http/Controller/CheckAvailability/CheckAvailabilityControllerTest.php`
- Create: `tests/Availability/UI/Http/Controller/GetAvailabilityCalendar/GetAvailabilityCalendarControllerTest.php`

---

## Task 1: Create feature branch

- [ ] **Step 1: Check current branch and create the feature branch**

```bash
git branch --show-current
git checkout -b feat/availability-context
```

Expected: you are now on `feat/availability-context`.

- [ ] **Step 2: Commit the domain documentation produced during design**

```bash
git add CONTEXT.md docs/adr/0005-availability-as-separate-context-with-opaque-blocks.md
git commit -m "docs(availability): add domain glossary terms and ADR-0005"
```

---

## Task 2: DatePeriod value object

**Files:**
- Create: `src/Availability/Domain/ValueObject/DatePeriod.php`
- Create: `tests/Availability/Domain/ValueObject/DatePeriodTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Availability/Domain/ValueObject/DatePeriodTest.php
declare(strict_types=1);

namespace App\Tests\Availability\Domain\ValueObject;

use App\Availability\Domain\ValueObject\DatePeriod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DatePeriodTest extends TestCase
{
    #[Test]
    public function itCreatesAValidPeriod(): void
    {
        $checkIn = new \DateTimeImmutable('2025-06-10');
        $checkOut = new \DateTimeImmutable('2025-06-13');

        $period = new DatePeriod($checkIn, $checkOut);

        self::assertSame('2025-06-10', $period->checkIn->format('Y-m-d'));
        self::assertSame('2025-06-13', $period->checkOut->format('Y-m-d'));
    }

    #[Test]
    public function itRejectsWhenCheckInEqualsCheckOut(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DatePeriod(
            new \DateTimeImmutable('2025-06-10'),
            new \DateTimeImmutable('2025-06-10'),
        );
    }

    #[Test]
    public function itRejectsWhenCheckInIsAfterCheckOut(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DatePeriod(
            new \DateTimeImmutable('2025-06-13'),
            new \DateTimeImmutable('2025-06-10'),
        );
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
make unit-test ARGS="--filter DatePeriodTest"
```

Expected: FAIL — class `DatePeriod` not found.

- [ ] **Step 3: Implement DatePeriod**

```php
<?php
// src/Availability/Domain/ValueObject/DatePeriod.php
declare(strict_types=1);

namespace App\Availability\Domain\ValueObject;

final readonly class DatePeriod
{
    public function __construct(
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
        if ($checkIn >= $checkOut) {
            throw new \InvalidArgumentException('Check-in must be strictly before check-out.');
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
make unit-test ARGS="--filter DatePeriodTest"
```

Expected: 3 tests, PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Availability/Domain/ValueObject/DatePeriod.php \
        tests/Availability/Domain/ValueObject/DatePeriodTest.php
git commit -m "feat(availability): add DatePeriod value object"
```

---

## Task 3: Domain model, ports and exceptions

**Files:**
- Create: `src/Availability/Domain/Model/BlockedPeriod.php`
- Create: `src/Availability/Domain/Port/BlockedPeriodRepositoryInterface.php`
- Create: `src/Availability/Domain/Port/RoomExistsInterface.php`
- Create: `src/Availability/Domain/Exception/BlockedPeriodNotFoundException.php`
- Create: `src/Availability/Domain/Exception/BlockedPeriodOverlapException.php`
- Create: `src/Availability/Domain/Exception/RoomNotFoundException.php`

- [ ] **Step 1: Create BlockedPeriod model**

```php
<?php
// src/Availability/Domain/Model/BlockedPeriod.php
declare(strict_types=1);

namespace App\Availability\Domain\Model;

use App\Availability\Domain\ValueObject\DatePeriod;

final readonly class BlockedPeriod
{
    public function __construct(
        public string $id,
        public string $roomId,
        public DatePeriod $period,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 2: Create BlockedPeriodRepositoryInterface**

```php
<?php
// src/Availability/Domain/Port/BlockedPeriodRepositoryInterface.php
declare(strict_types=1);

namespace App\Availability\Domain\Port;

use App\Availability\Domain\Model\BlockedPeriod;

interface BlockedPeriodRepositoryInterface
{
    public function add(BlockedPeriod $period): void;

    public function get(string $id): ?BlockedPeriod;

    public function remove(string $id): void;

    public function hasOverlap(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool;

    /** @return list<BlockedPeriod> */
    public function listByRoomId(string $roomId): array;
}
```

- [ ] **Step 3: Create RoomExistsInterface**

```php
<?php
// src/Availability/Domain/Port/RoomExistsInterface.php
declare(strict_types=1);

namespace App\Availability\Domain\Port;

interface RoomExistsInterface
{
    public function exists(string $roomId): bool;
}
```

- [ ] **Step 4: Create exceptions**

```php
<?php
// src/Availability/Domain/Exception/BlockedPeriodNotFoundException.php
declare(strict_types=1);

namespace App\Availability\Domain\Exception;

final class BlockedPeriodNotFoundException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Blocked period "%s" not found.', $id));
    }
}
```

```php
<?php
// src/Availability/Domain/Exception/BlockedPeriodOverlapException.php
declare(strict_types=1);

namespace App\Availability\Domain\Exception;

final class BlockedPeriodOverlapException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The requested period overlaps with an existing blocked period.');
    }
}
```

```php
<?php
// src/Availability/Domain/Exception/RoomNotFoundException.php
declare(strict_types=1);

namespace App\Availability\Domain\Exception;

final class RoomNotFoundException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Room "%s" not found.', $id));
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add src/Availability/
git commit -m "feat(availability): add domain model, ports and exceptions"
```

---

## Task 4: Test doubles

**Files:**
- Create: `tests/Availability/Infrastructure/FakeRoomExistenceChecker.php`
- Create: `tests/Availability/Infrastructure/Persistence/InMemory/InMemoryBlockedPeriodRepository.php`

- [ ] **Step 1: Create FakeRoomExistenceChecker**

```php
<?php
// tests/Availability/Infrastructure/FakeRoomExistenceChecker.php
declare(strict_types=1);

namespace App\Tests\Availability\Infrastructure;

use App\Availability\Domain\Port\RoomExistsInterface;

final class FakeRoomExistenceChecker implements RoomExistsInterface
{
    private bool $exists = true;

    public function setExists(bool $exists): void
    {
        $this->exists = $exists;
    }

    public function exists(string $roomId): bool
    {
        return $this->exists;
    }
}
```

- [ ] **Step 2: Create InMemoryBlockedPeriodRepository**

```php
<?php
// tests/Availability/Infrastructure/Persistence/InMemory/InMemoryBlockedPeriodRepository.php
declare(strict_types=1);

namespace App\Tests\Availability\Infrastructure\Persistence\InMemory;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;

final class InMemoryBlockedPeriodRepository implements BlockedPeriodRepositoryInterface
{
    /** @var array<string, BlockedPeriod> */
    private array $periods = [];

    public function add(BlockedPeriod $period): void
    {
        $this->periods[$period->id] = $period;
    }

    public function get(string $id): ?BlockedPeriod
    {
        return $this->periods[$id] ?? null;
    }

    public function remove(string $id): void
    {
        unset($this->periods[$id]);
    }

    public function hasOverlap(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        foreach ($this->periods as $period) {
            if ($period->roomId !== $roomId) {
                continue;
            }
            if ($checkIn < $period->period->checkOut && $checkOut > $period->period->checkIn) {
                return true;
            }
        }

        return false;
    }

    /** @return list<BlockedPeriod> */
    public function listByRoomId(string $roomId): array
    {
        $filtered = array_values(array_filter(
            $this->periods,
            static fn(BlockedPeriod $p) => $p->roomId === $roomId,
        ));

        usort($filtered, static fn(BlockedPeriod $a, BlockedPeriod $b) => $a->period->checkIn <=> $b->period->checkIn);

        return $filtered;
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add tests/Availability/
git commit -m "test(availability): add in-memory repository and fake room checker"
```

---

## Task 5: BlockPeriod use case

**Files:**
- Create: `src/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommand.php`
- Create: `src/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommandHandler.php`
- Create: `tests/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommandHandlerTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommandHandlerTest.php
declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\BlockPeriod;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommandHandler;
use App\Availability\Domain\Exception\BlockedPeriodOverlapException;
use App\Availability\Domain\Exception\RoomNotFoundException;
use App\Tests\Availability\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Availability\Infrastructure\Persistence\InMemory\InMemoryBlockedPeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BlockPeriodCommandHandlerTest extends TestCase
{
    private InMemoryBlockedPeriodRepository $repository;
    private FakeRoomExistenceChecker $roomExists;
    private BlockPeriodCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryBlockedPeriodRepository();
        $this->roomExists = new FakeRoomExistenceChecker();
        $this->handler = new BlockPeriodCommandHandler($this->repository, $this->roomExists);
    }

    #[Test]
    public function itPersistsTheBlockedPeriod(): void
    {
        $command = new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        );

        ($this->handler)($command);

        $period = $this->repository->get($command->id);
        self::assertNotNull($period);
        self::assertSame($command->id, $period->id);
        self::assertSame($command->roomId, $period->roomId);
        self::assertSame('2025-06-10', $period->period->checkIn->format('Y-m-d'));
        self::assertSame('2025-06-13', $period->period->checkOut->format('Y-m-d'));
    }

    #[Test]
    public function itThrowsWhenRoomDoesNotExist(): void
    {
        $this->roomExists->setExists(false);
        $this->expectException(RoomNotFoundException::class);

        ($this->handler)(new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itThrowsWhenPeriodOverlapsExistingBlock(): void
    {
        ($this->handler)(new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            createdAt: new \DateTimeImmutable(),
        ));

        $this->expectException(BlockedPeriodOverlapException::class);

        ($this->handler)(new BlockPeriodCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-12'),
            checkOut: new \DateTimeImmutable('2025-06-17'),
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itAllowsAdjacentBlocksOnSameRoom(): void
    {
        ($this->handler)(new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable(),
        ));

        ($this->handler)(new BlockPeriodCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-13'),
            checkOut: new \DateTimeImmutable('2025-06-16'),
            createdAt: new \DateTimeImmutable(),
        ));

        self::assertNotNull($this->repository->get('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));
        self::assertNotNull($this->repository->get('b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22'));
    }

    #[Test]
    public function itAllowsSamePeriodOnDifferentRooms(): void
    {
        ($this->handler)(new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440001',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable(),
        ));

        ($this->handler)(new BlockPeriodCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            roomId: '550e8400-e29b-41d4-a716-446655440002',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable(),
        ));

        self::assertNotNull($this->repository->get('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));
        self::assertNotNull($this->repository->get('b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
make unit-test ARGS="--filter BlockPeriodCommandHandlerTest"
```

Expected: FAIL — class `BlockPeriodCommand` not found.

- [ ] **Step 3: Create BlockPeriodCommand**

```php
<?php
// src/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommand.php
declare(strict_types=1);

namespace App\Availability\Application\UseCase\BlockPeriod;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class BlockPeriodCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 4: Create BlockPeriodCommandHandler**

```php
<?php
// src/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommandHandler.php
declare(strict_types=1);

namespace App\Availability\Application\UseCase\BlockPeriod;

use App\Availability\Domain\Exception\BlockedPeriodOverlapException;
use App\Availability\Domain\Exception\RoomNotFoundException;
use App\Availability\Domain\Model\BlockedPeriod;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Availability\Domain\Port\RoomExistsInterface;
use App\Availability\Domain\ValueObject\DatePeriod;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class BlockPeriodCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private BlockedPeriodRepositoryInterface $repository,
        private RoomExistsInterface $roomExists,
    ) {
    }

    public function __invoke(BlockPeriodCommand $command): void
    {
        if (!$this->roomExists->exists($command->roomId)) {
            throw new RoomNotFoundException($command->roomId);
        }

        if ($this->repository->hasOverlap($command->roomId, $command->checkIn, $command->checkOut)) {
            throw new BlockedPeriodOverlapException();
        }

        $this->repository->add(new BlockedPeriod(
            $command->id,
            $command->roomId,
            new DatePeriod($command->checkIn, $command->checkOut),
            $command->createdAt,
        ));
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
make unit-test ARGS="--filter BlockPeriodCommandHandlerTest"
```

Expected: 5 tests, PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Availability/Application/UseCase/BlockPeriod/ \
        tests/Availability/Application/UseCase/BlockPeriod/
git commit -m "feat(availability): add BlockPeriod use case"
```

---

## Task 6: DeleteBlockedPeriod use case

**Files:**
- Create: `src/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommand.php`
- Create: `src/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommandHandler.php`
- Create: `tests/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommandHandlerTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommandHandlerTest.php
declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\DeleteBlockedPeriod;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommandHandler;
use App\Availability\Application\UseCase\DeleteBlockedPeriod\DeleteBlockedPeriodCommand;
use App\Availability\Application\UseCase\DeleteBlockedPeriod\DeleteBlockedPeriodCommandHandler;
use App\Availability\Domain\Exception\BlockedPeriodNotFoundException;
use App\Tests\Availability\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Availability\Infrastructure\Persistence\InMemory\InMemoryBlockedPeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeleteBlockedPeriodCommandHandlerTest extends TestCase
{
    private InMemoryBlockedPeriodRepository $repository;
    private DeleteBlockedPeriodCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryBlockedPeriodRepository();
        $this->handler = new DeleteBlockedPeriodCommandHandler($this->repository);

        $blockHandler = new BlockPeriodCommandHandler($this->repository, new FakeRoomExistenceChecker());
        ($blockHandler)(new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itRemovesTheBlockedPeriod(): void
    {
        ($this->handler)(new DeleteBlockedPeriodCommand('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));

        self::assertNull($this->repository->get('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));
    }

    #[Test]
    public function itThrowsWhenBlockedPeriodDoesNotExist(): void
    {
        $this->expectException(BlockedPeriodNotFoundException::class);

        ($this->handler)(new DeleteBlockedPeriodCommand('00000000-0000-4000-8000-000000000000'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
make unit-test ARGS="--filter DeleteBlockedPeriodCommandHandlerTest"
```

Expected: FAIL — class `DeleteBlockedPeriodCommand` not found.

- [ ] **Step 3: Create DeleteBlockedPeriodCommand**

```php
<?php
// src/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommand.php
declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteBlockedPeriod;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class DeleteBlockedPeriodCommand implements SyncCommandInterface
{
    public function __construct(public string $id)
    {
    }
}
```

- [ ] **Step 4: Create DeleteBlockedPeriodCommandHandler**

```php
<?php
// src/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommandHandler.php
declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteBlockedPeriod;

use App\Availability\Domain\Exception\BlockedPeriodNotFoundException;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class DeleteBlockedPeriodCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private BlockedPeriodRepositoryInterface $repository)
    {
    }

    public function __invoke(DeleteBlockedPeriodCommand $command): void
    {
        if (null === $this->repository->get($command->id)) {
            throw new BlockedPeriodNotFoundException($command->id);
        }

        $this->repository->remove($command->id);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
make unit-test ARGS="--filter DeleteBlockedPeriodCommandHandlerTest"
```

Expected: 2 tests, PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Availability/Application/UseCase/DeleteBlockedPeriod/ \
        tests/Availability/Application/UseCase/DeleteBlockedPeriod/
git commit -m "feat(availability): add DeleteBlockedPeriod use case"
```

---

## Task 7: GetBlockedPeriod query use case

**Files:**
- Create: `src/Availability/Application/UseCase/GetBlockedPeriod/GetBlockedPeriodQuery.php`
- Create: `src/Availability/Application/UseCase/GetBlockedPeriod/GetBlockedPeriodQueryHandler.php`
- Create: `tests/Availability/Application/UseCase/GetBlockedPeriod/GetBlockedPeriodQueryHandlerTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Availability/Application/UseCase/GetBlockedPeriod/GetBlockedPeriodQueryHandlerTest.php
declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\GetBlockedPeriod;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommandHandler;
use App\Availability\Application\UseCase\GetBlockedPeriod\GetBlockedPeriodQuery;
use App\Availability\Application\UseCase\GetBlockedPeriod\GetBlockedPeriodQueryHandler;
use App\Tests\Availability\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Availability\Infrastructure\Persistence\InMemory\InMemoryBlockedPeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetBlockedPeriodQueryHandlerTest extends TestCase
{
    private InMemoryBlockedPeriodRepository $repository;
    private GetBlockedPeriodQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryBlockedPeriodRepository();
        $this->handler = new GetBlockedPeriodQueryHandler($this->repository);

        $blockHandler = new BlockPeriodCommandHandler($this->repository, new FakeRoomExistenceChecker());
        ($blockHandler)(new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: '550e8400-e29b-41d4-a716-446655440000',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-13'),
            createdAt: new \DateTimeImmutable('2025-01-01 10:00:00'),
        ));
    }

    #[Test]
    public function itReturnsTheBlockedPeriod(): void
    {
        $result = ($this->handler)(new GetBlockedPeriodQuery('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));

        self::assertNotNull($result);
        self::assertSame('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', $result->id);
    }

    #[Test]
    public function itReturnsNullWhenNotFound(): void
    {
        $result = ($this->handler)(new GetBlockedPeriodQuery('00000000-0000-4000-8000-000000000000'));

        self::assertNull($result);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
make unit-test ARGS="--filter GetBlockedPeriodQueryHandlerTest"
```

Expected: FAIL — class `GetBlockedPeriodQuery` not found.

- [ ] **Step 3: Create GetBlockedPeriodQuery**

```php
<?php
// src/Availability/Application/UseCase/GetBlockedPeriod/GetBlockedPeriodQuery.php
declare(strict_types=1);

namespace App\Availability\Application\UseCase\GetBlockedPeriod;

use App\Shared\Application\Bus\SyncQueryInterface;

final readonly class GetBlockedPeriodQuery implements SyncQueryInterface
{
    public function __construct(public string $id)
    {
    }
}
```

- [ ] **Step 4: Create GetBlockedPeriodQueryHandler**

```php
<?php
// src/Availability/Application/UseCase/GetBlockedPeriod/GetBlockedPeriodQueryHandler.php
declare(strict_types=1);

namespace App\Availability\Application\UseCase\GetBlockedPeriod;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetBlockedPeriodQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private BlockedPeriodRepositoryInterface $repository)
    {
    }

    public function __invoke(GetBlockedPeriodQuery $query): ?BlockedPeriod
    {
        return $this->repository->get($query->id);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
make unit-test ARGS="--filter GetBlockedPeriodQueryHandlerTest"
```

Expected: 2 tests, PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Availability/Application/UseCase/GetBlockedPeriod/ \
        tests/Availability/Application/UseCase/GetBlockedPeriod/
git commit -m "feat(availability): add GetBlockedPeriod query use case"
```

---

## Task 8: CheckAvailability query use case

**Files:**
- Create: `src/Availability/Application/UseCase/CheckAvailability/CheckAvailabilityQuery.php`
- Create: `src/Availability/Application/UseCase/CheckAvailability/CheckAvailabilityQueryHandler.php`
- Create: `tests/Availability/Application/UseCase/CheckAvailability/CheckAvailabilityQueryHandlerTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Availability/Application/UseCase/CheckAvailability/CheckAvailabilityQueryHandlerTest.php
declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\CheckAvailability;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommandHandler;
use App\Availability\Application\UseCase\CheckAvailability\CheckAvailabilityQuery;
use App\Availability\Application\UseCase\CheckAvailability\CheckAvailabilityQueryHandler;
use App\Tests\Availability\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Availability\Infrastructure\Persistence\InMemory\InMemoryBlockedPeriodRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CheckAvailabilityQueryHandlerTest extends TestCase
{
    private const string ROOM_ID = '550e8400-e29b-41d4-a716-446655440000';

    private InMemoryBlockedPeriodRepository $repository;
    private CheckAvailabilityQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryBlockedPeriodRepository();
        $this->handler = new CheckAvailabilityQueryHandler($this->repository);

        $blockHandler = new BlockPeriodCommandHandler($this->repository, new FakeRoomExistenceChecker());
        ($blockHandler)(new BlockPeriodCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itReturnsTrueWhenNoOverlap(): void
    {
        $result = ($this->handler)(new CheckAvailabilityQuery(
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-06-15'),
            checkOut: new \DateTimeImmutable('2025-06-18'),
        ));

        self::assertTrue($result);
    }

    #[Test]
    public function itReturnsFalseWhenOverlap(): void
    {
        $result = ($this->handler)(new CheckAvailabilityQuery(
            roomId: self::ROOM_ID,
            checkIn: new \DateTimeImmutable('2025-06-12'),
            checkOut: new \DateTimeImmutable('2025-06-17'),
        ));

        self::assertFalse($result);
    }

    #[Test]
    public function itReturnsTrueForDifferentRoom(): void
    {
        $result = ($this->handler)(new CheckAvailabilityQuery(
            roomId: '550e8400-e29b-41d4-a716-446655440099',
            checkIn: new \DateTimeImmutable('2025-06-10'),
            checkOut: new \DateTimeImmutable('2025-06-15'),
        ));

        self::assertTrue($result);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
make unit-test ARGS="--filter CheckAvailabilityQueryHandlerTest"
```

Expected: FAIL — class `CheckAvailabilityQuery` not found.

- [ ] **Step 3: Create CheckAvailabilityQuery**

```php
<?php
// src/Availability/Application/UseCase/CheckAvailability/CheckAvailabilityQuery.php
declare(strict_types=1);

namespace App\Availability\Application\UseCase\CheckAvailability;

use App\Shared\Application\Bus\SyncQueryInterface;

final readonly class CheckAvailabilityQuery implements SyncQueryInterface
{
    public function __construct(
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}
```

- [ ] **Step 4: Create CheckAvailabilityQueryHandler**

```php
<?php
// src/Availability/Application/UseCase/CheckAvailability/CheckAvailabilityQueryHandler.php
declare(strict_types=1);

namespace App\Availability\Application\UseCase\CheckAvailability;

use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class CheckAvailabilityQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private BlockedPeriodRepositoryInterface $repository)
    {
    }

    public function __invoke(CheckAvailabilityQuery $query): bool
    {
        return !$this->repository->hasOverlap($query->roomId, $query->checkIn, $query->checkOut);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
make unit-test ARGS="--filter CheckAvailabilityQueryHandlerTest"
```

Expected: 3 tests, PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Availability/Application/UseCase/CheckAvailability/ \
        tests/Availability/Application/UseCase/CheckAvailability/
git commit -m "feat(availability): add CheckAvailability query use case"
```

---

## Task 9: GetAvailabilityCalendar query use case

**Files:**
- Create: `src/Availability/Application/UseCase/GetAvailabilityCalendar/GetAvailabilityCalendarQuery.php`
- Create: `src/Availability/Application/UseCase/GetAvailabilityCalendar/GetAvailabilityCalendarQueryHandler.php`
- Create: `tests/Availability/Application/UseCase/GetAvailabilityCalendar/GetAvailabilityCalendarQueryHandlerTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Availability/Application/UseCase/GetAvailabilityCalendar/GetAvailabilityCalendarQueryHandlerTest.php
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

        $blockHandler = new BlockPeriodCommandHandler($this->repository, new FakeRoomExistenceChecker());
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
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
make unit-test ARGS="--filter GetAvailabilityCalendarQueryHandlerTest"
```

Expected: FAIL — class `GetAvailabilityCalendarQuery` not found.

- [ ] **Step 3: Create GetAvailabilityCalendarQuery**

```php
<?php
// src/Availability/Application/UseCase/GetAvailabilityCalendar/GetAvailabilityCalendarQuery.php
declare(strict_types=1);

namespace App\Availability\Application\UseCase\GetAvailabilityCalendar;

use App\Shared\Application\Bus\SyncQueryInterface;

final readonly class GetAvailabilityCalendarQuery implements SyncQueryInterface
{
    public function __construct(public string $roomId)
    {
    }
}
```

- [ ] **Step 4: Create GetAvailabilityCalendarQueryHandler**

```php
<?php
// src/Availability/Application/UseCase/GetAvailabilityCalendar/GetAvailabilityCalendarQueryHandler.php
declare(strict_types=1);

namespace App\Availability\Application\UseCase\GetAvailabilityCalendar;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetAvailabilityCalendarQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private BlockedPeriodRepositoryInterface $repository)
    {
    }

    /** @return list<BlockedPeriod> */
    public function __invoke(GetAvailabilityCalendarQuery $query): array
    {
        return $this->repository->listByRoomId($query->roomId);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
make unit-test ARGS="--filter GetAvailabilityCalendarQueryHandlerTest"
```

Expected: 2 tests, PASS.

- [ ] **Step 6: Run all unit tests to verify nothing is broken**

```bash
make unit-test
```

Expected: all tests PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Availability/Application/UseCase/GetAvailabilityCalendar/ \
        tests/Availability/Application/UseCase/GetAvailabilityCalendar/
git commit -m "feat(availability): add GetAvailabilityCalendar query use case"
```

---

## Task 10: Application service layer (ID generator + command factory)

**Files:**
- Create: `src/Availability/Application/Service/BlockedPeriodIdGeneratorInterface.php`
- Create: `src/Availability/Application/Service/BlockPeriodCommandFactory.php`
- Create: `src/Availability/Infrastructure/Service/BlockedPeriodIdGenerator.php`

- [ ] **Step 1: Create BlockedPeriodIdGeneratorInterface**

```php
<?php
// src/Availability/Application/Service/BlockedPeriodIdGeneratorInterface.php
declare(strict_types=1);

namespace App\Availability\Application\Service;

interface BlockedPeriodIdGeneratorInterface
{
    public function generate(): string;
}
```

- [ ] **Step 2: Create BlockPeriodCommandFactory**

```php
<?php
// src/Availability/Application/Service/BlockPeriodCommandFactory.php
declare(strict_types=1);

namespace App\Availability\Application\Service;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use Psr\Clock\ClockInterface;

final readonly class BlockPeriodCommandFactory
{
    public function __construct(
        private BlockedPeriodIdGeneratorInterface $idGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(string $roomId, string $checkIn, string $checkOut): BlockPeriodCommand
    {
        return new BlockPeriodCommand(
            id: $this->idGenerator->generate(),
            roomId: $roomId,
            checkIn: new \DateTimeImmutable($checkIn),
            checkOut: new \DateTimeImmutable($checkOut),
            createdAt: $this->clock->now(),
        );
    }
}
```

- [ ] **Step 3: Create BlockedPeriodIdGenerator (infrastructure)**

```php
<?php
// src/Availability/Infrastructure/Service/BlockedPeriodIdGenerator.php
declare(strict_types=1);

namespace App\Availability\Infrastructure\Service;

use App\Availability\Application\Service\BlockedPeriodIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class BlockedPeriodIdGenerator implements BlockedPeriodIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add src/Availability/Application/Service/ \
        src/Availability/Infrastructure/Service/
git commit -m "feat(availability): add ID generator and command factory"
```

---

## Task 11: Migration and Doctrine repository

**Files:**
- Create: migration (generated)
- Create: `src/Availability/Infrastructure/Persistence/Doctrine/BlockedPeriodRepository.php`
- Create: `src/Availability/Infrastructure/Persistence/Doctrine/RoomExistenceChecker.php`

- [ ] **Step 1: Generate the migration file**

```bash
make generate-migration
```

Expected: a new file created in `migrations/` like `Version20260518XXXXXX.php`.

- [ ] **Step 2: Write the migration SQL**

Open the generated file and replace the `up()` and `down()` methods:

```php
public function getDescription(): string
{
    return 'Create blocked_period table for Availability context';
}

public function up(Schema $schema): void
{
    $this->addSql('CREATE TABLE blocked_period (
        id UUID NOT NULL,
        room_id UUID NOT NULL,
        check_in DATE NOT NULL,
        check_out DATE NOT NULL,
        created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
        PRIMARY KEY (id)
    )');
    $this->addSql('CREATE INDEX idx_blocked_period_room_id ON blocked_period (room_id)');
}

public function down(Schema $schema): void
{
    $this->addSql('DROP TABLE blocked_period');
}
```

- [ ] **Step 3: Run the migration**

```bash
make migrate
```

Expected: migration applied with no errors.

- [ ] **Step 4: Create BlockedPeriodRepository**

```php
<?php
// src/Availability/Infrastructure/Persistence/Doctrine/BlockedPeriodRepository.php
declare(strict_types=1);

namespace App\Availability\Infrastructure\Persistence\Doctrine;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Availability\Domain\ValueObject\DatePeriod;
use Doctrine\DBAL\Connection;

final readonly class BlockedPeriodRepository implements BlockedPeriodRepositoryInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function add(BlockedPeriod $period): void
    {
        $this->bookit->insert('blocked_period', [
            'id' => $period->id,
            'room_id' => $period->roomId,
            'check_in' => $period->period->checkIn->format('Y-m-d'),
            'check_out' => $period->period->checkOut->format('Y-m-d'),
            'created_at' => $period->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $id): ?BlockedPeriod
    {
        /** @var array{id: string, room_id: string, check_in: string, check_out: string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, room_id, check_in, check_out, created_at FROM blocked_period WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function remove(string $id): void
    {
        $this->bookit->delete('blocked_period', ['id' => $id]);
    }

    public function hasOverlap(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM blocked_period
             WHERE room_id = :roomId
               AND check_in < :checkOut
               AND check_out > :checkIn',
            [
                'roomId' => $roomId,
                'checkIn' => $checkIn->format('Y-m-d'),
                'checkOut' => $checkOut->format('Y-m-d'),
            ],
        );

        return $count > 0;
    }

    /** @return list<BlockedPeriod> */
    public function listByRoomId(string $roomId): array
    {
        /** @var list<array{id: string, room_id: string, check_in: string, check_out: string, created_at: string}> $rows */
        $rows = $this->bookit->fetchAllAssociative(
            'SELECT id, room_id, check_in, check_out, created_at FROM blocked_period
             WHERE room_id = :roomId
             ORDER BY check_in ASC',
            ['roomId' => $roomId],
        );

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * @param array{id: string, room_id: string, check_in: string, check_out: string, created_at: string} $row
     */
    private function hydrate(array $row): BlockedPeriod
    {
        return new BlockedPeriod(
            $row['id'],
            $row['room_id'],
            new DatePeriod(
                new \DateTimeImmutable($row['check_in']),
                new \DateTimeImmutable($row['check_out']),
            ),
            new \DateTimeImmutable($row['created_at']),
        );
    }
}
```

- [ ] **Step 5: Create RoomExistenceChecker**

```php
<?php
// src/Availability/Infrastructure/Persistence/Doctrine/RoomExistenceChecker.php
declare(strict_types=1);

namespace App\Availability\Infrastructure\Persistence\Doctrine;

use App\Availability\Domain\Port\RoomExistsInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;

final readonly class RoomExistenceChecker implements RoomExistsInterface
{
    public function __construct(private RoomRepositoryInterface $roomRepository)
    {
    }

    public function exists(string $roomId): bool
    {
        return null !== $this->roomRepository->get($roomId);
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add migrations/ \
        src/Availability/Infrastructure/Persistence/
git commit -m "feat(availability): add migration and Doctrine repository"
```

---

## Task 12: Service configuration and exception mappings

**Files:**
- Create: `config/services/availability.yaml`
- Modify: `config/services/exceptions.yaml`

- [ ] **Step 1: Create availability service configuration**

```yaml
# config/services/availability.yaml
parameters: {}

services:
    _defaults:
        autowire: true
        autoconfigure: true
    _instanceof:
        App\Shared\Application\Bus\SyncCommandHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: sync.command.bus}
        App\Shared\Application\Bus\SyncQueryHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: sync.query.bus}

    App\Availability\Domain\:
        resource: '../../src/Availability/Domain/'
        exclude:
            - '../../src/Availability/Domain/Model/'

    App\Availability\Application\:
        resource: '../../src/Availability/Application/'
        exclude:
            - '../../src/Availability/Application/**/*Command.php'
            - '../../src/Availability/Application/**/*Query.php'

    App\Availability\Infrastructure\:
        resource: '../../src/Availability/Infrastructure/'

    App\Availability\UI\:
        resource: '../../src/Availability/UI/'
        exclude:
            - '../../src/Availability/UI/**/*Request.php'
```

- [ ] **Step 2: Register the config file**

Open `config/services.yaml` and add an import for availability:

```yaml
# config/services.yaml  — add alongside the existing imports
imports:
    - { resource: services/shared.yaml }
    - { resource: services/hotel.yaml }
    - { resource: services/room.yaml }
    - { resource: services/booker.yaml }
    - { resource: services/availability.yaml }  # add this line
    - { resource: services/exceptions.yaml }
```

- [ ] **Step 3: Add exception mappings**

In `config/services/exceptions.yaml`, add inside the `$map` block:

```yaml
                App\Availability\Domain\Exception\RoomNotFoundException:
                    type: 'https://book.it/problems/room-not-found'
                    title: 'Room Not Found'
                    status: 404
                App\Availability\Domain\Exception\BlockedPeriodOverlapException:
                    type: 'https://book.it/problems/blocked-period-overlap'
                    title: 'Blocked Period Overlap'
                    status: 409
                App\Availability\Domain\Exception\BlockedPeriodNotFoundException:
                    type: 'https://book.it/problems/blocked-period-not-found'
                    title: 'Blocked Period Not Found'
                    status: 404
```

- [ ] **Step 4: Verify the container compiles**

```bash
make unit-test ARGS="--filter DatePeriodTest"
```

Expected: PASS (container must compile for unit-test runner to boot).

- [ ] **Step 5: Commit**

```bash
git add config/services/availability.yaml config/services.yaml config/services/exceptions.yaml
git commit -m "feat(availability): add service config and exception mappings"
```

---

## Task 13: BlockedPeriodSerializer

**Files:**
- Create: `src/Availability/UI/Http/Controller/BlockedPeriodSerializer.php`

- [ ] **Step 1: Create the serializer**

```php
<?php
// src/Availability/UI/Http/Controller/BlockedPeriodSerializer.php
declare(strict_types=1);

namespace App\Availability\UI\Http\Controller;

use App\Availability\Domain\Model\BlockedPeriod;

final class BlockedPeriodSerializer
{
    /**
     * @return array{id: string, roomId: string, checkIn: string, checkOut: string, createdAt: int}
     */
    public function serialize(BlockedPeriod $period): array
    {
        return [
            'id' => $period->id,
            'roomId' => $period->roomId,
            'checkIn' => $period->period->checkIn->format('Y-m-d'),
            'checkOut' => $period->period->checkOut->format('Y-m-d'),
            'createdAt' => $period->createdAt->getTimestamp(),
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Availability/UI/Http/Controller/BlockedPeriodSerializer.php
git commit -m "feat(availability): add BlockedPeriodSerializer"
```

---

## Task 14: BlockPeriod HTTP controller

**Files:**
- Create: `src/Availability/UI/Http/Controller/BlockPeriod/BlockPeriodRequest.php`
- Create: `src/Availability/UI/Http/Controller/BlockPeriod/BlockPeriodController.php`
- Create: `tests/Availability/UI/Http/Controller/BlockPeriod/BlockPeriodControllerTest.php`

**API:** `POST /api/rooms/{roomId}/blocked-periods` → 201 with blocked period body

- [ ] **Step 1: Write the failing functional tests**

```php
<?php
// tests/Availability/UI/Http/Controller/BlockPeriod/BlockPeriodControllerTest.php
declare(strict_types=1);

namespace App\Tests\Availability\UI\Http\Controller\BlockPeriod;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class BlockPeriodControllerTest extends WebTestCase
{
    #[Test]
    public function itBlocksAPeriodAndReturns201(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/rooms/{$roomId}/blocked-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-06-10', 'checkOut' => '2025-06-13'], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{id: string, roomId: string, checkIn: string, checkOut: string, createdAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['id']);
        self::assertSame($roomId, $body['roomId']);
        self::assertSame('2025-06-10', $body['checkIn']);
        self::assertSame('2025-06-13', $body['checkOut']);
        self::assertGreaterThan(0, $body['createdAt']);
    }

    #[Test]
    public function itReturns409WhenOverlapExists(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/rooms/{$roomId}/blocked-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-06-10', 'checkOut' => '2025-06-15'], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'POST',
            uri: "/api/rooms/{$roomId}/blocked-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-06-12', 'checkOut' => '2025-06-17'], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/blocked-period-overlap', $body['type']);
        self::assertSame('Blocked Period Overlap', $body['title']);
        self::assertSame(Response::HTTP_CONFLICT, $body['status']);
    }

    #[Test]
    public function itReturns404WhenRoomDoesNotExist(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/rooms/00000000-0000-4000-8000-000000000000/blocked-periods',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-06-10', 'checkOut' => '2025-06-13'], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-not-found', $body['type']);
        self::assertSame('Room Not Found', $body['title']);
        self::assertSame(Response::HTTP_NOT_FOUND, $body['status']);
    }

    #[Test]
    public function itReturns422WhenCheckInIsMissing(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/rooms/{$roomId}/blocked-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkOut' => '2025-06-13'], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenCheckOutIsMissing(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/rooms/{$roomId}/blocked-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-06-10'], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenDateFormatIsInvalid(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/rooms/{$roomId}/blocked-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => 'not-a-date', 'checkOut' => '2025-06-13'], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns404WhenRoomIdIsNotAValidUuidV4(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/rooms/not-a-uuid/blocked-periods',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-06-10', 'checkOut' => '2025-06-13'], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    private function registerRoomAndGetId(KernelBrowser $client): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Hotel Test',
                'streetAddress' => '1 rue de la Paix',
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
            ], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $hotelBody */
        $hotelBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $hotelId = $hotelBody['id'];

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $roomBody */
        $roomBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $roomBody['id'];
    }
}
```

- [ ] **Step 2: Run functional tests to verify they fail**

```bash
make functional-test ARGS="--filter BlockPeriodControllerTest"
```

Expected: FAIL — route not found / controller not registered.

- [ ] **Step 3: Create BlockPeriodRequest**

```php
<?php
// src/Availability/UI/Http/Controller/BlockPeriod/BlockPeriodRequest.php
declare(strict_types=1);

namespace App\Availability\UI\Http\Controller\BlockPeriod;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class BlockPeriodRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(type: 'string', format: 'date', example: '2025-06-10')]
        public ?string $checkIn = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(type: 'string', format: 'date', example: '2025-06-13')]
        public ?string $checkOut = null,
    ) {
    }
}
```

- [ ] **Step 4: Create BlockPeriodController**

```php
<?php
// src/Availability/UI/Http/Controller/BlockPeriod/BlockPeriodController.php
declare(strict_types=1);

namespace App\Availability\UI\Http\Controller\BlockPeriod;

use App\Availability\Application\Service\BlockPeriodCommandFactory;
use App\Availability\Application\UseCase\GetBlockedPeriod\GetBlockedPeriodQuery;
use App\Availability\UI\Http\Controller\BlockedPeriodSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class BlockPeriodController
{
    public function __construct(
        private BlockPeriodCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private BlockedPeriodSerializer $serializer,
    ) {
    }

    #[Route('/api/rooms/{roomId}/blocked-periods', name: 'availability_block_period', requirements: ['roomId' => Requirement::UUID_V4], methods: ['POST'])]
    #[OA\Post(
        summary: 'Block a period for a room',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: BlockPeriodRequest::class)),
        ),
        tags: ['Availability'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Period blocked',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'checkIn', type: 'string', format: 'date', example: '2025-06-10'),
                        new OA\Property(property: 'checkOut', type: 'string', format: 'date', example: '2025-06-13'),
                        new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Room not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Period overlaps existing block', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $roomId,
        #[MapRequestPayload(acceptFormat: 'json')] BlockPeriodRequest $request,
    ): Response {
        $command = $this->commandFactory->create($roomId, (string) $request->checkIn, (string) $request->checkOut);
        $this->commandBus->execute($command);

        $period = $this->queryBus->ask(new GetBlockedPeriodQuery($command->id));
        if (null === $period) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->serializer->serialize($period), Response::HTTP_CREATED);
    }
}
```

- [ ] **Step 5: Run functional tests to verify they pass**

```bash
make functional-test ARGS="--filter BlockPeriodControllerTest"
```

Expected: all tests PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Availability/UI/Http/Controller/BlockPeriod/ \
        tests/Availability/UI/Http/Controller/BlockPeriod/
git commit -m "feat(availability): add POST /api/rooms/{roomId}/blocked-periods endpoint"
```

---

## Task 15: DeleteBlockedPeriod HTTP controller

**Files:**
- Create: `src/Availability/UI/Http/Controller/DeleteBlockedPeriod/DeleteBlockedPeriodController.php`
- Create: `tests/Availability/UI/Http/Controller/DeleteBlockedPeriod/DeleteBlockedPeriodControllerTest.php`

**API:** `DELETE /api/blocked-periods/{id}` → 204

- [ ] **Step 1: Write the failing functional tests**

```php
<?php
// tests/Availability/UI/Http/Controller/DeleteBlockedPeriod/DeleteBlockedPeriodControllerTest.php
declare(strict_types=1);

namespace App\Tests\Availability\UI\Http\Controller\DeleteBlockedPeriod;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class DeleteBlockedPeriodControllerTest extends WebTestCase
{
    #[Test]
    public function itDeletesABlockedPeriodAndReturns204(): void
    {
        $client = static::createClient();
        $blockedPeriodId = $this->blockPeriodAndGetId($client);

        $client->request('DELETE', "/api/blocked-periods/{$blockedPeriodId}");

        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns404WhenBlockedPeriodDoesNotExist(): void
    {
        $client = static::createClient();

        $client->request('DELETE', '/api/blocked-periods/00000000-0000-4000-8000-000000000000');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/blocked-period-not-found', $body['type']);
        self::assertSame('Blocked Period Not Found', $body['title']);
        self::assertSame(Response::HTTP_NOT_FOUND, $body['status']);
    }

    #[Test]
    public function itReturns404WhenIdIsNotAValidUuidV4(): void
    {
        $client = static::createClient();

        $client->request('DELETE', '/api/blocked-periods/not-a-uuid');

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    private function blockPeriodAndGetId(KernelBrowser $client): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Hotel Test',
                'streetAddress' => '1 rue de la Paix',
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
            ], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $hotelBody */
        $hotelBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelBody['id']}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $roomBody */
        $roomBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request(
            method: 'POST',
            uri: "/api/rooms/{$roomBody['id']}/blocked-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-06-10', 'checkOut' => '2025-06-13'], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $periodBody */
        $periodBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $periodBody['id'];
    }
}
```

- [ ] **Step 2: Run functional tests to verify they fail**

```bash
make functional-test ARGS="--filter DeleteBlockedPeriodControllerTest"
```

Expected: FAIL — route not found.

- [ ] **Step 3: Create DeleteBlockedPeriodController**

```php
<?php
// src/Availability/UI/Http/Controller/DeleteBlockedPeriod/DeleteBlockedPeriodController.php
declare(strict_types=1);

namespace App\Availability\UI\Http\Controller\DeleteBlockedPeriod;

use App\Availability\Application\UseCase\DeleteBlockedPeriod\DeleteBlockedPeriodCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class DeleteBlockedPeriodController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route('/api/blocked-periods/{id}', name: 'availability_delete_blocked_period', requirements: ['id' => Requirement::UUID_V4], methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Delete a blocked period',
        tags: ['Availability'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Blocked period deleted'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Blocked period not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(string $id): Response
    {
        $this->commandBus->execute(new DeleteBlockedPeriodCommand($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 4: Run functional tests to verify they pass**

```bash
make functional-test ARGS="--filter DeleteBlockedPeriodControllerTest"
```

Expected: all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Availability/UI/Http/Controller/DeleteBlockedPeriod/ \
        tests/Availability/UI/Http/Controller/DeleteBlockedPeriod/
git commit -m "feat(availability): add DELETE /api/blocked-periods/{id} endpoint"
```

---

## Task 16: CheckAvailability HTTP controller

**Files:**
- Create: `src/Availability/UI/Http/Controller/CheckAvailability/CheckAvailabilityRequest.php`
- Create: `src/Availability/UI/Http/Controller/CheckAvailability/CheckAvailabilityController.php`
- Create: `tests/Availability/UI/Http/Controller/CheckAvailability/CheckAvailabilityControllerTest.php`

**API:** `GET /api/rooms/{roomId}/availability?checkIn=Y-m-d&checkOut=Y-m-d` → 200 `{ "available": bool }`

- [ ] **Step 1: Write the failing functional tests**

```php
<?php
// tests/Availability/UI/Http/Controller/CheckAvailability/CheckAvailabilityControllerTest.php
declare(strict_types=1);

namespace App\Tests\Availability\UI\Http\Controller\CheckAvailability;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class CheckAvailabilityControllerTest extends WebTestCase
{
    #[Test]
    public function itReturnsTrueWhenRoomIsAvailable(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request('GET', "/api/rooms/{$roomId}/availability?checkIn=2025-06-10&checkOut=2025-06-13");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{available: bool} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($body['available']);
    }

    #[Test]
    public function itReturnsFalseWhenRoomIsBlocked(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/rooms/{$roomId}/blocked-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => '2025-06-10', 'checkOut' => '2025-06-15'], \JSON_THROW_ON_ERROR),
        );

        $client->request('GET', "/api/rooms/{$roomId}/availability?checkIn=2025-06-12&checkOut=2025-06-17");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{available: bool} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertFalse($body['available']);
    }

    #[Test]
    public function itReturns422WhenCheckInIsMissing(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request('GET', "/api/rooms/{$roomId}/availability?checkOut=2025-06-13");

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenDateFormatIsInvalid(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request('GET', "/api/rooms/{$roomId}/availability?checkIn=not-a-date&checkOut=2025-06-13");

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns404WhenRoomIdIsNotAValidUuidV4(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/rooms/not-a-uuid/availability?checkIn=2025-06-10&checkOut=2025-06-13');

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    private function registerRoomAndGetId(KernelBrowser $client): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Hotel Test',
                'streetAddress' => '1 rue de la Paix',
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
            ], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $hotelBody */
        $hotelBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelBody['id']}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $roomBody */
        $roomBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $roomBody['id'];
    }
}
```

- [ ] **Step 2: Run functional tests to verify they fail**

```bash
make functional-test ARGS="--filter CheckAvailabilityControllerTest"
```

Expected: FAIL — route not found.

- [ ] **Step 3: Create CheckAvailabilityRequest**

```php
<?php
// src/Availability/UI/Http/Controller/CheckAvailability/CheckAvailabilityRequest.php
declare(strict_types=1);

namespace App\Availability\UI\Http\Controller\CheckAvailability;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CheckAvailabilityRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Parameter(in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date', example: '2025-06-10'))]
        public ?string $checkIn = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Parameter(in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date', example: '2025-06-13'))]
        public ?string $checkOut = null,
    ) {
    }
}
```

- [ ] **Step 4: Create CheckAvailabilityController**

```php
<?php
// src/Availability/UI/Http/Controller/CheckAvailability/CheckAvailabilityController.php
declare(strict_types=1);

namespace App\Availability\UI\Http\Controller\CheckAvailability;

use App\Availability\Application\UseCase\CheckAvailability\CheckAvailabilityQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class CheckAvailabilityController
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    #[Route('/api/rooms/{roomId}/availability', name: 'availability_check', requirements: ['roomId' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'Check whether a room is available for a given period',
        tags: ['Availability'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Availability result',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'available', type: 'boolean')],
                ),
            ),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $roomId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] CheckAvailabilityRequest $request,
    ): Response {
        $available = $this->queryBus->ask(new CheckAvailabilityQuery(
            roomId: $roomId,
            checkIn: new \DateTimeImmutable((string) $request->checkIn),
            checkOut: new \DateTimeImmutable((string) $request->checkOut),
        ));

        return new JsonResponse(['available' => $available]);
    }
}
```

- [ ] **Step 5: Run functional tests to verify they pass**

```bash
make functional-test ARGS="--filter CheckAvailabilityControllerTest"
```

Expected: all tests PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Availability/UI/Http/Controller/CheckAvailability/ \
        tests/Availability/UI/Http/Controller/CheckAvailability/
git commit -m "feat(availability): add GET /api/rooms/{roomId}/availability endpoint"
```

---

## Task 17: GetAvailabilityCalendar HTTP controller

**Files:**
- Create: `src/Availability/UI/Http/Controller/GetAvailabilityCalendar/GetAvailabilityCalendarController.php`
- Create: `tests/Availability/UI/Http/Controller/GetAvailabilityCalendar/GetAvailabilityCalendarControllerTest.php`

**API:** `GET /api/rooms/{roomId}/blocked-periods` → 200 `{ "blockedPeriods": [...] }`

- [ ] **Step 1: Write the failing functional tests**

```php
<?php
// tests/Availability/UI/Http/Controller/GetAvailabilityCalendar/GetAvailabilityCalendarControllerTest.php
declare(strict_types=1);

namespace App\Tests\Availability\UI\Http\Controller\GetAvailabilityCalendar;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetAvailabilityCalendarControllerTest extends WebTestCase
{
    #[Test]
    public function itReturnsBlockedPeriodsOrderedByCheckIn(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request('POST', "/api/rooms/{$roomId}/blocked-periods", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['checkIn' => '2025-06-15', 'checkOut' => '2025-06-18'], \JSON_THROW_ON_ERROR));
        $client->request('POST', "/api/rooms/{$roomId}/blocked-periods", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['checkIn' => '2025-06-10', 'checkOut' => '2025-06-13'], \JSON_THROW_ON_ERROR));

        $client->request('GET', "/api/rooms/{$roomId}/blocked-periods");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{blockedPeriods: list<array{id: string, roomId: string, checkIn: string, checkOut: string, createdAt: int}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['blockedPeriods']);
        self::assertSame('2025-06-10', $body['blockedPeriods'][0]['checkIn']);
        self::assertSame('2025-06-15', $body['blockedPeriods'][1]['checkIn']);
        self::assertSame($roomId, $body['blockedPeriods'][0]['roomId']);
    }

    #[Test]
    public function itReturnsEmptyListWhenNoBlocks(): void
    {
        $client = static::createClient();
        $roomId = $this->registerRoomAndGetId($client);

        $client->request('GET', "/api/rooms/{$roomId}/blocked-periods");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{blockedPeriods: list<mixed>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([], $body['blockedPeriods']);
    }

    #[Test]
    public function itReturns404WhenRoomIdIsNotAValidUuidV4(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/rooms/not-a-uuid/blocked-periods');

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    private function registerRoomAndGetId(KernelBrowser $client): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Hotel Test',
                'streetAddress' => '1 rue de la Paix',
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
            ], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $hotelBody */
        $hotelBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelBody['id']}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $roomBody */
        $roomBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $roomBody['id'];
    }
}
```

- [ ] **Step 2: Run functional tests to verify they fail**

```bash
make functional-test ARGS="--filter GetAvailabilityCalendarControllerTest"
```

Expected: FAIL — route not found.

- [ ] **Step 3: Create GetAvailabilityCalendarController**

```php
<?php
// src/Availability/UI/Http/Controller/GetAvailabilityCalendar/GetAvailabilityCalendarController.php
declare(strict_types=1);

namespace App\Availability\UI\Http\Controller\GetAvailabilityCalendar;

use App\Availability\Application\UseCase\GetAvailabilityCalendar\GetAvailabilityCalendarQuery;
use App\Availability\UI\Http\Controller\BlockedPeriodSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetAvailabilityCalendarController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private BlockedPeriodSerializer $serializer,
    ) {
    }

    #[Route('/api/rooms/{roomId}/blocked-periods', name: 'availability_get_calendar', requirements: ['roomId' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'Get all blocked periods for a room',
        tags: ['Availability'],
        parameters: [
            new OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Availability calendar',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'blockedPeriods',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'checkIn', type: 'string', format: 'date', example: '2025-06-10'),
                                    new OA\Property(property: 'checkOut', type: 'string', format: 'date', example: '2025-06-13'),
                                    new OA\Property(property: 'createdAt', type: 'integer'),
                                ],
                            ),
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(string $roomId): Response
    {
        $periods = $this->queryBus->ask(new GetAvailabilityCalendarQuery($roomId));

        return new JsonResponse([
            'blockedPeriods' => array_map($this->serializer->serialize(...), $periods),
        ]);
    }
}
```

- [ ] **Step 4: Run functional tests to verify they pass**

```bash
make functional-test ARGS="--filter GetAvailabilityCalendarControllerTest"
```

Expected: all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Availability/UI/Http/Controller/GetAvailabilityCalendar/ \
        tests/Availability/UI/Http/Controller/GetAvailabilityCalendar/
git commit -m "feat(availability): add GET /api/rooms/{roomId}/blocked-periods endpoint"
```

---

## Task 18: Full test suite and OpenAPI

- [ ] **Step 1: Run the complete test suite**

```bash
make test
```

Expected: all unit, integration, and functional tests PASS.

- [ ] **Step 2: Regenerate the OpenAPI spec**

```bash
make openapi
```

Expected: `openapi.yaml` updated at project root with no errors.

- [ ] **Step 3: Commit**

```bash
git add openapi.yaml
git commit -m "docs(availability): regenerate openapi spec"
```

---

## Done

All endpoints are implemented and tested:

| Method | Path | Description |
|---|---|---|
| `POST` | `/api/rooms/{roomId}/blocked-periods` | Block a period (201) |
| `DELETE` | `/api/blocked-periods/{id}` | Delete a blocked period (204) |
| `GET` | `/api/rooms/{roomId}/availability?checkIn=&checkOut=` | Check availability (200) |
| `GET` | `/api/rooms/{roomId}/blocked-periods` | Get availability calendar (200) |

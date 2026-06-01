# Search — Availability Events, Projectors & Endpoint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the Search context: Availability events dispatched, `App\Search\` context skeleton, all projectors (listeners that populate the 3 tables), and the `GET /search` endpoint.

**Architecture:** Projectors are `#[AsEventListener]` classes in `App\Search\Infrastructure\EventListener\` that write directly to the search tables via DBAL. The query handler executes a parameterized SQL query against the same tables. No Doctrine ORM entities for Search — all reads and writes use `doctrine.dbal.search_connection` (isolated via `search_path=search` by `SearchPathMiddleware`). SQL in projectors and the query handler uses **unqualified** table names (`hotel_room_types`, `room_index`, `unavailable_periods`) — the middleware resolves them to the `search` schema. Migration SQL uses schema-qualified names (`search.hotel_room_types`, etc.) because migrations run without the middleware.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL, PHPUnit (unit tests for Availability handler dispatch; functional tests for the search endpoint)

**Prerequisite:** Plan 1 must be fully applied — migration and Hotel/Room events must be in place.

---

## File Map

**New files:**
- `src/Shared/Domain/Event/BlockedPeriodCreated.php`
- `src/Shared/Domain/Event/BlockedPeriodDeleted.php`
- `src/Shared/Domain/Event/AvailabilityHoldCreated.php`
- `src/Shared/Domain/Event/AvailabilityHoldDeleted.php`
- `src/Search/Infrastructure/EventListener/HotelRegisteredListener.php`
- `src/Search/Infrastructure/EventListener/StarRatingClassifiedListener.php`
- `src/Search/Infrastructure/EventListener/HotelAmenityDeclaredListener.php`
- `src/Search/Infrastructure/EventListener/RoomTypeRegisteredListener.php`
- `src/Search/Infrastructure/EventListener/RoomTypeUpdatedListener.php`
- `src/Search/Infrastructure/EventListener/RoomTypeAmenityDeclaredListener.php`
- `src/Search/Infrastructure/EventListener/RoomTypeDeletedListener.php`
- `src/Search/Infrastructure/EventListener/RoomRegisteredListener.php`
- `src/Search/Infrastructure/EventListener/BlockedPeriodCreatedListener.php`
- `src/Search/Infrastructure/EventListener/BlockedPeriodDeletedListener.php`
- `src/Search/Infrastructure/EventListener/AvailabilityHoldCreatedListener.php`
- `src/Search/Infrastructure/EventListener/AvailabilityHoldDeletedListener.php`
- `src/Search/Application/UseCase/SearchAvailableRoomTypes/SearchAvailableRoomTypesQuery.php`
- `src/Search/Application/UseCase/SearchAvailableRoomTypes/SearchAvailableRoomTypesQueryHandler.php`
- `src/Search/UI/Http/Controller/SearchAvailableRoomTypes/SearchAvailableRoomTypesController.php`
- `src/Search/UI/Http/Controller/SearchAvailableRoomTypes/SearchAvailableRoomTypesRequest.php`
- `config/services/search.yaml`
- `tests/Search/Functional/SearchAvailableRoomTypesTest.php`

**Modified files:**
- `src/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommandHandler.php` — dispatch `BlockedPeriodCreated`
- `src/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommandHandler.php` — dispatch `BlockedPeriodDeleted`
- `src/Availability/Application/UseCase/DeleteBlockedPeriodByRoomAndPeriod/DeleteBlockedPeriodByRoomAndPeriodCommandHandler.php` — dispatch `BlockedPeriodDeleted`
- `src/Availability/Application/UseCase/CreateAvailabilityHold/CreateAvailabilityHoldCommandHandler.php` — dispatch `AvailabilityHoldCreated`
- `src/Availability/Application/UseCase/DeleteAvailabilityHold/DeleteAvailabilityHoldCommandHandler.php` — dispatch `AvailabilityHoldDeleted`
- `config/routes.yaml` (or a dedicated `config/routes/search.yaml`) — register `GET /search`

---

## Availability Domain Models (reference)

`BlockedPeriod` has: `id: string`, `roomId: string`, `period: DatePeriod`, `createdAt: DateTimeImmutable`
`DatePeriod` has: `checkIn: DateTimeImmutable`, `checkOut: DateTimeImmutable`
`AvailabilityHold` has: `id: string`, `roomId: string`, `reservationId: string`, `period: DatePeriod`, `expiresAt: DateTimeImmutable`, `createdAt: DateTimeImmutable`

---

### Task 1: Availability event classes

**Files:**
- Create: `src/Shared/Domain/Event/BlockedPeriodCreated.php`
- Create: `src/Shared/Domain/Event/BlockedPeriodDeleted.php`
- Create: `src/Shared/Domain/Event/AvailabilityHoldCreated.php`
- Create: `src/Shared/Domain/Event/AvailabilityHoldDeleted.php`

- [ ] **Step 1: Create `BlockedPeriodCreated`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class BlockedPeriodCreated
{
    public function __construct(
        public string $blockedPeriodId,
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}
```

- [ ] **Step 2: Create `BlockedPeriodDeleted`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class BlockedPeriodDeleted
{
    public function __construct(
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}
```

- [ ] **Step 3: Create `AvailabilityHoldCreated`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class AvailabilityHoldCreated
{
    public function __construct(
        public string $holdId,
        public string $roomId,
        public string $reservationId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
```

- [ ] **Step 4: Create `AvailabilityHoldDeleted`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class AvailabilityHoldDeleted
{
    public function __construct(
        public string $reservationId,
    ) {
    }
}
```

- [ ] **Step 5: Run lint**

```bash
make lint
```

- [ ] **Step 6: Commit**

```bash
git add src/Shared/Domain/Event/BlockedPeriodCreated.php \
        src/Shared/Domain/Event/BlockedPeriodDeleted.php \
        src/Shared/Domain/Event/AvailabilityHoldCreated.php \
        src/Shared/Domain/Event/AvailabilityHoldDeleted.php
git commit -m "feat(search): add Availability domain events (BlockedPeriodCreated/Deleted, AvailabilityHoldCreated/Deleted)"
```

---

### Task 2: BlockPeriodCommandHandler — dispatch BlockedPeriodCreated

**Files:**
- Create: `tests/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommandHandlerTest.php`
- Modify: `src/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommandHandler.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\BlockPeriod;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommandHandler;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Availability\Domain\Port\RoomExistsInterface;
use App\Shared\Domain\Event\BlockedPeriodCreated;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class BlockPeriodCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesBlockedPeriodCreated(): void
    {
        $repository = $this->createMock(BlockedPeriodRepositoryInterface::class);
        $roomExists = $this->createMock(RoomExistsInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $roomExists->method('exists')->willReturn(true);
        $repository->method('hasOverlap')->willReturn(false);

        $checkIn  = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event) use ($checkIn, $checkOut): bool {
                return $event instanceof BlockedPeriodCreated
                    && $event->blockedPeriodId === 'bp-id-1'
                    && $event->roomId === 'room-id-1'
                    && $event->checkIn == $checkIn
                    && $event->checkOut == $checkOut;
            }));

        $handler = new BlockPeriodCommandHandler($repository, $roomExists, $dispatcher);

        ($handler)(new BlockPeriodCommand(
            id: 'bp-id-1',
            roomId: 'room-id-1',
            checkIn: $checkIn,
            checkOut: $checkOut,
            createdAt: new \DateTimeImmutable('2026-05-31T00:00:00Z'),
        ));
    }

    #[Test]
    public function itDoesNotDispatchWhenRoomDoesNotExist(): void
    {
        $repository = $this->createMock(BlockedPeriodRepositoryInterface::class);
        $roomExists = $this->createMock(RoomExistsInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $roomExists->method('exists')->willReturn(false);
        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new BlockPeriodCommandHandler($repository, $roomExists, $dispatcher);

        $this->expectException(\App\Availability\Domain\Exception\RoomNotFoundException::class);

        ($handler)(new BlockPeriodCommand(
            id: 'bp-id-2',
            roomId: 'missing-room',
            checkIn: new \DateTimeImmutable('2026-07-01'),
            checkOut: new \DateTimeImmutable('2026-07-05'),
            createdAt: new \DateTimeImmutable('2026-05-31T00:00:00Z'),
        ));
    }
}
```

- [ ] **Step 2: Run the test — expect FAIL**

```bash
make unit-test
```

- [ ] **Step 3: Modify the handler**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\BlockPeriod;

use App\Availability\Domain\Exception\BlockedPeriodOverlapException;
use App\Availability\Domain\Exception\RoomNotFoundException;
use App\Availability\Domain\Model\BlockedPeriod;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Availability\Domain\Port\RoomExistsInterface;
use App\Availability\Domain\ValueObject\DatePeriod;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\BlockedPeriodCreated;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class BlockPeriodCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private BlockedPeriodRepositoryInterface $repository,
        private RoomExistsInterface $roomExists,
        private EventDispatcherInterface $eventDispatcher,
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

        $this->eventDispatcher->dispatch(new BlockedPeriodCreated(
            blockedPeriodId: $command->id,
            roomId: $command->roomId,
            checkIn: $command->checkIn,
            checkOut: $command->checkOut,
        ));
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
make unit-test
```

- [ ] **Step 5: Commit**

```bash
git add src/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommandHandler.php \
        tests/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommandHandlerTest.php
git commit -m "feat(availability): dispatch BlockedPeriodCreated from BlockPeriodCommandHandler"
```

---

### Task 3: DeleteBlockedPeriod handlers — dispatch BlockedPeriodDeleted

Two handlers produce this event. `DeleteBlockedPeriodCommandHandler` fetches the period before deleting (it already does a `get()` check). `DeleteBlockedPeriodByRoomAndPeriodCommandHandler` has the room+period in the command directly.

**Files:**
- Create: `tests/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommandHandlerTest.php`
- Create: `tests/Availability/Application/UseCase/DeleteBlockedPeriodByRoomAndPeriod/DeleteBlockedPeriodByRoomAndPeriodCommandHandlerTest.php`
- Modify: `src/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommandHandler.php`
- Modify: `src/Availability/Application/UseCase/DeleteBlockedPeriodByRoomAndPeriod/DeleteBlockedPeriodByRoomAndPeriodCommandHandler.php`

- [ ] **Step 1: Write the failing test for `DeleteBlockedPeriodCommandHandler`**

You need to know how `BlockedPeriod` exposes its period. Check `src/Availability/Domain/Model/BlockedPeriod.php` — it has a `period: DatePeriod` property with `checkIn` and `checkOut`.

```php
<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\DeleteBlockedPeriod;

use App\Availability\Application\UseCase\DeleteBlockedPeriod\DeleteBlockedPeriodCommand;
use App\Availability\Application\UseCase\DeleteBlockedPeriod\DeleteBlockedPeriodCommandHandler;
use App\Availability\Domain\Model\BlockedPeriod;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Availability\Domain\ValueObject\DatePeriod;
use App\Shared\Domain\Event\BlockedPeriodDeleted;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class DeleteBlockedPeriodCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesBlockedPeriodDeleted(): void
    {
        $repository = $this->createMock(BlockedPeriodRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $checkIn  = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        $blockedPeriod = new BlockedPeriod(
            id: 'bp-id-1',
            roomId: 'room-id-1',
            period: new DatePeriod($checkIn, $checkOut),
            createdAt: new \DateTimeImmutable('2026-05-31T00:00:00Z'),
        );

        $repository->method('get')->willReturn($blockedPeriod);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event) use ($checkIn, $checkOut): bool {
                return $event instanceof BlockedPeriodDeleted
                    && $event->roomId === 'room-id-1'
                    && $event->checkIn == $checkIn
                    && $event->checkOut == $checkOut;
            }));

        $handler = new DeleteBlockedPeriodCommandHandler($repository, $dispatcher);

        ($handler)(new DeleteBlockedPeriodCommand(id: 'bp-id-1'));
    }

    #[Test]
    public function itDoesNotDispatchWhenNotFound(): void
    {
        $repository = $this->createMock(BlockedPeriodRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('get')->willReturn(null);
        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new DeleteBlockedPeriodCommandHandler($repository, $dispatcher);

        $this->expectException(\App\Availability\Domain\Exception\BlockedPeriodNotFoundException::class);

        ($handler)(new DeleteBlockedPeriodCommand(id: 'missing-id'));
    }
}
```

- [ ] **Step 2: Write the failing test for `DeleteBlockedPeriodByRoomAndPeriodCommandHandler`**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod;

use App\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod\DeleteBlockedPeriodByRoomAndPeriodCommand;
use App\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod\DeleteBlockedPeriodByRoomAndPeriodCommandHandler;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Domain\Event\BlockedPeriodDeleted;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class DeleteBlockedPeriodByRoomAndPeriodCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesBlockedPeriodDeleted(): void
    {
        $repository = $this->createMock(BlockedPeriodRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $checkIn  = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event) use ($checkIn, $checkOut): bool {
                return $event instanceof BlockedPeriodDeleted
                    && $event->roomId === 'room-id-1'
                    && $event->checkIn == $checkIn
                    && $event->checkOut == $checkOut;
            }));

        $handler = new DeleteBlockedPeriodByRoomAndPeriodCommandHandler($repository, $dispatcher);

        ($handler)(new DeleteBlockedPeriodByRoomAndPeriodCommand(
            roomId: 'room-id-1',
            checkIn: $checkIn,
            checkOut: $checkOut,
        ));
    }
}
```

- [ ] **Step 3: Run the tests — expect FAIL**

```bash
make unit-test
```

- [ ] **Step 4: Modify `DeleteBlockedPeriodCommandHandler`**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteBlockedPeriod;

use App\Availability\Domain\Exception\BlockedPeriodNotFoundException;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\BlockedPeriodDeleted;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class DeleteBlockedPeriodCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private BlockedPeriodRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(DeleteBlockedPeriodCommand $command): void
    {
        $blockedPeriod = $this->repository->get($command->id);

        if (null === $blockedPeriod) {
            throw new BlockedPeriodNotFoundException($command->id);
        }

        $this->repository->remove($command->id);

        $this->eventDispatcher->dispatch(new BlockedPeriodDeleted(
            roomId: $blockedPeriod->roomId,
            checkIn: $blockedPeriod->period->checkIn,
            checkOut: $blockedPeriod->period->checkOut,
        ));
    }
}
```

- [ ] **Step 5: Modify `DeleteBlockedPeriodByRoomAndPeriodCommandHandler`**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod;

use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\BlockedPeriodDeleted;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class DeleteBlockedPeriodByRoomAndPeriodCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private readonly BlockedPeriodRepositoryInterface $blockedPeriods,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(DeleteBlockedPeriodByRoomAndPeriodCommand $command): void
    {
        $this->blockedPeriods->removeByRoomAndPeriod($command->roomId, $command->checkIn, $command->checkOut);

        $this->eventDispatcher->dispatch(new BlockedPeriodDeleted(
            roomId: $command->roomId,
            checkIn: $command->checkIn,
            checkOut: $command->checkOut,
        ));
    }
}
```

- [ ] **Step 6: Run tests — expect PASS**

```bash
make unit-test
```

- [ ] **Step 7: Commit**

```bash
git add src/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommandHandler.php \
        src/Availability/Application/UseCase/DeleteBlockedPeriodByRoomAndPeriod/DeleteBlockedPeriodByRoomAndPeriodCommandHandler.php \
        tests/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommandHandlerTest.php \
        tests/Availability/Application/UseCase/DeleteBlockedPeriodByRoomAndPeriod/DeleteBlockedPeriodByRoomAndPeriodCommandHandlerTest.php
git commit -m "feat(availability): dispatch BlockedPeriodDeleted from delete handlers"
```

---

### Task 4: CreateAvailabilityHoldCommandHandler — dispatch AvailabilityHoldCreated

**Files:**
- Create: `tests/Availability/Application/UseCase/CreateAvailabilityHold/CreateAvailabilityHoldCommandHandlerTest.php`
- Modify: `src/Availability/Application/UseCase/CreateAvailabilityHold/CreateAvailabilityHoldCommandHandler.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\CreateAvailabilityHold;

use App\Availability\Application\UseCase\CreateAvailabilityHold\CreateAvailabilityHoldCommand;
use App\Availability\Application\UseCase\CreateAvailabilityHold\CreateAvailabilityHoldCommandHandler;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Shared\Domain\Event\AvailabilityHoldCreated;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class CreateAvailabilityHoldCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesAvailabilityHoldCreated(): void
    {
        $repository = $this->createMock(AvailabilityHoldRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('hasActiveOverlap')->willReturn(false);

        $checkIn   = new \DateTimeImmutable('2026-07-01');
        $checkOut  = new \DateTimeImmutable('2026-07-05');
        $expiresAt = new \DateTimeImmutable('2026-05-31T00:15:00Z');

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event) use ($checkIn, $checkOut, $expiresAt): bool {
                return $event instanceof AvailabilityHoldCreated
                    && $event->holdId === 'hold-id-1'
                    && $event->roomId === 'room-id-1'
                    && $event->reservationId === 'res-id-1'
                    && $event->checkIn == $checkIn
                    && $event->checkOut == $checkOut
                    && $event->expiresAt == $expiresAt;
            }));

        $handler = new CreateAvailabilityHoldCommandHandler($repository, $dispatcher);

        ($handler)(new CreateAvailabilityHoldCommand(
            id: 'hold-id-1',
            roomId: 'room-id-1',
            reservationId: 'res-id-1',
            checkIn: $checkIn,
            checkOut: $checkOut,
            expiresAt: $expiresAt,
            createdAt: new \DateTimeImmutable('2026-05-31T00:00:00Z'),
        ));
    }
}
```

- [ ] **Step 2: Run the test — expect FAIL**

```bash
make unit-test
```

- [ ] **Step 3: Modify the handler**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\CreateAvailabilityHold;

use App\Availability\Domain\Exception\AvailabilityHoldOverlapException;
use App\Availability\Domain\Model\AvailabilityHold;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Availability\Domain\ValueObject\DatePeriod;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\AvailabilityHoldCreated;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class CreateAvailabilityHoldCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private AvailabilityHoldRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(CreateAvailabilityHoldCommand $command): void
    {
        if ($this->repository->hasActiveOverlap($command->roomId, $command->checkIn, $command->checkOut)) {
            throw new AvailabilityHoldOverlapException();
        }

        $this->repository->add(new AvailabilityHold(
            id: $command->id,
            roomId: $command->roomId,
            reservationId: $command->reservationId,
            period: new DatePeriod($command->checkIn, $command->checkOut),
            expiresAt: $command->expiresAt,
            createdAt: $command->createdAt,
        ));

        $this->eventDispatcher->dispatch(new AvailabilityHoldCreated(
            holdId: $command->id,
            roomId: $command->roomId,
            reservationId: $command->reservationId,
            checkIn: $command->checkIn,
            checkOut: $command->checkOut,
            expiresAt: $command->expiresAt,
        ));
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
make unit-test
```

- [ ] **Step 5: Commit**

```bash
git add src/Availability/Application/UseCase/CreateAvailabilityHold/CreateAvailabilityHoldCommandHandler.php \
        tests/Availability/Application/UseCase/CreateAvailabilityHold/CreateAvailabilityHoldCommandHandlerTest.php
git commit -m "feat(availability): dispatch AvailabilityHoldCreated from CreateAvailabilityHoldCommandHandler"
```

---

### Task 5: DeleteAvailabilityHoldCommandHandler — dispatch AvailabilityHoldDeleted

**Files:**
- Create: `tests/Availability/Application/UseCase/DeleteAvailabilityHold/DeleteAvailabilityHoldCommandHandlerTest.php`
- Modify: `src/Availability/Application/UseCase/DeleteAvailabilityHold/DeleteAvailabilityHoldCommandHandler.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\DeleteAvailabilityHold;

use App\Availability\Application\UseCase\DeleteAvailabilityHold\DeleteAvailabilityHoldCommand;
use App\Availability\Application\UseCase\DeleteAvailabilityHold\DeleteAvailabilityHoldCommandHandler;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Shared\Domain\Event\AvailabilityHoldDeleted;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class DeleteAvailabilityHoldCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesAvailabilityHoldDeleted(): void
    {
        $repository = $this->createMock(AvailabilityHoldRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof AvailabilityHoldDeleted
                    && $event->reservationId === 'res-id-1';
            }));

        $handler = new DeleteAvailabilityHoldCommandHandler($repository, $dispatcher);

        ($handler)(new DeleteAvailabilityHoldCommand(reservationId: 'res-id-1'));
    }
}
```

- [ ] **Step 2: Run the test — expect FAIL**

```bash
make unit-test
```

- [ ] **Step 3: Modify the handler**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteAvailabilityHold;

use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\AvailabilityHoldDeleted;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class DeleteAvailabilityHoldCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private AvailabilityHoldRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(DeleteAvailabilityHoldCommand $command): void
    {
        $this->repository->deleteByReservationId($command->reservationId);

        $this->eventDispatcher->dispatch(new AvailabilityHoldDeleted(
            reservationId: $command->reservationId,
        ));
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
make unit-test
```

- [ ] **Step 5: Run full test suite to catch regressions in existing Availability listeners**

```bash
make test
```

Expected: all PASS. The existing `ReservationConfirmedListener` calls `DeleteAvailabilityHoldCommand` — it now also dispatches `AvailabilityHoldDeleted`, which is fine since no listener consumes it yet.

- [ ] **Step 6: Commit**

```bash
git add src/Availability/Application/UseCase/DeleteAvailabilityHold/DeleteAvailabilityHoldCommandHandler.php \
        tests/Availability/Application/UseCase/DeleteAvailabilityHold/DeleteAvailabilityHoldCommandHandlerTest.php
git commit -m "feat(availability): dispatch AvailabilityHoldDeleted from DeleteAvailabilityHoldCommandHandler"
```

---

### Task 6: Search context skeleton

**Files:**
- Create: `config/services/search.yaml`

The `Search` context has no Application or Domain layers to register (only Infrastructure and UI). Deptrac already covers all `App\*\Infrastructure\*` and `App\*\UI\*` by its wildcard layers — no deptrac change needed.

- [ ] **Step 1: Create `config/services/search.yaml`**

```yaml
parameters: {}

services:
    _defaults:
        autowire: true
        autoconfigure: true
    _instanceof:
        App\Shared\Application\Bus\SyncQueryHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: sync.query.bus}

    App\Search\Infrastructure\:
        resource: '../../src/Search/Infrastructure/'

    App\Search\UI\:
        resource: '../../src/Search/UI/'
        exclude:
            - '../../src/Search/UI/**/*Request.php'

    App\Search\Application\:
        resource: '../../src/Search/Application/'
        exclude:
            - '../../src/Search/Application/**/*Query.php'

    bookit.doctrine.middleware.search_path.search:
        class: App\Shared\Infrastructure\Doctrine\SearchPathMiddleware
        arguments:
            $schema: 'search'
        tags:
            - {name: doctrine.middleware, connection: search}
```

- [ ] **Step 2: Create the directory structure**

```bash
mkdir -p src/Search/Infrastructure/EventListener
mkdir -p src/Search/Application/UseCase/SearchAvailableRoomTypes
mkdir -p src/Search/UI/Http/Controller/SearchAvailableRoomTypes
mkdir -p tests/Search/Functional
```

- [ ] **Step 3: Verify the container compiles**

```bash
docker compose exec php bin/console cache:clear
```

Expected: no `FileLocatorFileNotFoundException` (empty `resource:` directories are excluded before they exist — but since we're creating them, this is safe).

- [ ] **Step 4: Commit**

```bash
git add config/services/search.yaml src/Search/ tests/Search/
git commit -m "feat(search): add Search context skeleton and service config"
```

---

### Task 7: Hotel projectors — HotelRegistered, StarRatingClassified, HotelAmenityDeclared

These listeners write to `search.hotel_room_types`. `HotelRegistered` cannot insert a row because the table's PK is `room_type_id` — it only stores the hotel columns for future upserts when `RoomTypeRegistered` arrives. Use an `UPDATE` to patch all rows that belong to this `hotel_id`.

**Design:** `HotelRegistered` has no row to insert yet (no room type). It is a no-op for the projection — the hotel data will be denormalized into `search.hotel_room_types` rows when `RoomTypeRegistered` fires. `StarRatingClassified` and `HotelAmenityDeclared` UPDATE existing rows.

**Files:**
- Create: `src/Search/Infrastructure/EventListener/HotelRegisteredListener.php`
- Create: `src/Search/Infrastructure/EventListener/StarRatingClassifiedListener.php`
- Create: `src/Search/Infrastructure/EventListener/HotelAmenityDeclaredListener.php`

- [ ] **Step 1: Create `HotelRegisteredListener`**

This listener is intentionally a no-op: hotel data lands in the projection only when a Room Type is registered. Keep it explicit so the event is visibly consumed.

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\HotelRegistered;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: HotelRegistered::class)]
final readonly class HotelRegisteredListener
{
    public function __invoke(HotelRegistered $event): void
    {
        // Hotel data is denormalized into search.hotel_room_types rows
        // when RoomTypeRegistered fires. Nothing to do here yet.
    }
}
```

- [ ] **Step 2: Create `StarRatingClassifiedListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\StarRatingClassified;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: StarRatingClassified::class)]
final readonly class StarRatingClassifiedListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(StarRatingClassified $event): void
    {
        $this->connection->executeStatement(
            'UPDATE hotel_room_types SET star_rating = :starRating WHERE hotel_id = :hotelId',
            ['starRating' => $event->starRating, 'hotelId' => $event->hotelId],
        );
    }
}
```

- [ ] **Step 3: Create `HotelAmenityDeclaredListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\HotelAmenityDeclared;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: HotelAmenityDeclared::class)]
final readonly class HotelAmenityDeclaredListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(HotelAmenityDeclared $event): void
    {
        $this->connection->executeStatement(
            'UPDATE hotel_room_types SET hotel_amenities = :amenities WHERE hotel_id = :hotelId',
            [
                'amenities' => json_encode($event->amenities, \JSON_THROW_ON_ERROR),
                'hotelId'   => $event->hotelId,
            ],
        );
    }
}
```

- [ ] **Step 4: Wire the DBAL connection in `config/services/search.yaml`**

Add these explicit bindings after the existing `resource:` blocks:

```yaml
    App\Search\Infrastructure\EventListener\StarRatingClassifiedListener:
        arguments:
            $connection: '@doctrine.dbal.search_connection'

    App\Search\Infrastructure\EventListener\HotelAmenityDeclaredListener:
        arguments:
            $connection: '@doctrine.dbal.search_connection'
```

- [ ] **Step 5: Run lint**

```bash
make lint
```

- [ ] **Step 6: Commit**

```bash
git add src/Search/Infrastructure/EventListener/HotelRegisteredListener.php \
        src/Search/Infrastructure/EventListener/StarRatingClassifiedListener.php \
        src/Search/Infrastructure/EventListener/HotelAmenityDeclaredListener.php \
        config/services/search.yaml
git commit -m "feat(search): add Hotel projectors (HotelRegistered no-op, StarRatingClassified, HotelAmenityDeclared)"
```

---

### Task 8: Room Type projectors — RoomTypeRegistered, RoomTypeUpdated, RoomTypeAmenityDeclared, RoomTypeDeleted

`RoomTypeRegistered` inserts the full denormalized row (hotel name/city/country/star_rating are not known here — use empty defaults; `StarRatingClassified` and `HotelAmenityDeclared` will patch them). Use `INSERT ... ON CONFLICT DO UPDATE` to handle replays.

**Important:** At `RoomTypeRegistered` time we do NOT yet have `hotel_name`, `city`, `country`, `star_rating`, `hotel_amenities` — those come from Hotel events. The simplest approach: keep a separate `search_hotel_snapshot` cache, OR join against the Hotel write model. Because this is a projection in a separate context, the cleanest option is to accept eventual consistency: insert with empty hotel fields and rely on a subsequent `HotelRegistered` replay (or a future re-projection command) to fill them.

However, since `HotelRegistered` fires before any `RoomTypeRegistered` in normal flow, we need to store hotel data somewhere for lookup. The simplest solution: add a `search_hotel_snapshot` table in a future migration, OR inject a cross-context hotel reader. For this iteration, inject the hotel data by reading from the Hotel write DB at projection time (acceptable — projectors may read from other contexts via infrastructure).

**Simpler alternative used here:** inject `doctrine.dbal.hotel_connection` (search_path=hotel) to read the `hotel` table directly when `RoomTypeRegistered` fires, and `doctrine.dbal.search_connection` (search_path=search) for the write. This listener is the only one needing two connections.

**Files:**
- Create: `src/Search/Infrastructure/EventListener/RoomTypeRegisteredListener.php`
- Create: `src/Search/Infrastructure/EventListener/RoomTypeUpdatedListener.php`
- Create: `src/Search/Infrastructure/EventListener/RoomTypeAmenityDeclaredListener.php`
- Create: `src/Search/Infrastructure/EventListener/RoomTypeDeletedListener.php`

- [ ] **Step 1: Create `RoomTypeRegisteredListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\RoomTypeRegistered;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeRegistered::class)]
final readonly class RoomTypeRegisteredListener
{
    public function __construct(
        private Connection $connection,
        private Connection $hotelConnection,
    ) {
    }

    public function __invoke(RoomTypeRegistered $event): void
    {
        $hotel = $this->hotelConnection->fetchAssociative(
            'SELECT name, street_address, postal_code, city, country, star_rating, amenities FROM hotel WHERE id = :id',
            ['id' => $event->hotelId],
        );

        if (false === $hotel) {
            return;
        }

        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO hotel_room_types
                (room_type_id, hotel_id, hotel_name, city, country, star_rating, hotel_amenities,
                 room_type_name, guest_capacity, bed_composition, room_amenities)
            VALUES
                (:roomTypeId, :hotelId, :hotelName, :city, :country, :starRating, :hotelAmenities,
                 :roomTypeName, :guestCapacity, :bedComposition, '[]')
            ON CONFLICT (room_type_id) DO UPDATE SET
                hotel_name      = EXCLUDED.hotel_name,
                city            = EXCLUDED.city,
                country         = EXCLUDED.country,
                star_rating     = EXCLUDED.star_rating,
                hotel_amenities = EXCLUDED.hotel_amenities,
                room_type_name  = EXCLUDED.room_type_name,
                guest_capacity  = EXCLUDED.guest_capacity,
                bed_composition = EXCLUDED.bed_composition
            SQL,
            [
                'roomTypeId'     => $event->roomTypeId,
                'hotelId'        => $event->hotelId,
                'hotelName'      => $hotel['name'],
                'city'           => $hotel['city'],
                'country'        => $hotel['country'],
                'starRating'     => $hotel['star_rating'] ?? null,
                'hotelAmenities' => $hotel['amenities'] ?? '[]',
                'roomTypeName'   => $event->name,
                'guestCapacity'  => $event->guestCapacity,
                'bedComposition' => json_encode($event->bedComposition, \JSON_THROW_ON_ERROR),
            ],
        );
    }
}
```

Note: check the actual column names in the `hotel` table by reading `migrations/Version20260514084245.php` and subsequent migrations before running — adjust `street_address`, `postal_code`, `amenities` to match.

- [ ] **Step 2: Create `RoomTypeUpdatedListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\RoomTypeUpdated;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeUpdated::class)]
final readonly class RoomTypeUpdatedListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(RoomTypeUpdated $event): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE hotel_room_types
            SET room_type_name  = :name,
                guest_capacity  = :guestCapacity,
                bed_composition = :bedComposition
            WHERE room_type_id = :roomTypeId
            SQL,
            [
                'name'           => $event->name,
                'guestCapacity'  => $event->guestCapacity,
                'bedComposition' => json_encode($event->bedComposition, \JSON_THROW_ON_ERROR),
                'roomTypeId'     => $event->roomTypeId,
            ],
        );
    }
}
```

- [ ] **Step 3: Create `RoomTypeAmenityDeclaredListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\RoomTypeAmenityDeclared;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeAmenityDeclared::class)]
final readonly class RoomTypeAmenityDeclaredListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(RoomTypeAmenityDeclared $event): void
    {
        $this->connection->executeStatement(
            'UPDATE hotel_room_types SET room_amenities = :amenities WHERE room_type_id = :roomTypeId',
            [
                'amenities'  => json_encode($event->amenities, \JSON_THROW_ON_ERROR),
                'roomTypeId' => $event->roomTypeId,
            ],
        );
    }
}
```

- [ ] **Step 4: Create `RoomTypeDeletedListener`**

Cascade delete on `search.room_index` and `search.unavailable_periods` is handled by the FK `ON DELETE CASCADE`.

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\RoomTypeDeleted;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeDeleted::class)]
final readonly class RoomTypeDeletedListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(RoomTypeDeleted $event): void
    {
        $this->connection->executeStatement(
            'DELETE FROM hotel_room_types WHERE room_type_id = :roomTypeId',
            ['roomTypeId' => $event->roomTypeId],
        );
    }
}
```

- [ ] **Step 5: Add connection wiring to `config/services/search.yaml`**

```yaml
    App\Search\Infrastructure\EventListener\RoomTypeRegisteredListener:
        arguments:
            $connection: '@doctrine.dbal.search_connection'
            $hotelConnection: '@doctrine.dbal.hotel_connection'

    App\Search\Infrastructure\EventListener\RoomTypeUpdatedListener:
        arguments:
            $connection: '@doctrine.dbal.search_connection'

    App\Search\Infrastructure\EventListener\RoomTypeAmenityDeclaredListener:
        arguments:
            $connection: '@doctrine.dbal.search_connection'

    App\Search\Infrastructure\EventListener\RoomTypeDeletedListener:
        arguments:
            $connection: '@doctrine.dbal.search_connection'
```

- [ ] **Step 6: Run lint**

```bash
make lint
```

- [ ] **Step 7: Commit**

```bash
git add src/Search/Infrastructure/EventListener/RoomTypeRegisteredListener.php \
        src/Search/Infrastructure/EventListener/RoomTypeUpdatedListener.php \
        src/Search/Infrastructure/EventListener/RoomTypeAmenityDeclaredListener.php \
        src/Search/Infrastructure/EventListener/RoomTypeDeletedListener.php \
        config/services/search.yaml
git commit -m "feat(search): add Room Type projectors (registered, updated, amenities, deleted)"
```

---

### Task 9: RoomRegisteredListener — populate search.room_index

**Files:**
- Create: `src/Search/Infrastructure/EventListener/RoomRegisteredListener.php`

- [ ] **Step 1: Create `RoomRegisteredListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\RoomRegistered;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomRegistered::class)]
final readonly class RoomRegisteredListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(RoomRegistered $event): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO room_index (room_id, room_type_id, hotel_id)
            VALUES (:roomId, :roomTypeId, :hotelId)
            ON CONFLICT (room_id) DO UPDATE SET
                room_type_id = EXCLUDED.room_type_id,
                hotel_id     = EXCLUDED.hotel_id
            SQL,
            [
                'roomId'     => $event->roomId,
                'roomTypeId' => $event->roomTypeId,
                'hotelId'    => $event->hotelId,
            ],
        );
    }
}
```

- [ ] **Step 2: Add connection wiring to `config/services/search.yaml`**

```yaml
    App\Search\Infrastructure\EventListener\RoomRegisteredListener:
        arguments:
            $connection: '@doctrine.dbal.search_connection'
```

- [ ] **Step 3: Run lint**

```bash
make lint
```

- [ ] **Step 4: Commit**

```bash
git add src/Search/Infrastructure/EventListener/RoomRegisteredListener.php \
        config/services/search.yaml
git commit -m "feat(search): add RoomRegisteredListener to populate search.room_index"
```

---

### Task 10: Availability projectors — BlockedPeriodCreated, BlockedPeriodDeleted

**Files:**
- Create: `src/Search/Infrastructure/EventListener/BlockedPeriodCreatedListener.php`
- Create: `src/Search/Infrastructure/EventListener/BlockedPeriodDeletedListener.php`

- [ ] **Step 1: Create `BlockedPeriodCreatedListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\BlockedPeriodCreated;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BlockedPeriodCreated::class)]
final readonly class BlockedPeriodCreatedListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(BlockedPeriodCreated $event): void
    {
        $roomRow = $this->connection->fetchAssociative(
            'SELECT room_type_id, hotel_id FROM room_index WHERE room_id = :roomId',
            ['roomId' => $event->roomId],
        );

        if (false === $roomRow) {
            return;
        }

        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO unavailable_periods (id, room_id, room_type_id, hotel_id, period)
            VALUES (:id, :roomId, :roomTypeId, :hotelId, daterange(:checkIn, :checkOut))
            ON CONFLICT (id) DO NOTHING
            SQL,
            [
                'id'         => Uuid::uuid4()->toString(),
                'roomId'     => $event->roomId,
                'roomTypeId' => $roomRow['room_type_id'],
                'hotelId'    => $roomRow['hotel_id'],
                'checkIn'    => $event->checkIn->format('Y-m-d'),
                'checkOut'   => $event->checkOut->format('Y-m-d'),
            ],
        );
    }
}
```

- [ ] **Step 2: Create `BlockedPeriodDeletedListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\BlockedPeriodDeleted;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BlockedPeriodDeleted::class)]
final readonly class BlockedPeriodDeletedListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(BlockedPeriodDeleted $event): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            DELETE FROM unavailable_periods
            WHERE room_id = :roomId
              AND period = daterange(:checkIn, :checkOut)
            SQL,
            [
                'roomId'  => $event->roomId,
                'checkIn' => $event->checkIn->format('Y-m-d'),
                'checkOut'=> $event->checkOut->format('Y-m-d'),
            ],
        );
    }
}
```

- [ ] **Step 3: Add connection wiring to `config/services/search.yaml`**

```yaml
    App\Search\Infrastructure\EventListener\BlockedPeriodCreatedListener:
        arguments:
            $connection: '@doctrine.dbal.search_connection'

    App\Search\Infrastructure\EventListener\BlockedPeriodDeletedListener:
        arguments:
            $connection: '@doctrine.dbal.search_connection'
```

- [ ] **Step 4: Run lint**

```bash
make lint
```

- [ ] **Step 5: Commit**

```bash
git add src/Search/Infrastructure/EventListener/BlockedPeriodCreatedListener.php \
        src/Search/Infrastructure/EventListener/BlockedPeriodDeletedListener.php \
        config/services/search.yaml
git commit -m "feat(search): add BlockedPeriod projectors (search.unavailable_periods)"
```

---

### Task 11: AvailabilityHold projectors — AvailabilityHoldCreated, AvailabilityHoldDeleted

Holds are tracked in `search.unavailable_periods` alongside hard blocks, using the same table. The `id` column stores the `holdId` so deletion by `reservationId` requires a lookup via `search.room_index`. Use a dedicated `search_hold_periods` approach OR track the `reservationId` on the row.

**Decision:** Add a `source_id` column that stores `blockedPeriodId` for hard blocks and `reservationId` for holds. Add via migration.

- [ ] **Step 1: Create migration to add `source_id` column**

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add source_id to search.unavailable_periods to support hold deletion by reservationId';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search.unavailable_periods ADD COLUMN source_id VARCHAR(36) NOT NULL DEFAULT \'\'');
        $this->addSql('CREATE INDEX idx_search_unavailable_periods_source ON search.unavailable_periods (source_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search.unavailable_periods DROP COLUMN source_id');
    }
}
```

- [ ] **Step 2: Run the migration**

```bash
make migrate
```

- [ ] **Step 3: Update `BlockedPeriodCreatedListener` to pass `blockedPeriodId` as `source_id`**

In `BlockedPeriodCreatedListener::__invoke()`, change:
```php
// Change the INSERT to include source_id
'INSERT INTO unavailable_periods (id, room_id, room_type_id, hotel_id, period, source_id)
 VALUES (:id, :roomId, :roomTypeId, :hotelId, daterange(:checkIn, :checkOut), :sourceId)
 ON CONFLICT (id) DO NOTHING'
// add: 'sourceId' => $event->blockedPeriodId,
```

Full updated listener:

```php
public function __invoke(BlockedPeriodCreated $event): void
{
    $roomRow = $this->connection->fetchAssociative(
        'SELECT room_type_id, hotel_id FROM room_index WHERE room_id = :roomId',
        ['roomId' => $event->roomId],
    );

    if (false === $roomRow) {
        return;
    }

    $this->connection->executeStatement(
        <<<'SQL'
        INSERT INTO unavailable_periods (id, room_id, room_type_id, hotel_id, period, source_id)
        VALUES (:id, :roomId, :roomTypeId, :hotelId, daterange(:checkIn, :checkOut), :sourceId)
        ON CONFLICT (id) DO NOTHING
        SQL,
        [
            'id'         => Uuid::uuid4()->toString(),
            'roomId'     => $event->roomId,
            'roomTypeId' => $roomRow['room_type_id'],
            'hotelId'    => $roomRow['hotel_id'],
            'checkIn'    => $event->checkIn->format('Y-m-d'),
            'checkOut'   => $event->checkOut->format('Y-m-d'),
            'sourceId'   => $event->blockedPeriodId,
        ],
    );
}
```

- [ ] **Step 4: Create `AvailabilityHoldCreatedListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\AvailabilityHoldCreated;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: AvailabilityHoldCreated::class)]
final readonly class AvailabilityHoldCreatedListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(AvailabilityHoldCreated $event): void
    {
        $roomRow = $this->connection->fetchAssociative(
            'SELECT room_type_id, hotel_id FROM room_index WHERE room_id = :roomId',
            ['roomId' => $event->roomId],
        );

        if (false === $roomRow) {
            return;
        }

        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO unavailable_periods (id, room_id, room_type_id, hotel_id, period, source_id)
            VALUES (:id, :roomId, :roomTypeId, :hotelId, daterange(:checkIn, :checkOut), :sourceId)
            ON CONFLICT (id) DO NOTHING
            SQL,
            [
                'id'         => Uuid::uuid4()->toString(),
                'roomId'     => $event->roomId,
                'roomTypeId' => $roomRow['room_type_id'],
                'hotelId'    => $roomRow['hotel_id'],
                'checkIn'    => $event->checkIn->format('Y-m-d'),
                'checkOut'   => $event->checkOut->format('Y-m-d'),
                'sourceId'   => $event->reservationId,
            ],
        );
    }
}
```

- [ ] **Step 5: Create `AvailabilityHoldDeletedListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Shared\Domain\Event\AvailabilityHoldDeleted;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: AvailabilityHoldDeleted::class)]
final readonly class AvailabilityHoldDeletedListener
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(AvailabilityHoldDeleted $event): void
    {
        $this->connection->executeStatement(
            'DELETE FROM unavailable_periods WHERE source_id = :reservationId',
            ['reservationId' => $event->reservationId],
        );
    }
}
```

- [ ] **Step 6: Add connection wiring to `config/services/search.yaml`**

```yaml
    App\Search\Infrastructure\EventListener\AvailabilityHoldCreatedListener:
        arguments:
            $connection: '@doctrine.dbal.search_connection'

    App\Search\Infrastructure\EventListener\AvailabilityHoldDeletedListener:
        arguments:
            $connection: '@doctrine.dbal.search_connection'
```

- [ ] **Step 7: Run lint and tests**

```bash
make lint && make test
```

- [ ] **Step 8: Commit**

```bash
git add migrations/Version20260531100001.php \
        src/Search/Infrastructure/EventListener/AvailabilityHoldCreatedListener.php \
        src/Search/Infrastructure/EventListener/AvailabilityHoldDeletedListener.php \
        src/Search/Infrastructure/EventListener/BlockedPeriodCreatedListener.php \
        config/services/search.yaml
git commit -m "feat(search): add AvailabilityHold projectors and source_id migration"
```

---

### Task 12: SearchAvailableRoomTypes query handler

**Files:**
- Create: `src/Search/Application/UseCase/SearchAvailableRoomTypes/SearchAvailableRoomTypesQuery.php`
- Create: `src/Search/Application/UseCase/SearchAvailableRoomTypes/SearchAvailableRoomTypesQueryHandler.php`

- [ ] **Step 1: Create the query**

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\SearchAvailableRoomTypes;

use App\Shared\Application\Bus\SyncQueryInterface;

final readonly class SearchAvailableRoomTypesQuery implements SyncQueryInterface
{
    public function __construct(
        public string $city,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $guests,
    ) {
    }
}
```

- [ ] **Step 2: Create the query handler**

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\SearchAvailableRoomTypes;

use App\Shared\Application\Bus\SyncQueryHandlerInterface;
use Doctrine\DBAL\Connection;

final readonly class SearchAvailableRoomTypesQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(SearchAvailableRoomTypesQuery $query): array
    {
        return $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                s.hotel_id,
                s.hotel_name,
                s.city,
                s.country,
                s.star_rating,
                s.hotel_amenities,
                s.room_type_id,
                s.room_type_name,
                s.guest_capacity,
                s.bed_composition,
                s.room_amenities,
                s.base_price_cents
            FROM hotel_room_types s
            WHERE s.city = :city
              AND s.guest_capacity >= :guests
              AND (
                SELECT COUNT(*)
                FROM room_index r
                WHERE r.room_type_id = s.room_type_id
                  AND NOT EXISTS (
                    SELECT 1
                    FROM unavailable_periods u
                    WHERE u.room_id = r.room_id
                      AND u.period && daterange(:checkIn, :checkOut)
                  )
              ) > 0
            ORDER BY s.hotel_name, s.room_type_name
            SQL,
            [
                'city'     => $query->city,
                'guests'   => $query->guests,
                'checkIn'  => $query->checkIn->format('Y-m-d'),
                'checkOut' => $query->checkOut->format('Y-m-d'),
            ],
        );
    }
}
```

- [ ] **Step 3: Add connection wiring to `config/services/search.yaml`**

```yaml
    App\Search\Application\UseCase\SearchAvailableRoomTypes\SearchAvailableRoomTypesQueryHandler:
        arguments:
            $connection: '@doctrine.dbal.search_connection'
```

- [ ] **Step 4: Run lint**

```bash
make lint
```

- [ ] **Step 5: Commit**

```bash
git add src/Search/Application/UseCase/SearchAvailableRoomTypes/ \
        config/services/search.yaml
git commit -m "feat(search): add SearchAvailableRoomTypes query handler (DBAL)"
```

---

### Task 13: Search controller — GET /search

**Files:**
- Create: `src/Search/UI/Http/Controller/SearchAvailableRoomTypes/SearchAvailableRoomTypesRequest.php`
- Create: `src/Search/UI/Http/Controller/SearchAvailableRoomTypes/SearchAvailableRoomTypesController.php`
- Create: `tests/Search/Functional/SearchAvailableRoomTypesTest.php`

- [ ] **Step 1: Create the request DTO**

```php
<?php

declare(strict_types=1);

namespace App\Search\UI\Http\Controller\SearchAvailableRoomTypes;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SearchAvailableRoomTypesRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $city,

        #[Assert\NotBlank]
        #[Assert\Date]
        public string $checkIn,

        #[Assert\NotBlank]
        #[Assert\Date]
        public string $checkOut,

        #[Assert\NotBlank]
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(20)]
        public int $guests,
    ) {
    }
}
```

- [ ] **Step 2: Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Search\UI\Http\Controller\SearchAvailableRoomTypes;

use App\Search\Application\UseCase\SearchAvailableRoomTypes\SearchAvailableRoomTypesQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/search',
    name: 'search_available_room_types',
    methods: ['GET'],
)]
#[OA\Get(
    summary: 'Search available room types',
    tags: ['Search'],
)]
#[OA\Response(
    response: Response::HTTP_OK,
    description: 'List of available hotel room types matching the criteria',
    content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object')),
)]
#[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error')]
final readonly class SearchAvailableRoomTypesController
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        SearchAvailableRoomTypesRequest $request,
    ): JsonResponse {
        $results = $this->queryBus->execute(new SearchAvailableRoomTypesQuery(
            city: $request->city,
            checkIn: new \DateTimeImmutable($request->checkIn),
            checkOut: new \DateTimeImmutable($request->checkOut),
            guests: $request->guests,
        ));

        return new JsonResponse($results);
    }
}
```

- [ ] **Step 3: Write the functional test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class SearchAvailableRoomTypesTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    #[Test]
    public function itReturns200WithEmptyResultsWhenNothingMatches(): void
    {
        $this->client->request('GET', '/search?city=Nowhere&checkIn=2026-07-01&checkOut=2026-07-05&guests=2');

        self::assertResponseStatusCodeSame(200);
        self::assertJson($this->client->getResponse()->getContent());

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertIsArray($body);
        self::assertSame([], $body);
    }

    #[Test]
    public function itReturns422WhenCityIsMissing(): void
    {
        $this->client->request('GET', '/search?checkIn=2026-07-01&checkOut=2026-07-05&guests=2');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns422WhenGuestsIsZero(): void
    {
        $this->client->request('GET', '/search?city=Paris&checkIn=2026-07-01&checkOut=2026-07-05&guests=0');

        self::assertResponseStatusCodeSame(422);
    }
}
```

- [ ] **Step 4: Run the functional test — expect FAIL**

```bash
make functional-test
```

Expected: 404 (route not wired yet).

- [ ] **Step 5: Run `make openapi` to register the route and regenerate the spec**

```bash
make openapi
```

Verify the route appears in `openapi.yaml` under `GET /search`.

- [ ] **Step 6: Run functional tests — expect PASS**

```bash
make functional-test
```

- [ ] **Step 7: Run full test suite**

```bash
make test
```

- [ ] **Step 8: Run lint**

```bash
make lint
```

- [ ] **Step 9: Commit**

```bash
git add src/Search/UI/Http/Controller/SearchAvailableRoomTypes/ \
        tests/Search/Functional/SearchAvailableRoomTypesTest.php \
        openapi.yaml
git commit -m "feat(search): add GET /search endpoint (SearchAvailableRoomTypes)"
```

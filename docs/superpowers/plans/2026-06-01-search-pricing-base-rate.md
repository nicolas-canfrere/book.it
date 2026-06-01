# Search — Pricing Integration (BaseRateSet) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire `BaseRateSet` from the Pricing context into the Search read model so that `search.hotel_room_types.base_price_cents` reflects the indicative price for a room type.

**Architecture:** `SetBaseRateCommandHandler` dispatches a `BaseRateSet` domain event. A thin `#[AsEventListener]` in `App\Search\Infrastructure\EventListener\` dispatches an async `UpdateSearchBaseRateCommand`. The async handler delegates to `HotelRoomTypeWriterInterface::updateBaseRateByRoom(roomId, amountCents)` which looks up the `room_type_id` via `search.room_index` then updates `search.hotel_room_types.base_price_cents`. Rate periods and promotions are out of scope for this plan — `base_price_cents` stores the unconditional indicative price (last `SetBaseRate` call wins per room type).

**Tech Stack:** PHP 8.4, Symfony 8.0, Symfony Messenger (AMQP), Doctrine DBAL, PHPUnit (unit tests)

**Prerequisite:** Plans 1–3 fully applied (search tables + async projection in place).

---

## File Map

**New files:**
- `src/Shared/Domain/Event/BaseRateSet.php`
- `src/Search/Application/UseCase/UpdateSearchBaseRate/UpdateSearchBaseRateCommand.php`
- `src/Search/Application/UseCase/UpdateSearchBaseRate/UpdateSearchBaseRateCommandHandler.php`
- `src/Search/Infrastructure/EventListener/BaseRateSetListener.php`
- `tests/Pricing/Application/UseCase/SetBaseRate/SetBaseRateCommandHandlerTest.php`
- `tests/Search/Application/UseCase/UpdateSearchBaseRate/UpdateSearchBaseRateCommandHandlerTest.php`

**Modified files:**
- `src/Pricing/Application/UseCase/SetBaseRate/SetBaseRateCommandHandler.php` — inject `EventDispatcherInterface`, dispatch `BaseRateSet`
- `src/Search/Domain/Port/HotelRoomTypeWriterInterface.php` — add `updateBaseRateByRoom(string $roomId, int $amountCents): void`
- `src/Search/Infrastructure/Persistence/HotelRoomTypeWriter.php` — implement `updateBaseRateByRoom`
- `tests/Search/Infrastructure/Persistence/HotelRoomTypeWriterTest.php` — add two tests for the new method

---

### Task 0: Create branch

- [ ] **Step 1: Check current branch and create feature branch if on main**

```bash
git branch --show-current
git checkout -b feat/search-pricing-base-rate
```

---

### Task 1: Domain event `BaseRateSet`

**Files:**
- Create: `src/Shared/Domain/Event/BaseRateSet.php`

- [ ] **Step 1: Create `BaseRateSet`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class BaseRateSet
{
    public function __construct(
        public string $roomId,
        public int $amountCents,
    ) {
    }
}
```

- [ ] **Step 2: Run lint**

```bash
make lint
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/Shared/Domain/Event/BaseRateSet.php
git commit -m "feat(pricing): add BaseRateSet domain event"
```

---

### Task 2: Dispatch `BaseRateSet` from `SetBaseRateCommandHandler`

**Files:**
- Create: `tests/Pricing/Application/UseCase/SetBaseRate/SetBaseRateCommandHandlerTest.php`
- Modify: `src/Pricing/Application/UseCase/SetBaseRate/SetBaseRateCommandHandler.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\SetBaseRate;

use App\Pricing\Application\UseCase\SetBaseRate\SetBaseRateCommand;
use App\Pricing\Application\UseCase\SetBaseRate\SetBaseRateCommandHandler;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;
use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Shared\Domain\Event\BaseRateSet;
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
        $roomExists = $this->createMock(RoomExistsInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $roomExists->method('exists')->willReturn(true);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof BaseRateSet
                    && $event->roomId === 'room-id-1'
                    && $event->amountCents === 15000;
            }));

        $handler = new SetBaseRateCommandHandler($repository, $roomExists, $dispatcher);

        ($handler)(new SetBaseRateCommand(
            roomId: 'room-id-1',
            amountCents: 15000,
            updatedAt: new \DateTimeImmutable('2026-06-01T00:00:00Z'),
        ));
    }

    #[Test]
    public function itDoesNotDispatchWhenRoomNotFound(): void
    {
        $repository = $this->createMock(BaseRateRepositoryInterface::class);
        $roomExists = $this->createMock(RoomExistsInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $roomExists->method('exists')->willReturn(false);
        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new SetBaseRateCommandHandler($repository, $roomExists, $dispatcher);

        $this->expectException(\App\Pricing\Domain\Exception\RoomNotFoundException::class);

        ($handler)(new SetBaseRateCommand(
            roomId: 'missing-room',
            amountCents: 15000,
            updatedAt: new \DateTimeImmutable('2026-06-01T00:00:00Z'),
        ));
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
make unit-test
```

Expected: FAIL — `SetBaseRateCommandHandler::__construct()` doesn't accept `EventDispatcherInterface`.

- [ ] **Step 3: Modify the handler**

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\SetBaseRate;

use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Pricing\Domain\Model\BaseRate;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;
use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\BaseRateSet;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class SetBaseRateCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private BaseRateRepositoryInterface $repository,
        private RoomExistsInterface $roomExists,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(SetBaseRateCommand $command): void
    {
        if (!$this->roomExists->exists($command->roomId)) {
            throw new RoomNotFoundException($command->roomId);
        }

        $this->repository->save(new BaseRate(
            roomId: $command->roomId,
            amountCents: $command->amountCents,
            updatedAt: $command->updatedAt,
        ));

        $this->eventDispatcher->dispatch(new BaseRateSet(
            roomId: $command->roomId,
            amountCents: $command->amountCents,
        ));
    }
}
```

- [ ] **Step 4: Run test — expect PASS**

```bash
make unit-test
```

Expected: all PASS.

- [ ] **Step 5: Run lint**

```bash
make lint
```

Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add src/Pricing/Application/UseCase/SetBaseRate/SetBaseRateCommandHandler.php \
        tests/Pricing/Application/UseCase/SetBaseRate/SetBaseRateCommandHandlerTest.php
git commit -m "feat(pricing): dispatch BaseRateSet from SetBaseRateCommandHandler"
```

---

### Task 3: Extend `HotelRoomTypeWriterInterface` and `HotelRoomTypeWriter`

**Files:**
- Modify: `src/Search/Domain/Port/HotelRoomTypeWriterInterface.php`
- Modify: `src/Search/Infrastructure/Persistence/HotelRoomTypeWriter.php`
- Modify: `tests/Search/Infrastructure/Persistence/HotelRoomTypeWriterTest.php` (add two new test methods)

- [ ] **Step 1: Add `updateBaseRateByRoom` to the interface**

Open `src/Search/Domain/Port/HotelRoomTypeWriterInterface.php` and add the method signature at the end:

```php
    public function updateBaseRateByRoom(string $roomId, int $amountCents): void;
```

Full resulting interface:

```php
<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

interface HotelRoomTypeWriterInterface
{
    public function updateStarRating(string $hotelId, ?int $starRating): void;

    /** @param string[] $amenities */
    public function updateHotelAmenities(string $hotelId, array $amenities): void;

    /** @param list<array{type: string, count: int}> $bedComposition */
    public function upsertRoomType(
        string $roomTypeId,
        string $hotelId,
        string $name,
        int $guestCapacity,
        array $bedComposition,
    ): void;

    /** @param list<array{type: string, count: int}> $bedComposition */
    public function updateRoomType(
        string $roomTypeId,
        string $name,
        int $guestCapacity,
        array $bedComposition,
    ): void;

    /** @param string[] $amenities */
    public function updateRoomAmenities(string $roomTypeId, array $amenities): void;

    public function deleteRoomType(string $roomTypeId): void;

    public function updateBaseRateByRoom(string $roomId, int $amountCents): void;
}
```

- [ ] **Step 2: Write failing tests in `HotelRoomTypeWriterTest.php`**

Add these two test methods at the end of the class (before the closing `}`):

```php
    #[Test]
    public function itUpdatesBaseRateByRoomLookingUpRoomIndex(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                'SELECT room_type_id FROM room_index WHERE room_id = :roomId',
                ['roomId' => 'room-id-1'],
            )
            ->willReturn(['room_type_id' => 'rt-id-1']);

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE hotel_room_types SET base_price_cents = :amountCents WHERE room_type_id = :roomTypeId',
                ['amountCents' => 15000, 'roomTypeId' => 'rt-id-1'],
            );

        (new HotelRoomTypeWriter($connection, $this->createMock(Connection::class)))
            ->updateBaseRateByRoom('room-id-1', 15000);
    }

    #[Test]
    public function itSkipsBaseRateUpdateWhenRoomNotIndexed(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        $connection->expects($this->never())->method('executeStatement');

        (new HotelRoomTypeWriter($connection, $this->createMock(Connection::class)))
            ->updateBaseRateByRoom('unknown-room', 15000);
    }
```

- [ ] **Step 3: Run tests — expect FAIL**

```bash
make unit-test
```

Expected: FAIL — `HotelRoomTypeWriter` doesn't implement `updateBaseRateByRoom`.

- [ ] **Step 4: Implement `updateBaseRateByRoom` in `HotelRoomTypeWriter`**

Add this method before the `parsePostgresAmenities` private method:

```php
    public function updateBaseRateByRoom(string $roomId, int $amountCents): void
    {
        $roomRow = $this->searchConnection->fetchAssociative(
            'SELECT room_type_id FROM room_index WHERE room_id = :roomId',
            ['roomId' => $roomId],
        );

        if (false === $roomRow) {
            return;
        }

        $this->searchConnection->executeStatement(
            'UPDATE hotel_room_types SET base_price_cents = :amountCents WHERE room_type_id = :roomTypeId',
            ['amountCents' => $amountCents, 'roomTypeId' => $roomRow['room_type_id']],
        );
    }
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
make unit-test
```

Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Search/Domain/Port/HotelRoomTypeWriterInterface.php \
        src/Search/Infrastructure/Persistence/HotelRoomTypeWriter.php \
        tests/Search/Infrastructure/Persistence/HotelRoomTypeWriterTest.php
git commit -m "feat(search): add updateBaseRateByRoom to HotelRoomTypeWriter (via room_index lookup)"
```

---

### Task 4: Search async command and handler

**Files:**
- Create: `src/Search/Application/UseCase/UpdateSearchBaseRate/UpdateSearchBaseRateCommand.php`
- Create: `src/Search/Application/UseCase/UpdateSearchBaseRate/UpdateSearchBaseRateCommandHandler.php`
- Create: `tests/Search/Application/UseCase/UpdateSearchBaseRate/UpdateSearchBaseRateCommandHandlerTest.php`

- [ ] **Step 1: Create command and write failing test**

`src/Search/Application/UseCase/UpdateSearchBaseRate/UpdateSearchBaseRateCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchBaseRate;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class UpdateSearchBaseRateCommand implements AsyncCommandInterface
{
    public function __construct(
        public string $roomId,
        public int $amountCents,
    ) {
    }
}
```

`tests/Search/Application/UseCase/UpdateSearchBaseRate/UpdateSearchBaseRateCommandHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\UpdateSearchBaseRate;

use App\Search\Application\UseCase\UpdateSearchBaseRate\UpdateSearchBaseRateCommand;
use App\Search\Application\UseCase\UpdateSearchBaseRate\UpdateSearchBaseRateCommandHandler;
use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UpdateSearchBaseRateCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesBaseRateUpdateToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('updateBaseRateByRoom')
            ->with('room-id-1', 15000);

        $handler = new UpdateSearchBaseRateCommandHandler($writer);
        ($handler)(new UpdateSearchBaseRateCommand(roomId: 'room-id-1', amountCents: 15000));
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
make unit-test
```

Expected: FAIL — `UpdateSearchBaseRateCommandHandler` does not exist.

- [ ] **Step 3: Create the handler**

`src/Search/Application/UseCase/UpdateSearchBaseRate/UpdateSearchBaseRateCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchBaseRate;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class UpdateSearchBaseRateCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private HotelRoomTypeWriterInterface $writer)
    {
    }

    public function __invoke(UpdateSearchBaseRateCommand $command): void
    {
        $this->writer->updateBaseRateByRoom($command->roomId, $command->amountCents);
    }
}
```

- [ ] **Step 4: Run test — expect PASS**

```bash
make unit-test
```

Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Search/Application/UseCase/UpdateSearchBaseRate/ \
        tests/Search/Application/UseCase/UpdateSearchBaseRate/
git commit -m "feat(search): add UpdateSearchBaseRate async command handler"
```

---

### Task 5: `BaseRateSetListener` — thin dispatcher

**Files:**
- Create: `src/Search/Infrastructure/EventListener/BaseRateSetListener.php`

No `config/services/search.yaml` changes needed: `App\Search\Infrastructure\:` auto-registers the listener, `AsyncCommandDispatcherInterface` is autowired, and the `AsyncCommandHandlerInterface` `_instanceof` block tags the handler for `messenger.bus.default`.

- [ ] **Step 1: Create `BaseRateSetListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\UpdateSearchBaseRate\UpdateSearchBaseRateCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\BaseRateSet;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BaseRateSet::class)]
final readonly class BaseRateSetListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(BaseRateSet $event): void
    {
        $this->commandDispatcher->dispatch(new UpdateSearchBaseRateCommand(
            roomId: $event->roomId,
            amountCents: $event->amountCents,
        ));
    }
}
```

- [ ] **Step 2: Run lint**

```bash
make lint
```

Expected: no errors. If deptrac complains, check that no `App\Pricing\` imports appear in `App\Search\`.

- [ ] **Step 3: Run full test suite**

```bash
make test
```

Expected: all PASS. The functional tests use `in-memory://` transport so no AMQP connection is needed.

- [ ] **Step 4: Commit**

```bash
git add src/Search/Infrastructure/EventListener/BaseRateSetListener.php
git commit -m "feat(search): add BaseRateSetListener — dispatch UpdateSearchBaseRate async command"
```

---

### Task 6: Open PR

- [ ] **Step 1: Push branch and open PR**

```bash
git push -u origin feat/search-pricing-base-rate
gh pr create \
  --title "feat(search): wire BaseRateSet → search.hotel_room_types.base_price_cents" \
  --body "$(cat <<'EOF'
## Summary

- Adds `BaseRateSet` shared domain event dispatched by `SetBaseRateCommandHandler`
- Adds `HotelRoomTypeWriterInterface::updateBaseRateByRoom` (looks up room type via `search.room_index`, then updates `base_price_cents`)
- Adds `UpdateSearchBaseRate` async command + handler in the Search application layer
- Adds `BaseRateSetListener` thin dispatcher in the Search infrastructure layer

Rate periods and promotions are out of scope: `base_price_cents` stores the unconditional base rate (indicative price). The last `SetBaseRate` call for any room of a given type determines the displayed price.

## Test plan

- [ ] `make unit-test` — all new handler and writer tests pass
- [ ] `make lint` — no CS Fixer, PHPStan, or deptrac errors
- [ ] `make test` — full suite green

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

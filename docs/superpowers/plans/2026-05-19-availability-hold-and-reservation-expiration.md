# Availability Hold & Reservation Expiration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prevent double-booking by introducing a 15-minute `AvailabilityHold` created atomically with each `Reservation`, and automatically expiring pending reservations whose hold lapses.

**Architecture:** When a `Reservation` is created (pending), a `ReservationCreated` event (sync) triggers an `AvailabilityHold` in PostgreSQL that blocks the room for 15 minutes. Simultaneously, a delayed `ExpireReservationCommand` is dispatched via RabbitMQ. If the reservation is not confirmed before the hold expires, the delayed command fires, transitions the reservation to `expired`, and a `ReservationExpired` event causes Availability to delete the hold. The entire create-reservation flow is wrapped in a single DB transaction via a `TransactionManagerInterface` port — if the hold creation fails (concurrent overlap), the reservation is rolled back.

**Tech Stack:** PHP 8.4 / Symfony 8.0 / PostgreSQL 16 / RabbitMQ 4 via Symfony Messenger / Doctrine DBAL

---

## File Map

**Shared — new files:**
- `src/Shared/Application/Bus/AsyncCommandDispatcherInterface.php` — port for async dispatch with optional delay
- `src/Shared/Application/Transaction/TransactionManagerInterface.php` — port to wrap DB transactions
- `src/Shared/Infrastructure/Bus/MessengerAsyncCommandDispatcher.php` — Messenger impl of async dispatcher
- `src/Shared/Infrastructure/Transaction/DoctrineTransactionManager.php` — DBAL impl of transaction manager

**Availability — new files:**
- `src/Availability/Domain/Model/AvailabilityHold.php` — entity: id, roomId, reservationId, period, expiresAt, createdAt
- `src/Availability/Domain/Port/AvailabilityHoldRepositoryInterface.php` — add / deleteByReservationId / hasActiveOverlap
- `src/Availability/Domain/Port/AvailabilityHoldIdGeneratorInterface.php` — UUID generation port
- `src/Availability/Domain/Exception/AvailabilityHoldOverlapException.php` — thrown on concurrent overlap
- `src/Availability/Application/UseCase/CreateAvailabilityHold/CreateAvailabilityHoldCommand.php`
- `src/Availability/Application/UseCase/CreateAvailabilityHold/CreateAvailabilityHoldCommandHandler.php`
- `src/Availability/Application/UseCase/DeleteAvailabilityHold/DeleteAvailabilityHoldCommand.php`
- `src/Availability/Application/UseCase/DeleteAvailabilityHold/DeleteAvailabilityHoldCommandHandler.php`
- `src/Availability/Infrastructure/Persistence/Doctrine/AvailabilityHoldRepository.php`
- `src/Availability/Infrastructure/Service/AvailabilityHoldIdGenerator.php`
- `src/Availability/Infrastructure/EventListener/ReservationCreatedListener.php`
- `src/Availability/Infrastructure/EventListener/ReservationExpiredListener.php`

**Availability — modified files:**
- `src/Availability/Application/UseCase/CheckAvailability/CheckAvailabilityQueryHandler.php` — also checks active holds

**Reservation — new files:**
- `src/Reservation/Domain/Event/ReservationExpired.php`
- `src/Reservation/Application/UseCase/ExpireReservation/ExpireReservationCommand.php`
- `src/Reservation/Application/UseCase/ExpireReservation/ExpireReservationCommandHandler.php`

**Reservation — modified files:**
- `src/Reservation/Domain/Model/ReservationStatus.php` — add `Expired` case
- `src/Reservation/Domain/Model/Reservation.php` — add `expire()` method
- `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php` — wrap in transaction, dispatch delayed expiry

**Config — modified files:**
- `config/services/shared.yaml` — wire TransactionManager and AsyncCommandDispatcher with named connection
- `config/services/availability.yaml` — exclude Event/ dirs, add listener tags
- `config/packages/messenger.yaml` — no change needed (`delays` exchange already declared in RabbitMQ definitions)
- `config/services/exceptions.yaml` — map AvailabilityHoldOverlapException → 409

**Migration:**
- `migrations/VersionYYYYMMDDHHMMSS.php` — create `availability_hold` table

---

## Task 1: Feature branch

- [ ] **Create branch**
```bash
git checkout -b feat/availability-hold-and-expiration
```

---

## Task 2: TransactionManagerInterface + DoctrineTransactionManager

**Files:**
- Create: `src/Shared/Application/Transaction/TransactionManagerInterface.php`
- Create: `src/Shared/Infrastructure/Transaction/DoctrineTransactionManager.php`

- [ ] **Create the port**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Application\Transaction;

interface TransactionManagerInterface
{
    public function transactional(callable $callback): void;
}
```

- [ ] **Create the infrastructure implementation**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Transaction;

use App\Shared\Application\Transaction\TransactionManagerInterface;
use Doctrine\DBAL\Connection;

final readonly class DoctrineTransactionManager implements TransactionManagerInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function transactional(callable $callback): void
    {
        $this->bookit->transactional($callback);
    }
}
```

- [ ] **Wire in `config/services/shared.yaml`**

Add below the existing `App\Shared\:` block:

```yaml
    App\Shared\Infrastructure\Transaction\DoctrineTransactionManager:
        arguments:
            $bookit: '@doctrine.dbal.bookit_connection'
```

- [ ] **Run the container to verify wiring compiles**
```bash
make lint
```
Expected: no errors.

- [ ] **Commit**
```bash
git add src/Shared/Application/Transaction/ src/Shared/Infrastructure/Transaction/ config/services/shared.yaml
git commit -m "feat(shared): add TransactionManagerInterface port and DoctrineTransactionManager"
```

---

## Task 3: AsyncCommandDispatcherInterface + MessengerAsyncCommandDispatcher

**Files:**
- Create: `src/Shared/Application/Bus/AsyncCommandDispatcherInterface.php`
- Create: `src/Shared/Infrastructure/Bus/MessengerAsyncCommandDispatcher.php`

- [ ] **Create the port**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

interface AsyncCommandDispatcherInterface
{
    public function dispatch(AsyncCommandInterface $command, int $delayMs = 0): void;
}
```

- [ ] **Create the infrastructure implementation**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Application\Bus\AsyncCommandInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final readonly class MessengerAsyncCommandDispatcher implements AsyncCommandDispatcherInterface
{
    public function __construct(private MessageBusInterface $defaultBus)
    {
    }

    public function dispatch(AsyncCommandInterface $command, int $delayMs = 0): void
    {
        $stamps = $delayMs > 0 ? [new DelayStamp($delayMs)] : [];
        $this->defaultBus->dispatch($command, $stamps);
    }
}
```

- [ ] **Verify `config/packages/messenger.yaml` — no change needed**

`auto_setup: false` stays as-is. The delay infrastructure is already declared in `.docker/rabbitmq/definitions.json`: a `delays` exchange of type `direct` is pre-configured. Symfony Messenger's AMQP transport will use it automatically when a `DelayStamp` is applied. No `messenger.yaml` change required.

- [ ] **Verify DI compiles**
```bash
make lint
```

- [ ] **Commit**
```bash
git add src/Shared/Application/Bus/AsyncCommandDispatcherInterface.php src/Shared/Infrastructure/Bus/MessengerAsyncCommandDispatcher.php config/packages/messenger.yaml
git commit -m "feat(shared): add AsyncCommandDispatcherInterface port and MessengerAsyncCommandDispatcher"
```

---

## Task 4: AvailabilityHold domain model + ports

**Files:**
- Create: `src/Availability/Domain/Model/AvailabilityHold.php`
- Create: `src/Availability/Domain/Port/AvailabilityHoldRepositoryInterface.php`
- Create: `src/Availability/Domain/Port/AvailabilityHoldIdGeneratorInterface.php`
- Create: `src/Availability/Domain/Exception/AvailabilityHoldOverlapException.php`

- [ ] **Create the entity**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Domain\Model;

use App\Availability\Domain\ValueObject\DatePeriod;

final readonly class AvailabilityHold
{
    public function __construct(
        public string $id,
        public string $roomId,
        public string $reservationId,
        public DatePeriod $period,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Create the repository port**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Domain\Port;

use App\Availability\Domain\Model\AvailabilityHold;

interface AvailabilityHoldRepositoryInterface
{
    public function add(AvailabilityHold $hold): void;

    public function deleteByReservationId(string $reservationId): void;

    public function hasActiveOverlap(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool;
}
```

- [ ] **Create the ID generator port**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Domain\Port;

interface AvailabilityHoldIdGeneratorInterface
{
    public function generate(): string;
}
```

- [ ] **Create the domain exception**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Domain\Exception;

final class AvailabilityHoldOverlapException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('An active availability hold already exists for this room and period.');
    }
}
```

- [ ] **Commit**
```bash
git add src/Availability/Domain/
git commit -m "feat(availability): add AvailabilityHold domain model, ports, and exception"
```

---

## Task 5: Database migration + AvailabilityHoldRepository + AvailabilityHoldIdGenerator

**Files:**
- Create: `src/Availability/Infrastructure/Persistence/Doctrine/AvailabilityHoldRepository.php`
- Create: `src/Availability/Infrastructure/Service/AvailabilityHoldIdGenerator.php`
- Create: migration via `make migration`

- [ ] **Write the failing integration test**

Create `tests/Availability/Infrastructure/Persistence/Doctrine/AvailabilityHoldRepositoryTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Availability\Infrastructure\Persistence\Doctrine;

use App\Availability\Domain\Model\AvailabilityHold;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Availability\Domain\ValueObject\DatePeriod;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class AvailabilityHoldRepositoryTest extends KernelTestCase
{
    private AvailabilityHoldRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(AvailabilityHoldRepositoryInterface::class);
    }

    public function test_add_and_has_active_overlap(): void
    {
        $roomId = 'room-1';
        $checkIn = new \DateTimeImmutable('2030-06-01');
        $checkOut = new \DateTimeImmutable('2030-06-05');
        $expiresAt = new \DateTimeImmutable('+15 minutes');

        $hold = new AvailabilityHold(
            id: 'hold-1',
            roomId: $roomId,
            reservationId: 'res-1',
            period: new DatePeriod($checkIn, $checkOut),
            expiresAt: $expiresAt,
            createdAt: new \DateTimeImmutable(),
        );

        $this->repository->add($hold);

        self::assertTrue($this->repository->hasActiveOverlap($roomId, $checkIn, $checkOut));
    }

    public function test_expired_hold_does_not_count_as_overlap(): void
    {
        $roomId = 'room-2';
        $checkIn = new \DateTimeImmutable('2030-07-01');
        $checkOut = new \DateTimeImmutable('2030-07-05');

        $hold = new AvailabilityHold(
            id: 'hold-2',
            roomId: $roomId,
            reservationId: 'res-2',
            period: new DatePeriod($checkIn, $checkOut),
            expiresAt: new \DateTimeImmutable('-1 second'),
            createdAt: new \DateTimeImmutable('-20 minutes'),
        );

        $this->repository->add($hold);

        self::assertFalse($this->repository->hasActiveOverlap($roomId, $checkIn, $checkOut));
    }

    public function test_delete_by_reservation_id_removes_hold(): void
    {
        $roomId = 'room-3';
        $checkIn = new \DateTimeImmutable('2030-08-01');
        $checkOut = new \DateTimeImmutable('2030-08-05');

        $hold = new AvailabilityHold(
            id: 'hold-3',
            roomId: $roomId,
            reservationId: 'res-3',
            period: new DatePeriod($checkIn, $checkOut),
            expiresAt: new \DateTimeImmutable('+15 minutes'),
            createdAt: new \DateTimeImmutable(),
        );

        $this->repository->add($hold);
        $this->repository->deleteByReservationId('res-3');

        self::assertFalse($this->repository->hasActiveOverlap($roomId, $checkIn, $checkOut));
    }
}
```

- [ ] **Run the test to confirm it fails (class not found)**
```bash
make test-integration
```
Expected: error about missing class.

- [ ] **Create the repository implementation**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\Persistence\Doctrine;

use App\Availability\Domain\Model\AvailabilityHold;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Availability\Domain\ValueObject\DatePeriod;
use Doctrine\DBAL\Connection;

final readonly class AvailabilityHoldRepository implements AvailabilityHoldRepositoryInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function add(AvailabilityHold $hold): void
    {
        $this->bookit->insert('availability_hold', [
            'id' => $hold->id,
            'room_id' => $hold->roomId,
            'reservation_id' => $hold->reservationId,
            'check_in' => $hold->period->checkIn->format('Y-m-d'),
            'check_out' => $hold->period->checkOut->format('Y-m-d'),
            'expires_at' => $hold->expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $hold->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function deleteByReservationId(string $reservationId): void
    {
        $this->bookit->delete('availability_hold', ['reservation_id' => $reservationId]);
    }

    public function hasActiveOverlap(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM availability_hold
             WHERE room_id = :roomId
               AND check_in < :checkOut
               AND check_out > :checkIn
               AND expires_at > :now',
            [
                'roomId' => $roomId,
                'checkIn' => $checkIn->format('Y-m-d'),
                'checkOut' => $checkOut->format('Y-m-d'),
                'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
        );

        return $count > 0;
    }
}
```

- [ ] **Create the ID generator**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\Service;

use App\Availability\Domain\Port\AvailabilityHoldIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class AvailabilityHoldIdGenerator implements AvailabilityHoldIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}
```

- [ ] **Wire the named connection in `config/services/availability.yaml`**

Add below the existing Infrastructure block:

```yaml
    App\Availability\Infrastructure\Persistence\Doctrine\AvailabilityHoldRepository:
        arguments:
            $bookit: '@doctrine.dbal.bookit_connection'
```

- [ ] **Generate and run the migration**
```bash
make migration
```

Edit the generated migration to add the `availability_hold` table. Open the generated file in `migrations/` and replace the `up()` body:

```php
public function up(Schema $schema): void
{
    $this->addSql(<<<'SQL'
        CREATE TABLE availability_hold (
            id VARCHAR(36) NOT NULL,
            room_id VARCHAR(36) NOT NULL,
            reservation_id VARCHAR(36) NOT NULL,
            check_in DATE NOT NULL,
            check_out DATE NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )
    SQL);
    $this->addSql('CREATE INDEX idx_availability_hold_room_dates ON availability_hold (room_id, check_in, check_out, expires_at)');
    $this->addSql('CREATE UNIQUE INDEX uniq_availability_hold_reservation ON availability_hold (reservation_id)');
}

public function down(Schema $schema): void
{
    $this->addSql('DROP TABLE availability_hold');
}
```

```bash
make migrate
```

- [ ] **Run the integration tests**
```bash
make test-integration
```
Expected: all three repository tests pass.

- [ ] **Commit**
```bash
git add src/Availability/Infrastructure/ config/services/availability.yaml migrations/
git commit -m "feat(availability): add AvailabilityHoldRepository, AvailabilityHoldIdGenerator, and migration"
```

---

## Task 6: CreateAvailabilityHold use case

**Files:**
- Create: `src/Availability/Application/UseCase/CreateAvailabilityHold/CreateAvailabilityHoldCommand.php`
- Create: `src/Availability/Application/UseCase/CreateAvailabilityHold/CreateAvailabilityHoldCommandHandler.php`
- Test: `tests/Availability/Application/UseCase/CreateAvailabilityHold/CreateAvailabilityHoldCommandHandlerTest.php`

- [ ] **Write the failing unit test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\CreateAvailabilityHold;

use App\Availability\Application\UseCase\CreateAvailabilityHold\CreateAvailabilityHoldCommand;
use App\Availability\Application\UseCase\CreateAvailabilityHold\CreateAvailabilityHoldCommandHandler;
use App\Availability\Domain\Exception\AvailabilityHoldOverlapException;
use App\Availability\Domain\Model\AvailabilityHold;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CreateAvailabilityHoldCommandHandlerTest extends TestCase
{
    private MockObject&AvailabilityHoldRepositoryInterface $repository;
    private CreateAvailabilityHoldCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AvailabilityHoldRepositoryInterface::class);
        $this->handler = new CreateAvailabilityHoldCommandHandler($this->repository);
    }

    public function test_creates_hold_when_no_active_overlap(): void
    {
        $this->repository->method('hasActiveOverlap')->willReturn(false);
        $this->repository->expects(self::once())->method('add')
            ->with(self::isInstanceOf(AvailabilityHold::class));

        ($this->handler)(new CreateAvailabilityHoldCommand(
            id: 'hold-uuid',
            roomId: 'room-uuid',
            reservationId: 'res-uuid',
            checkIn: new \DateTimeImmutable('2030-06-01'),
            checkOut: new \DateTimeImmutable('2030-06-05'),
            expiresAt: new \DateTimeImmutable('+15 minutes'),
            createdAt: new \DateTimeImmutable(),
        ));
    }

    public function test_throws_when_active_overlap_exists(): void
    {
        $this->repository->method('hasActiveOverlap')->willReturn(true);
        $this->repository->expects(self::never())->method('add');

        $this->expectException(AvailabilityHoldOverlapException::class);

        ($this->handler)(new CreateAvailabilityHoldCommand(
            id: 'hold-uuid',
            roomId: 'room-uuid',
            reservationId: 'res-uuid',
            checkIn: new \DateTimeImmutable('2030-06-01'),
            checkOut: new \DateTimeImmutable('2030-06-05'),
            expiresAt: new \DateTimeImmutable('+15 minutes'),
            createdAt: new \DateTimeImmutable(),
        ));
    }
}
```

- [ ] **Run the test to confirm it fails**
```bash
make test-unit
```

- [ ] **Create the command**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\CreateAvailabilityHold;

final readonly class CreateAvailabilityHoldCommand
{
    public function __construct(
        public string $id,
        public string $roomId,
        public string $reservationId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Create the handler**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\CreateAvailabilityHold;

use App\Availability\Domain\Exception\AvailabilityHoldOverlapException;
use App\Availability\Domain\Model\AvailabilityHold;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Availability\Domain\ValueObject\DatePeriod;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class CreateAvailabilityHoldCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private AvailabilityHoldRepositoryInterface $repository)
    {
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
    }
}
```

- [ ] **Run tests**
```bash
make test-unit
```
Expected: both tests pass.

- [ ] **Map the exception to HTTP 409 in `config/services/exceptions.yaml`**

```yaml
            App\Availability\Domain\Exception\AvailabilityHoldOverlapException:
                type: 'https://book.it/problems/room-not-available'
                title: 'Room Not Available'
                status: 409
```

- [ ] **Commit**
```bash
git add src/Availability/Application/UseCase/CreateAvailabilityHold/ tests/Availability/Application/UseCase/CreateAvailabilityHold/ config/services/exceptions.yaml
git commit -m "feat(availability): add CreateAvailabilityHold use case"
```

---

## Task 7: DeleteAvailabilityHold use case

**Files:**
- Create: `src/Availability/Application/UseCase/DeleteAvailabilityHold/DeleteAvailabilityHoldCommand.php`
- Create: `src/Availability/Application/UseCase/DeleteAvailabilityHold/DeleteAvailabilityHoldCommandHandler.php`
- Test: `tests/Availability/Application/UseCase/DeleteAvailabilityHold/DeleteAvailabilityHoldCommandHandlerTest.php`

- [ ] **Write the failing unit test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\DeleteAvailabilityHold;

use App\Availability\Application\UseCase\DeleteAvailabilityHold\DeleteAvailabilityHoldCommand;
use App\Availability\Application\UseCase\DeleteAvailabilityHold\DeleteAvailabilityHoldCommandHandler;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeleteAvailabilityHoldCommandHandlerTest extends TestCase
{
    private MockObject&AvailabilityHoldRepositoryInterface $repository;
    private DeleteAvailabilityHoldCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AvailabilityHoldRepositoryInterface::class);
        $this->handler = new DeleteAvailabilityHoldCommandHandler($this->repository);
    }

    public function test_deletes_hold_by_reservation_id(): void
    {
        $this->repository->expects(self::once())
            ->method('deleteByReservationId')
            ->with('res-uuid');

        ($this->handler)(new DeleteAvailabilityHoldCommand(reservationId: 'res-uuid'));
    }
}
```

- [ ] **Run the test to confirm it fails**
```bash
make test-unit
```

- [ ] **Create the command**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteAvailabilityHold;

final readonly class DeleteAvailabilityHoldCommand
{
    public function __construct(public string $reservationId)
    {
    }
}
```

- [ ] **Create the handler**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteAvailabilityHold;

use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class DeleteAvailabilityHoldCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private AvailabilityHoldRepositoryInterface $repository)
    {
    }

    public function __invoke(DeleteAvailabilityHoldCommand $command): void
    {
        $this->repository->deleteByReservationId($command->reservationId);
    }
}
```

- [ ] **Run tests**
```bash
make test-unit
```
Expected: test passes.

- [ ] **Commit**
```bash
git add src/Availability/Application/UseCase/DeleteAvailabilityHold/ tests/Availability/Application/UseCase/DeleteAvailabilityHold/
git commit -m "feat(availability): add DeleteAvailabilityHold use case"
```

---

## Task 8: Extend CheckAvailabilityQueryHandler to check active holds

**Files:**
- Modify: `src/Availability/Application/UseCase/CheckAvailability/CheckAvailabilityQueryHandler.php`
- Test: `tests/Availability/Application/UseCase/CheckAvailability/CheckAvailabilityQueryHandlerTest.php`

- [ ] **Write the failing unit test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Availability\Application\UseCase\CheckAvailability;

use App\Availability\Application\UseCase\CheckAvailability\CheckAvailabilityQuery;
use App\Availability\Application\UseCase\CheckAvailability\CheckAvailabilityQueryHandler;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CheckAvailabilityQueryHandlerTest extends TestCase
{
    private MockObject&BlockedPeriodRepositoryInterface $blockedPeriodRepo;
    private MockObject&AvailabilityHoldRepositoryInterface $holdRepo;
    private CheckAvailabilityQueryHandler $handler;

    protected function setUp(): void
    {
        $this->blockedPeriodRepo = $this->createMock(BlockedPeriodRepositoryInterface::class);
        $this->holdRepo = $this->createMock(AvailabilityHoldRepositoryInterface::class);
        $this->handler = new CheckAvailabilityQueryHandler($this->blockedPeriodRepo, $this->holdRepo);
    }

    public function test_returns_true_when_no_overlap(): void
    {
        $this->blockedPeriodRepo->method('hasOverlap')->willReturn(false);
        $this->holdRepo->method('hasActiveOverlap')->willReturn(false);

        $result = ($this->handler)(new CheckAvailabilityQuery(
            'room-uuid',
            new \DateTimeImmutable('2030-06-01'),
            new \DateTimeImmutable('2030-06-05'),
        ));

        self::assertTrue($result);
    }

    public function test_returns_false_when_blocked_period_overlaps(): void
    {
        $this->blockedPeriodRepo->method('hasOverlap')->willReturn(true);
        $this->holdRepo->method('hasActiveOverlap')->willReturn(false);

        $result = ($this->handler)(new CheckAvailabilityQuery(
            'room-uuid',
            new \DateTimeImmutable('2030-06-01'),
            new \DateTimeImmutable('2030-06-05'),
        ));

        self::assertFalse($result);
    }

    public function test_returns_false_when_active_hold_overlaps(): void
    {
        $this->blockedPeriodRepo->method('hasOverlap')->willReturn(false);
        $this->holdRepo->method('hasActiveOverlap')->willReturn(true);

        $result = ($this->handler)(new CheckAvailabilityQuery(
            'room-uuid',
            new \DateTimeImmutable('2030-06-01'),
            new \DateTimeImmutable('2030-06-05'),
        ));

        self::assertFalse($result);
    }
}
```

- [ ] **Run the test to confirm it fails**
```bash
make test-unit
```

- [ ] **Modify the handler**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\CheckAvailability;

use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class CheckAvailabilityQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private BlockedPeriodRepositoryInterface $blockedPeriodRepository,
        private AvailabilityHoldRepositoryInterface $holdRepository,
    ) {
    }

    public function __invoke(CheckAvailabilityQuery $query): bool
    {
        if ($this->blockedPeriodRepository->hasOverlap($query->roomId, $query->checkIn, $query->checkOut)) {
            return false;
        }

        return !$this->holdRepository->hasActiveOverlap($query->roomId, $query->checkIn, $query->checkOut);
    }
}
```

- [ ] **Run tests**
```bash
make test-unit
```
Expected: all three tests pass.

- [ ] **Commit**
```bash
git add src/Availability/Application/UseCase/CheckAvailability/ tests/Availability/Application/UseCase/CheckAvailability/
git commit -m "feat(availability): extend CheckAvailabilityQueryHandler to include active holds"
```

---

## Task 9: ReservationStatus::Expired + Reservation::expire() + ReservationExpired event

**Files:**
- Modify: `src/Reservation/Domain/Model/ReservationStatus.php`
- Modify: `src/Reservation/Domain/Model/Reservation.php`
- Create: `src/Reservation/Domain/Event/ReservationExpired.php`

- [ ] **Add `Expired` case to `ReservationStatus`**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
```

- [ ] **Create the `ReservationExpired` event**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Event;

final readonly class ReservationExpired
{
    public function __construct(
        public string $reservationId,
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}
```

- [ ] **Write the failing unit test for `Reservation::expire()`**

Create `tests/Reservation/Domain/Model/ReservationTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\Model;

use App\Reservation\Domain\Exception\InvalidReservationTransitionException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\DatePeriod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationTest extends TestCase
{
    private function makeReservation(): Reservation
    {
        return new Reservation(
            id: 'res-uuid',
            roomId: 'room-uuid',
            bookerId: 'booker-uuid',
            period: new DatePeriod(
                new \DateTimeImmutable('2030-06-01'),
                new \DateTimeImmutable('2030-06-05'),
            ),
            totalPrice: 40000,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function test_expire_transitions_pending_to_expired(): void
    {
        $reservation = $this->makeReservation();

        $reservation->expire();

        self::assertSame(ReservationStatus::Expired, $reservation->status);
    }

    public function test_expire_throws_when_already_confirmed(): void
    {
        $reservation = $this->makeReservation();
        $reservation->status = ReservationStatus::Confirmed;

        $this->expectException(InvalidReservationTransitionException::class);

        $reservation->expire();
    }

    public function test_expire_throws_when_already_cancelled(): void
    {
        $reservation = $this->makeReservation();
        $reservation->status = ReservationStatus::Cancelled;

        $this->expectException(InvalidReservationTransitionException::class);

        $reservation->expire();
    }
}
```

- [ ] **Run the test to confirm it fails**
```bash
make test-unit
```

- [ ] **Add `expire()` to the `Reservation` model**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

use App\Reservation\Domain\Exception\InvalidReservationTransitionException;
use App\Reservation\Domain\ValueObject\DatePeriod;

final class Reservation
{
    public ReservationStatus $status;

    public function __construct(
        public readonly string $id,
        public readonly string $roomId,
        public readonly string $bookerId,
        public readonly DatePeriod $period,
        public readonly int $totalPrice,
        public readonly \DateTimeImmutable $createdAt,
    ) {
        $this->status = ReservationStatus::Pending;
    }

    public function expire(): void
    {
        if ($this->status !== ReservationStatus::Pending) {
            throw new InvalidReservationTransitionException($this->status, ReservationStatus::Expired);
        }

        $this->status = ReservationStatus::Expired;
    }
}
```

- [ ] **Run tests**
```bash
make test-unit
```
Expected: all tests pass.

- [ ] **Commit**
```bash
git add src/Reservation/Domain/ tests/Reservation/Domain/
git commit -m "feat(reservation): add Expired status, expire() method, and ReservationExpired event"
```

---

## Task 10: ExpireReservation use case

**Files:**
- Create: `src/Reservation/Application/UseCase/ExpireReservation/ExpireReservationCommand.php`
- Create: `src/Reservation/Application/UseCase/ExpireReservation/ExpireReservationCommandHandler.php`
- Test: `tests/Reservation/Application/UseCase/ExpireReservation/ExpireReservationCommandHandlerTest.php`

- [ ] **Write the failing unit test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Application\UseCase\ExpireReservation;

use App\Reservation\Application\UseCase\ExpireReservation\ExpireReservationCommand;
use App\Reservation\Application\UseCase\ExpireReservation\ExpireReservationCommandHandler;
use App\Reservation\Domain\Event\ReservationExpired;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\DatePeriod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class ExpireReservationCommandHandlerTest extends TestCase
{
    private MockObject&ReservationRepositoryInterface $repository;
    private MockObject&EventDispatcherInterface $eventDispatcher;
    private ExpireReservationCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReservationRepositoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->handler = new ExpireReservationCommandHandler($this->repository, $this->eventDispatcher);
    }

    private function makeReservation(string $id = 'res-uuid', ReservationStatus $status = ReservationStatus::Pending): Reservation
    {
        $r = new Reservation(
            id: $id,
            roomId: 'room-uuid',
            bookerId: 'booker-uuid',
            period: new DatePeriod(new \DateTimeImmutable('2030-06-01'), new \DateTimeImmutable('2030-06-05')),
            totalPrice: 40000,
            createdAt: new \DateTimeImmutable(),
        );
        $r->status = $status;

        return $r;
    }

    public function test_expires_pending_reservation_and_dispatches_event(): void
    {
        $reservation = $this->makeReservation();
        $this->repository->method('get')->willReturn($reservation);
        $this->repository->expects(self::once())->method('add')->with($reservation);
        $this->eventDispatcher->expects(self::once())->method('dispatch')
            ->with(self::isInstanceOf(ReservationExpired::class));

        ($this->handler)(new ExpireReservationCommand('res-uuid'));

        self::assertSame(ReservationStatus::Expired, $reservation->status);
    }

    public function test_is_noop_when_reservation_not_found(): void
    {
        $this->repository->method('get')->willReturn(null);
        $this->repository->expects(self::never())->method('add');
        $this->eventDispatcher->expects(self::never())->method('dispatch');

        ($this->handler)(new ExpireReservationCommand('res-uuid'));
    }

    public function test_is_noop_when_reservation_already_confirmed(): void
    {
        $reservation = $this->makeReservation(status: ReservationStatus::Confirmed);
        $this->repository->method('get')->willReturn($reservation);
        $this->repository->expects(self::never())->method('add');
        $this->eventDispatcher->expects(self::never())->method('dispatch');

        ($this->handler)(new ExpireReservationCommand('res-uuid'));
    }
}
```

- [ ] **Run the test to confirm it fails**
```bash
make test-unit
```

- [ ] **Create the command**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\ExpireReservation;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class ExpireReservationCommand implements AsyncCommandInterface
{
    public function __construct(public string $reservationId)
    {
    }
}
```

- [ ] **Create the handler**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\ExpireReservation;

use App\Reservation\Domain\Event\ReservationExpired;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class ExpireReservationCommandHandler
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ExpireReservationCommand $command): void
    {
        $reservation = $this->repository->get($command->reservationId);

        if (null === $reservation || $reservation->status !== ReservationStatus::Pending) {
            return;
        }

        $reservation->expire();
        $this->repository->add($reservation);

        $this->eventDispatcher->dispatch(new ReservationExpired(
            reservationId: $reservation->id,
            roomId: $reservation->roomId,
            checkIn: $reservation->period->checkIn,
            checkOut: $reservation->period->checkOut,
        ));
    }
}
```

Note: `ExpireReservationCommandHandler` does NOT implement `SyncCommandHandlerInterface` — it is consumed from RabbitMQ via `messenger.bus.default`, not via `sync.command.bus`. With `autoconfigure: true`, Symfony auto-tags it as a handler for the default bus based on its `__invoke` type hint.

- [ ] **Run tests**
```bash
make test-unit
```
Expected: all three tests pass.

- [ ] **Verify the `ReservationRepositoryInterface` has a `get()` method**

Check `src/Reservation/Domain/Port/ReservationRepositoryInterface.php`. If `get(string $id): ?Reservation` is missing, add it there and implement it in `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php`.

- [ ] **Commit**
```bash
git add src/Reservation/Application/UseCase/ExpireReservation/ tests/Reservation/Application/UseCase/ExpireReservation/
git commit -m "feat(reservation): add ExpireReservation async use case"
```

---

## Task 11: Event listeners in Availability Infrastructure

**Files:**
- Create: `src/Availability/Infrastructure/EventListener/ReservationCreatedListener.php`
- Create: `src/Availability/Infrastructure/EventListener/ReservationExpiredListener.php`
- Modify: `config/services/availability.yaml` — exclude Event dirs from resource scanning

- [ ] **Create the ReservationCreated listener**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\EventListener;

use App\Availability\Application\UseCase\CreateAvailabilityHold\CreateAvailabilityHoldCommand;
use App\Availability\Domain\Port\AvailabilityHoldIdGeneratorInterface;
use App\Reservation\Domain\Event\ReservationCreated;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationCreated::class)]
final readonly class ReservationCreatedListener
{
    private const int HOLD_TTL_SECONDS = 900;

    public function __construct(
        private SyncCommandBusInterface $commandBus,
        private AvailabilityHoldIdGeneratorInterface $idGenerator,
    ) {
    }

    public function __invoke(ReservationCreated $event): void
    {
        $this->commandBus->handle(new CreateAvailabilityHoldCommand(
            id: $this->idGenerator->generate(),
            roomId: $event->roomId,
            reservationId: $event->reservationId,
            checkIn: $event->checkIn,
            checkOut: $event->checkOut,
            expiresAt: new \DateTimeImmutable(sprintf('+%d seconds', self::HOLD_TTL_SECONDS)),
            createdAt: new \DateTimeImmutable(),
        ));
    }
}
```

- [ ] **Check that `SyncCommandBusInterface` has a `handle()` method**

Open `src/Shared/Application/Bus/SyncCommandBusInterface.php`. If the method is named differently (e.g., `dispatch()`), use that name in the listener above.

- [ ] **Check that `ReservationCreated` exposes a `reservationId` property**

Open `src/Reservation/Domain/Event/ReservationCreated.php`. Verify the property name — it may be `reservationId` or `id`. Adjust the listener accordingly.

- [ ] **Create the ReservationExpired listener**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\EventListener;

use App\Availability\Application\UseCase\DeleteAvailabilityHold\DeleteAvailabilityHoldCommand;
use App\Reservation\Domain\Event\ReservationExpired;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationExpired::class)]
final readonly class ReservationExpiredListener
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    public function __invoke(ReservationExpired $event): void
    {
        $this->commandBus->handle(new DeleteAvailabilityHoldCommand(
            reservationId: $event->reservationId,
        ));
    }
}
```

- [ ] **Exclude `Reservation\Domain\Event\` from the Availability resource scan**

The Availability listeners import `ReservationCreated` and `ReservationExpired`. Symfony must NOT try to register these Reservation classes as Availability services. Add excludes to `config/services/availability.yaml` if the resource scan covers `src/Availability/` only (it already does — no changes needed). Verify: `App\Availability\Infrastructure\:` resource is `../../src/Availability/Infrastructure/` — already scoped correctly.

- [ ] **Compile the container**
```bash
make lint
```
Expected: no errors.

- [ ] **Commit**
```bash
git add src/Availability/Infrastructure/EventListener/
git commit -m "feat(availability): add ReservationCreated and ReservationExpired listeners"
```

---

## Task 12: Modify CreateReservationCommandHandler

**Files:**
- Modify: `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php`

- [ ] **Write the failing unit test additions**

Add to `tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php` (create the file if it doesn't exist):

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Application\UseCase\CreateReservation;

use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommand;
use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommandHandler;
use App\Reservation\Domain\Exception\BookerNotFoundException;
use App\Reservation\Domain\Exception\RoomNotAvailableException;
use App\Reservation\Domain\Exception\RoomNotFoundException;
use App\Reservation\Domain\Port\BookerExistsInterface;
use App\Reservation\Domain\Port\PriceCalculatorInterface;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;
use App\Reservation\Domain\Port\RoomExistsInterface;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Application\Transaction\TransactionManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class CreateReservationCommandHandlerTest extends TestCase
{
    private MockObject&ReservationRepositoryInterface $repository;
    private MockObject&RoomExistsInterface $roomExists;
    private MockObject&BookerExistsInterface $bookerExists;
    private MockObject&RoomAvailabilityCheckerInterface $availabilityChecker;
    private MockObject&PriceCalculatorInterface $priceCalculator;
    private MockObject&EventDispatcherInterface $eventDispatcher;
    private MockObject&TransactionManagerInterface $transactionManager;
    private MockObject&AsyncCommandDispatcherInterface $asyncDispatcher;
    private CreateReservationCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReservationRepositoryInterface::class);
        $this->roomExists = $this->createMock(RoomExistsInterface::class);
        $this->bookerExists = $this->createMock(BookerExistsInterface::class);
        $this->availabilityChecker = $this->createMock(RoomAvailabilityCheckerInterface::class);
        $this->priceCalculator = $this->createMock(PriceCalculatorInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->transactionManager = $this->createMock(TransactionManagerInterface::class);
        $this->asyncDispatcher = $this->createMock(AsyncCommandDispatcherInterface::class);

        $this->transactionManager->method('transactional')
            ->willReturnCallback(fn (callable $cb) => $cb());

        $this->handler = new CreateReservationCommandHandler(
            $this->repository,
            $this->roomExists,
            $this->bookerExists,
            $this->availabilityChecker,
            $this->priceCalculator,
            $this->eventDispatcher,
            $this->transactionManager,
            $this->asyncDispatcher,
        );
    }

    private function makeCommand(): CreateReservationCommand
    {
        return new CreateReservationCommand(
            id: 'res-uuid',
            roomId: 'room-uuid',
            bookerId: 'booker-uuid',
            checkIn: new \DateTimeImmutable('2030-06-01'),
            checkOut: new \DateTimeImmutable('2030-06-05'),
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function test_throws_when_room_not_found(): void
    {
        $this->roomExists->method('exists')->willReturn(false);
        $this->expectException(RoomNotFoundException::class);
        ($this->handler)($this->makeCommand());
    }

    public function test_throws_when_booker_not_found(): void
    {
        $this->roomExists->method('exists')->willReturn(true);
        $this->bookerExists->method('exists')->willReturn(false);
        $this->expectException(BookerNotFoundException::class);
        ($this->handler)($this->makeCommand());
    }

    public function test_throws_when_room_not_available(): void
    {
        $this->roomExists->method('exists')->willReturn(true);
        $this->bookerExists->method('exists')->willReturn(true);
        $this->availabilityChecker->method('isAvailable')->willReturn(false);
        $this->expectException(RoomNotAvailableException::class);
        ($this->handler)($this->makeCommand());
    }

    public function test_creates_reservation_and_dispatches_delayed_expire(): void
    {
        $this->roomExists->method('exists')->willReturn(true);
        $this->bookerExists->method('exists')->willReturn(true);
        $this->availabilityChecker->method('isAvailable')->willReturn(true);
        $this->priceCalculator->method('calculate')->willReturn(40000);
        $this->repository->expects(self::once())->method('add');
        $this->asyncDispatcher->expects(self::once())->method('dispatch')
            ->with(self::anything(), 900_000);

        ($this->handler)($this->makeCommand());
    }
}
```

- [ ] **Run the test to confirm it fails**
```bash
make test-unit
```

- [ ] **Modify the handler**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CreateReservation;

use App\Reservation\Domain\Event\ReservationCreated;
use App\Reservation\Domain\Exception\BookerNotFoundException;
use App\Reservation\Domain\Exception\RoomNotAvailableException;
use App\Reservation\Domain\Exception\RoomNotFoundException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Port\BookerExistsInterface;
use App\Reservation\Domain\Port\PriceCalculatorInterface;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;
use App\Reservation\Domain\Port\RoomExistsInterface;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Application\Transaction\TransactionManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class CreateReservationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private RoomExistsInterface $roomExists,
        private BookerExistsInterface $bookerExists,
        private RoomAvailabilityCheckerInterface $availabilityChecker,
        private PriceCalculatorInterface $priceCalculator,
        private EventDispatcherInterface $eventDispatcher,
        private TransactionManagerInterface $transactionManager,
        private AsyncCommandDispatcherInterface $asyncDispatcher,
    ) {
    }

    public function __invoke(CreateReservationCommand $command): void
    {
        if (!$this->roomExists->exists($command->roomId)) {
            throw new RoomNotFoundException($command->roomId);
        }

        if (!$this->bookerExists->exists($command->bookerId)) {
            throw new BookerNotFoundException($command->bookerId);
        }

        if (!$this->availabilityChecker->isAvailable($command->roomId, $command->checkIn, $command->checkOut)) {
            throw new RoomNotAvailableException($command->roomId);
        }

        $totalPrice = $this->priceCalculator->calculate($command->roomId, $command->checkIn, $command->checkOut);

        $reservation = new Reservation(
            id: $command->id,
            roomId: $command->roomId,
            bookerId: $command->bookerId,
            period: new DatePeriod($command->checkIn, $command->checkOut),
            totalPrice: $totalPrice,
            createdAt: $command->createdAt,
        );

        $this->transactionManager->transactional(function () use ($reservation): void {
            $this->repository->add($reservation);

            $this->eventDispatcher->dispatch(new ReservationCreated(
                reservationId: $reservation->id,
                roomId: $reservation->roomId,
                bookerId: $reservation->bookerId,
                checkIn: $reservation->period->checkIn,
                checkOut: $reservation->period->checkOut,
                totalPrice: $reservation->totalPrice,
            ));
        });

        $this->asyncDispatcher->dispatch(
            new ExpireReservationCommand($reservation->id),
            900_000,
        );
    }
}
```

Note: `ExpireReservationCommand` is in the `Reservation\Application\UseCase\ExpireReservation\` namespace — add the `use` statement:
```php
use App\Reservation\Application\UseCase\ExpireReservation\ExpireReservationCommand;
```

- [ ] **Run tests**
```bash
make test-unit
```
Expected: all tests pass.

- [ ] **Commit**
```bash
git add src/Reservation/Application/UseCase/CreateReservation/ tests/Reservation/Application/UseCase/CreateReservation/
git commit -m "feat(reservation): wrap CreateReservation in transaction and dispatch delayed ExpireReservation"
```

---

## Task 13: Final DI config wiring + smoke test

- [ ] **Verify `config/services/reservation.yaml` excludes `ExpireReservationCommand`**

The existing exclude pattern `'../../src/Reservation/Application/**/*Command.php'` already covers it. No change needed.

- [ ] **Compile the full container and run linting**
```bash
make lint
```
Expected: no errors.

- [ ] **Run the full test suite**
```bash
make test
```
Expected: all tests pass.

- [ ] **Run a functional smoke test for the create-reservation endpoint**

Start the app and send a request (adjust IDs to valid UUIDs present in your test database):

```bash
curl -X POST http://localhost/reservations \
  -H 'Content-Type: application/json' \
  -d '{"room_id":"<valid-room-uuid>","booker_id":"<valid-booker-uuid>","check_in":"2030-06-01","check_out":"2030-06-05"}'
```

Expected: HTTP 201, reservation created.

Check the `availability_hold` table:
```bash
docker compose exec postgres psql -U bookit -c "SELECT * FROM availability_hold;"
```
Expected: one row with `expires_at` ~15 minutes in the future.

- [ ] **Commit**
```bash
git add config/
git commit -m "chore(config): finalize DI wiring for availability hold and expiration"
```

---

## Self-Review Checklist

- **Double-booking prevention**: `CheckAvailabilityQueryHandler` checks both `blocked_period` and active `availability_hold` ✓
- **Atomic create + hold**: `transactionManager.transactional()` wraps both `repository.add()` and `ReservationCreated` dispatch ✓
- **Concurrent race condition guard**: `CreateAvailabilityHoldCommandHandler` re-checks `hasActiveOverlap` inside the transaction; if two requests race, the second's hold creation will detect the first's hold and throw `AvailabilityHoldOverlapException`, rolling back the transaction ✓
- **Idempotent expiration**: `ExpireReservationCommandHandler` is a no-op if reservation is not `pending` ✓
- **Async dispatch after transaction**: `asyncDispatcher.dispatch()` is called AFTER `transactional()` returns, ensuring the DB is committed before the delayed message is sent ✓
- **Hold cleanup on expiration**: `ReservationExpiredListener` calls `DeleteAvailabilityHoldCommand` ✓
- **ReservationStatus.Expired**: Added to enum ✓
- **Exception → HTTP 409**: `AvailabilityHoldOverlapException` mapped in `exceptions.yaml` ✓
- **RabbitMQ delay infrastructure**: `delays` exchange already declared in `.docker/rabbitmq/definitions.json`, `auto_setup: false` retained ✓

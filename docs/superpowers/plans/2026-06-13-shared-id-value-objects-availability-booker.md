# Shared ID Value Objects — Availability & Booker (aggregate $id only)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace raw `string $id` on three aggregate root domain models with typed VOs: `AvailabilityHold::$id`, `BlockedPeriod::$id`, `Booker::$id`.

**Architecture:** Three VOs (`AvailabilityHoldId`, `BlockedPeriodId`, `BookerId`) in `Shared\Domain\ValueObject\`. Only the aggregate's own identity field changes — cross-context reference IDs (`$roomId`, `$reservationId`, etc.) stay as raw strings in this tranche. The cascade goes Domain model → Domain ports → Infrastructure → Application commands/queries → UI → serializers → tests, committed in two slices (Booker then Availability).

**Tech Stack:** PHP 8.4, Symfony 8, DBAL (no ORM). All commands via `make`.

---

## File Map

**Create:**
- `src/Shared/Domain/ValueObject/BookerId.php`
- `src/Shared/Domain/ValueObject/AvailabilityHoldId.php`
- `src/Shared/Domain/ValueObject/BlockedPeriodId.php`
- `tests/Shared/Domain/ValueObject/IdValueObjectsTest.php`

**Modify — Booker:**
- `src/Booker/Domain/Model/Booker.php` — `string $id` → `BookerId`
- `src/Booker/Domain/Port/BookerIdGeneratorInterface.php` — return `BookerId`
- `src/Booker/Domain/Port/BookerRepositoryInterface.php` — `get(BookerId)`
- `src/Booker/Domain/Port/ExternalAccountRegistrarInterface.php` — params `BookerId`
- `src/Booker/Infrastructure/Service/BookerIdGenerator.php`
- `src/Booker/Infrastructure/Persistence/Doctrine/BookerRepository.php`
- `src/Booker/Infrastructure/Contract/SecurityAccountRegistrarAdapter.php`
- `src/Booker/Application/UseCase/RegisterBookerWithCredentials/RegisterBookerWithCredentialsCommand.php`
- `src/Booker/Application/UseCase/RegisterBookerWithCredentials/RegisterBookerWithCredentialsCommandHandler.php`
- `src/Booker/Application/UseCase/GetBooker/GetBookerQuery.php`
- `src/Booker/UI/Http/Controller/GetBooker/GetBookerController.php`
- `src/Booker/UI/Http/Controller/BookerSerializer.php`
- `tests/Booker/Infrastructure/Persistence/InMemory/InMemoryBookerRepository.php`
- `tests/Booker/Infrastructure/ExternalAccount/NullExternalAccountRegistrar.php`
- `tests/Booker/Infrastructure/ExternalAccount/ThrowingExternalAccountRegistrar.php`
- `tests/Booker/Application/UseCase/RegisterBookerWithCredentials/RegisterBookerWithCredentialsCommandHandlerTest.php`

**Modify — Availability:**
- `src/Availability/Domain/Model/AvailabilityHold.php` — `string $id` → `AvailabilityHoldId`
- `src/Availability/Domain/Model/BlockedPeriod.php` — `string $id` → `BlockedPeriodId`
- `src/Availability/Domain/Port/AvailabilityHoldIdGeneratorInterface.php` — return `AvailabilityHoldId`
- `src/Availability/Domain/Port/BlockedPeriodIdGeneratorInterface.php` — return `BlockedPeriodId`
- `src/Availability/Infrastructure/Service/AvailabilityHoldIdGenerator.php`
- `src/Availability/Infrastructure/Service/BlockedPeriodIdGenerator.php`
- `src/Availability/Infrastructure/Persistence/Doctrine/AvailabilityHoldRepository.php`
- `src/Availability/Infrastructure/Persistence/Doctrine/BlockedPeriodRepository.php` — `get(BlockedPeriodId)`, `remove(BlockedPeriodId)`, `add()` uses `->value`
- `src/Availability/Domain/Port/BlockedPeriodRepositoryInterface.php` — `get(BlockedPeriodId)`, `remove(BlockedPeriodId)`
- `src/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommand.php` — `BlockedPeriodId $id`
- `src/Availability/Application/UseCase/BlockPeriod/BlockPeriodCommandHandler.php` — dispatch event with `->value`
- `src/Availability/Application/UseCase/CreateAvailabilityHold/CreateAvailabilityHoldCommand.php` — `AvailabilityHoldId $id`
- `src/Availability/Application/UseCase/CreateAvailabilityHold/CreateAvailabilityHoldCommandHandler.php` — dispatch event with `->value`
- `src/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommand.php` — `BlockedPeriodId $id`
- `src/Availability/Application/UseCase/DeleteBlockedPeriod/DeleteBlockedPeriodCommandHandler.php`
- `src/Availability/Application/UseCase/GetBlockedPeriod/GetBlockedPeriodQuery.php` — `BlockedPeriodId $id`
- `src/Availability/UI/Http/Controller/DeleteBlockedPeriod/DeleteBlockedPeriodController.php`
- `src/Availability/UI/Http/Controller/BlockedPeriodSerializer.php`
- `tests/Availability/Infrastructure/Persistence/InMemory/InMemoryAvailabilityHoldRepository.php`
- `tests/Availability/Infrastructure/Persistence/InMemory/InMemoryBlockedPeriodRepository.php`
- + all Availability test files that construct models or commands with raw string `$id`

---

## Task 0: Create branch

- [ ] **Create branch**

```bash
git checkout -b refactor/shared-id-value-objects
```

---

## Task 1: Create the 3 VOs + tests

- [ ] **Create BookerId.php**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class BookerId
{
    public function __construct(public string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Create AvailabilityHoldId.php**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class AvailabilityHoldId
{
    public function __construct(public string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Create BlockedPeriodId.php**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class BlockedPeriodId
{
    public function __construct(public string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Create tests/Shared/Domain/ValueObject/IdValueObjectsTest.php**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AvailabilityHoldId;
use App\Shared\Domain\ValueObject\BlockedPeriodId;
use App\Shared\Domain\ValueObject\BookerId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class IdValueObjectsTest extends TestCase
{
    private const string UUID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    #[Test]
    public function booker_id_exposes_value_and_casts_to_string(): void
    {
        $id = new BookerId(self::UUID);
        self::assertSame(self::UUID, $id->value);
        self::assertSame(self::UUID, (string) $id);
    }

    #[Test]
    public function availability_hold_id_exposes_value_and_casts_to_string(): void
    {
        $id = new AvailabilityHoldId(self::UUID);
        self::assertSame(self::UUID, $id->value);
        self::assertSame(self::UUID, (string) $id);
    }

    #[Test]
    public function blocked_period_id_exposes_value_and_casts_to_string(): void
    {
        $id = new BlockedPeriodId(self::UUID);
        self::assertSame(self::UUID, $id->value);
        self::assertSame(self::UUID, (string) $id);
    }
}
```

- [ ] **Run — expect green**

```bash
make unit-test
```

---

## Task 2: Booker — Domain layer

- [ ] **Update Booker.php**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Domain\Model;

use App\Shared\Domain\ValueObject\BookerId;

final readonly class Booker
{
    public function __construct(
        public BookerId $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public \DateTimeImmutable $dateOfBirth,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
```

- [ ] **Update BookerIdGeneratorInterface.php**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Domain\Port;

use App\Shared\Domain\ValueObject\BookerId;

interface BookerIdGeneratorInterface
{
    public function generate(): BookerId;
}
```

- [ ] **Update BookerRepositoryInterface.php**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Domain\Port;

use App\Booker\Domain\Model\Booker;
use App\Shared\Domain\ValueObject\BookerId;

interface BookerRepositoryInterface
{
    public function add(Booker $booker): void;

    public function get(BookerId $id): ?Booker;

    public function existsByEmail(string $email): bool;
}
```

- [ ] **Update ExternalAccountRegistrarInterface.php**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Domain\Port;

use App\Shared\Domain\ValueObject\BookerId;

interface ExternalAccountRegistrarInterface
{
    public function register(BookerId $bookerId, string $email, string $password): void;

    public function unregister(BookerId $bookerId): void;
}
```

---

## Task 3: Booker — Infrastructure

- [ ] **Update BookerIdGenerator.php**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Infrastructure\Service;

use App\Booker\Domain\Port\BookerIdGeneratorInterface;
use App\Shared\Domain\ValueObject\BookerId;
use Symfony\Component\Uid\Uuid;

final class BookerIdGenerator implements BookerIdGeneratorInterface
{
    public function generate(): BookerId
    {
        return new BookerId(Uuid::v4()->toString());
    }
}
```

- [ ] **Update BookerRepository.php**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Infrastructure\Persistence\Doctrine;

use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Shared\Domain\ValueObject\BookerId;
use Doctrine\DBAL\Connection;

final readonly class BookerRepository implements BookerRepositoryInterface
{
    public function __construct(private Connection $bookerConnection)
    {
    }

    public function add(Booker $booker): void
    {
        $this->bookerConnection->insert('booker', [
            'id' => $booker->id->value,
            'first_name' => $booker->firstName,
            'last_name' => $booker->lastName,
            'email' => $booker->email,
            'phone' => $booker->phone,
            'date_of_birth' => $booker->dateOfBirth->format('Y-m-d'),
            'registered_at' => $booker->registeredAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(BookerId $id): ?Booker
    {
        /** @var array{id: string, first_name: string, last_name: string, email: string, phone: string, date_of_birth: string, registered_at: string}|false $row */
        $row = $this->bookerConnection->fetchAssociative(
            'SELECT id, first_name, last_name, email, phone, date_of_birth, registered_at FROM booker WHERE id = :id',
            ['id' => $id->value],
        );

        if (false === $row) {
            return null;
        }

        return new Booker(
            new BookerId($row['id']),
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['phone'],
            new \DateTimeImmutable($row['date_of_birth']),
            new \DateTimeImmutable($row['registered_at']),
        );
    }

    public function existsByEmail(string $email): bool
    {
        /** @var int|string $count */
        $count = $this->bookerConnection->fetchOne(
            'SELECT COUNT(*) FROM booker WHERE LOWER(email) = LOWER(:email)',
            ['email' => $email],
        );

        return (int) $count > 0;
    }
}
```

- [ ] **Update SecurityAccountRegistrarAdapter.php**

Read the file first, then change the two method signatures and replace bare `$bookerId` usages with `$bookerId->value` in the body:

```php
use App\Shared\Domain\ValueObject\BookerId;

public function register(BookerId $bookerId, string $email, string $password): void
{
    // replace occurrences of $bookerId used as string with $bookerId->value
}

public function unregister(BookerId $bookerId): void
{
    // replace occurrences of $bookerId used as string with $bookerId->value
}
```

---

## Task 4: Booker — Application layer

`RegisterBookerWithCredentialsCommandFactory` passes `$this->bookerIdGenerator->generate()` directly to the command — once the generator returns `BookerId`, no change is needed in the factory.

- [ ] **Update RegisterBookerWithCredentialsCommand.php**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Application\UseCase\RegisterBookerWithCredentials;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\BookerId;

final readonly class RegisterBookerWithCredentialsCommand implements SyncCommandInterface
{
    public function __construct(
        public BookerId $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public \DateTimeImmutable $dateOfBirth,
        public string $password,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
```

- [ ] **Update RegisterBookerWithCredentialsCommandHandler.php**

Two logger calls access `$command->id` as string — use `->value`. The `accountRegistrar` and `bookerRepository` calls already accept `BookerId`:

```php
$this->logger->error('Booker persistence failed after account creation — compensating', [
    'booker_id' => $command->id->value,
    ...
]);

$this->logger->info('Booker registered', [
    'booker_id' => $command->id->value,
    ...
]);
```

- [ ] **Update GetBookerQuery.php**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Application\UseCase\GetBooker;

use App\Booker\Domain\Model\Booker;
use App\Shared\Application\Bus\SyncQueryInterface;
use App\Shared\Domain\ValueObject\BookerId;

/** @implements SyncQueryInterface<Booker|null> */
final readonly class GetBookerQuery implements SyncQueryInterface
{
    public function __construct(public BookerId $bookerId)
    {
    }
}
```

(`GetBookerQueryHandler` passes `$query->bookerId` to `BookerRepositoryInterface::get()` — both are now `BookerId`, no change needed.)

---

## Task 5: Booker — UI + Serializer

- [ ] **Update GetBookerController.php**

```php
use App\Shared\Domain\ValueObject\BookerId;

public function __invoke(string $id): Response
{
    $query = new GetBookerQuery(new BookerId($id));
    $booker = $this->queryBus->ask($query);
    // rest unchanged
}
```

(`RegisterBookerController` calls `new GetBookerQuery($command->id)` — `$command->id` is already `BookerId` after Task 4, no change.)

- [ ] **Update BookerSerializer.php**

```php
return [
    'id' => $booker->id->value,
    'firstName' => $booker->firstName,
    // remaining string fields unchanged
];
```

---

## Task 6: Booker — Update tests

- [ ] **Update InMemoryBookerRepository.php**

```php
use App\Shared\Domain\ValueObject\BookerId;

public function add(Booker $booker): void
{
    $this->bookers[$booker->id->value] = $booker;
}

public function get(BookerId $id): ?Booker
{
    return $this->bookers[$id->value] ?? null;
}
```

- [ ] **Update NullExternalAccountRegistrar.php**

```php
use App\Shared\Domain\ValueObject\BookerId;

public function register(BookerId $bookerId, string $email, string $password): void {}

public function unregister(BookerId $bookerId): void {}
```

- [ ] **Update ThrowingExternalAccountRegistrar.php**

```php
use App\Shared\Domain\ValueObject\BookerId;

public function register(BookerId $bookerId, string $email, string $password): void
{
    throw new ExternalAccountCreationException($email, new \RuntimeException('Keycloak unavailable'));
}

public function unregister(BookerId $bookerId): void {}
```

- [ ] **Update RegisterBookerWithCredentialsCommandHandlerTest.php**

Wrap `$id` in `BookerId` in `makeCommand()`. Update mock assertions for `register` and `unregister`:

```php
use App\Shared\Domain\ValueObject\BookerId;

private function makeCommand(
    string $dateOfBirth = '1990-01-01',
    string $registeredAt = '2025-01-01',
    string $email = 'jean@example.com',
    string $id = 'uuid-1',
): RegisterBookerWithCredentialsCommand {
    return new RegisterBookerWithCredentialsCommand(
        new BookerId($id),
        'Jean', 'Dupont', $email, '+33612345678',
        new \DateTimeImmutable($dateOfBirth),
        'password123',
        new \DateTimeImmutable($registeredAt),
    );
}
```

In `it_compensates_by_unregistering_when_db_save_fails`:
```php
$this->accountRegistrar->expects(self::once())->method('unregister')->with(new BookerId('uuid-1'));
```

In `it_registers_external_account_then_saves_booker`:
```php
$this->accountRegistrar->expects(self::once())
    ->method('register')
    ->with(new BookerId('uuid-1'), 'jean@example.com', 'password123');
```

- [ ] **Scan for any remaining Booker tests that pass raw strings as `$id`**

```bash
grep -rln "new Booker(\|new GetBookerQuery(\|new RegisterBookerWithCredentialsCommand(" \
  tests/Booker tests/Notification tests/Reservation --include="*.php"
```

In each file found, wrap the first positional arg (the id) in `new BookerId(...)`.

---

## Task 7: Run Booker slice + commit

- [ ] **Run tests**

```bash
make unit-test && make functional-test
```

Expected: all green.

- [ ] **Commit**

```bash
git add src/Shared/Domain/ValueObject/BookerId.php \
        tests/Shared/Domain/ValueObject/IdValueObjectsTest.php \
        src/Booker tests/Booker
git commit -m "refactor(booker): replace string \$id with BookerId VO on Booker aggregate"
```

---

## Task 8: Availability — Domain models + ports

- [ ] **Update AvailabilityHold.php**

Only the `$id` field changes. `$roomId` and `$reservationId` stay `string`.

```php
<?php

declare(strict_types=1);

namespace App\Availability\Domain\Model;

use App\Availability\Domain\ValueObject\DatePeriod;
use App\Shared\Domain\ValueObject\AvailabilityHoldId;

final readonly class AvailabilityHold
{
    public function __construct(
        public AvailabilityHoldId $id,
        public string $roomId,
        public string $reservationId,
        public DatePeriod $period,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Update BlockedPeriod.php**

Only the `$id` field changes. `$roomId` stays `string`.

```php
<?php

declare(strict_types=1);

namespace App\Availability\Domain\Model;

use App\Availability\Domain\ValueObject\DatePeriod;
use App\Shared\Domain\ValueObject\BlockedPeriodId;

final readonly class BlockedPeriod
{
    public function __construct(
        public BlockedPeriodId $id,
        public string $roomId,
        public DatePeriod $period,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Update AvailabilityHoldIdGeneratorInterface.php**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Domain\Port;

use App\Shared\Domain\ValueObject\AvailabilityHoldId;

interface AvailabilityHoldIdGeneratorInterface
{
    public function generate(): AvailabilityHoldId;
}
```

- [ ] **Update BlockedPeriodIdGeneratorInterface.php**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Domain\Port;

use App\Shared\Domain\ValueObject\BlockedPeriodId;

interface BlockedPeriodIdGeneratorInterface
{
    public function generate(): BlockedPeriodId;
}
```

- [ ] **Update BlockedPeriodRepositoryInterface.php**

Only the `$id` parameter on `get` and `remove` changes. `$roomId` params stay `string`.

```php
<?php

declare(strict_types=1);

namespace App\Availability\Domain\Port;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Shared\Domain\ValueObject\BlockedPeriodId;

interface BlockedPeriodRepositoryInterface
{
    public function add(BlockedPeriod $period): void;

    public function get(BlockedPeriodId $id): ?BlockedPeriod;

    public function remove(BlockedPeriodId $id): void;

    public function hasOverlap(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool;

    /** @return list<BlockedPeriod> */
    public function listByRoomId(string $roomId): array;

    public function removeByRoomAndPeriod(
        string $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void;
}
```

(`AvailabilityHoldRepositoryInterface` has no `$id` param — no change needed.)

---

## Task 9: Availability — Infrastructure

- [ ] **Update AvailabilityHoldIdGenerator.php**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\Service;

use App\Availability\Domain\Port\AvailabilityHoldIdGeneratorInterface;
use App\Shared\Domain\ValueObject\AvailabilityHoldId;
use Symfony\Component\Uid\Uuid;

final class AvailabilityHoldIdGenerator implements AvailabilityHoldIdGeneratorInterface
{
    public function generate(): AvailabilityHoldId
    {
        return new AvailabilityHoldId(Uuid::v4()->toRfc4122());
    }
}
```

- [ ] **Update BlockedPeriodIdGenerator.php**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\Service;

use App\Availability\Domain\Port\BlockedPeriodIdGeneratorInterface;
use App\Shared\Domain\ValueObject\BlockedPeriodId;
use Symfony\Component\Uid\Uuid;

final class BlockedPeriodIdGenerator implements BlockedPeriodIdGeneratorInterface
{
    public function generate(): BlockedPeriodId
    {
        return new BlockedPeriodId(Uuid::v4()->toString());
    }
}
```

- [ ] **Update AvailabilityHoldRepository.php**

Only the `$hold->id` write changes. `$roomId` and `$reservationId` remain strings.

```php
public function add(AvailabilityHold $hold): void
{
    $this->availabilityConnection->insert('hold', [
        'id' => $hold->id->value,
        'room_id' => $hold->roomId,          // string — unchanged
        'reservation_id' => $hold->reservationId,  // string — unchanged
        'check_in' => $hold->period->checkIn->format('Y-m-d'),
        'check_out' => $hold->period->checkOut->format('Y-m-d'),
        'expires_at' => $hold->expiresAt->format('Y-m-d H:i:s'),
        'created_at' => $hold->createdAt->format('Y-m-d H:i:s'),
    ]);
}
```

(`deleteByReservationId` and `hasActiveOverlap` take strings — no change.)

AvailabilityHold has no `get` method, so no hydration to update.

- [ ] **Update BlockedPeriodRepository.php**

Three changes: `add()` uses `$period->id->value`, `get()` and `remove()` take `BlockedPeriodId`, `hydrate()` wraps `$row['id']` in `BlockedPeriodId`. The `$roomId` params and hydration stay as string.

```php
use App\Shared\Domain\ValueObject\BlockedPeriodId;

public function add(BlockedPeriod $period): void
{
    $this->availabilityConnection->insert('blocked_period', [
        'id' => $period->id->value,
        'room_id' => $period->roomId,   // string — unchanged
        ...
    ]);
}

public function get(BlockedPeriodId $id): ?BlockedPeriod
{
    $row = $this->availabilityConnection->fetchAssociative(
        'SELECT id, room_id, check_in, check_out, created_at FROM blocked_period WHERE id = :id',
        ['id' => $id->value],
    );
    ...
}

public function remove(BlockedPeriodId $id): void
{
    $this->availabilityConnection->delete('blocked_period', ['id' => $id->value]);
}

// hydrate():
private function hydrate(array $row): BlockedPeriod
{
    return new BlockedPeriod(
        new BlockedPeriodId($row['id']),
        $row['room_id'],   // string — unchanged
        new DatePeriod(...),
        new \DateTimeImmutable($row['created_at']),
    );
}
```

(`hasOverlap`, `listByRoomId`, `removeByRoomAndPeriod` all take `string $roomId` — no change.)

---

## Task 10: Availability — Application layer

`BlockPeriodCommandFactory` passes `$this->idGenerator->generate()` directly — once the generator returns `BlockedPeriodId`, no factory change is needed.

- [ ] **Update BlockPeriodCommand.php**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\BlockPeriod;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\BlockedPeriodId;

final readonly class BlockPeriodCommand implements SyncCommandInterface
{
    public function __construct(
        public BlockedPeriodId $id,
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Update BlockPeriodCommandHandler.php**

`BlockedPeriod` constructor now takes `BlockedPeriodId $id` — pass `$command->id` directly. The Shared event `BlockedPeriodCreated` still takes `string $blockedPeriodId` — pass `$command->id->value`:

```php
$this->repository->add(new BlockedPeriod(
    $command->id,
    $command->roomId,
    new DatePeriod($command->checkIn, $command->checkOut),
    $command->createdAt,
));

$this->eventDispatcher->dispatch(new BlockedPeriodCreated(
    blockedPeriodId: $command->id->value,
    roomId: $command->roomId,
    checkIn: $command->checkIn,
    checkOut: $command->checkOut,
));
```

Also check `RoomNotFoundException` — if its constructor takes `string`, pass `$command->roomId` (already string, no change).

- [ ] **Update CreateAvailabilityHoldCommand.php**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\CreateAvailabilityHold;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\AvailabilityHoldId;

final readonly class CreateAvailabilityHoldCommand implements SyncCommandInterface
{
    public function __construct(
        public AvailabilityHoldId $id,
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

- [ ] **Update CreateAvailabilityHoldCommandHandler.php**

`AvailabilityHold` constructor takes `AvailabilityHoldId $id`. Shared event `AvailabilityHoldCreated` takes `string $holdId`:

```php
$this->repository->add(new AvailabilityHold(
    id: $command->id,
    roomId: $command->roomId,
    reservationId: $command->reservationId,
    period: new DatePeriod($command->checkIn, $command->checkOut),
    expiresAt: $command->expiresAt,
    createdAt: $command->createdAt,
));

$this->eventDispatcher->dispatch(new AvailabilityHoldCreated(
    holdId: $command->id->value,
    roomId: $command->roomId,
    reservationId: $command->reservationId,
    checkIn: $command->checkIn,
    checkOut: $command->checkOut,
    expiresAt: $command->expiresAt,
));
```

- [ ] **Update DeleteBlockedPeriodCommand.php**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\DeleteBlockedPeriod;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\BlockedPeriodId;

final readonly class DeleteBlockedPeriodCommand implements SyncCommandInterface
{
    public function __construct(public BlockedPeriodId $id)
    {
    }
}
```

- [ ] **Update DeleteBlockedPeriodCommandHandler.php**

```php
public function __invoke(DeleteBlockedPeriodCommand $command): void
{
    $blockedPeriod = $this->repository->get($command->id);

    if (null === $blockedPeriod) {
        throw new BlockedPeriodNotFoundException($command->id->value);
    }

    $this->repository->remove($command->id);

    $this->eventDispatcher->dispatch(new BlockedPeriodDeleted(
        roomId: $blockedPeriod->roomId,  // string — unchanged
        checkIn: $blockedPeriod->period->checkIn,
        checkOut: $blockedPeriod->period->checkOut,
    ));
}
```

- [ ] **Update GetBlockedPeriodQuery.php**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Application\UseCase\GetBlockedPeriod;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Shared\Application\Bus\SyncQueryInterface;
use App\Shared\Domain\ValueObject\BlockedPeriodId;

/** @implements SyncQueryInterface<BlockedPeriod|null> */
final readonly class GetBlockedPeriodQuery implements SyncQueryInterface
{
    public function __construct(public BlockedPeriodId $id)
    {
    }
}
```

(`GetBlockedPeriodQueryHandler` passes `$query->id` to `BlockedPeriodRepositoryInterface::get()` — both now `BlockedPeriodId`, no change needed.)

---

## Task 11: Availability — UI + Serializer

- [ ] **Update DeleteBlockedPeriodController.php**

```php
use App\Shared\Domain\ValueObject\BlockedPeriodId;

public function __invoke(string $id): Response
{
    $this->commandBus->execute(new DeleteBlockedPeriodCommand(new BlockedPeriodId($id)));

    return new Response(null, Response::HTTP_NO_CONTENT);
}
```

`BlockPeriodController` builds `GetBlockedPeriodQuery($command->id)` where `$command->id` is already `BlockedPeriodId` — no change needed.

- [ ] **Update BlockedPeriodSerializer.php**

```php
return [
    'id' => $period->id->value,
    'roomId' => $period->roomId,   // string — unchanged
    ...
];
```

---

## Task 12: Availability — Update tests

- [ ] **Update InMemoryAvailabilityHoldRepository.php**

```php
use App\Shared\Domain\ValueObject\AvailabilityHoldId;  // not used in signatures, but $hold->id->value needed

public function add(AvailabilityHold $hold): void
{
    $this->holds[$hold->id->value] = $hold;
}
```

(`deleteByReservationId` and `hasActiveOverlap` take strings — no change. Internal comparisons `$hold->reservationId` and `$hold->roomId` stay strings.)

- [ ] **Update InMemoryBlockedPeriodRepository.php**

```php
use App\Shared\Domain\ValueObject\BlockedPeriodId;

public function add(BlockedPeriod $period): void
{
    $this->periods[$period->id->value] = $period;
}

public function get(BlockedPeriodId $id): ?BlockedPeriod
{
    return $this->periods[$id->value] ?? null;
}

public function remove(BlockedPeriodId $id): void
{
    unset($this->periods[$id->value]);
}
```

(`hasOverlap`, `listByRoomId`, `removeByRoomAndPeriod` take `string $roomId` — no change. Internal comparisons `$period->roomId` stay string.)

- [ ] **Scan and update all Availability unit tests that build commands or models with raw string `$id`**

```bash
grep -rln "new BlockPeriodCommand(\|new CreateAvailabilityHoldCommand(\|new DeleteBlockedPeriodCommand(\|new AvailabilityHold(\|new BlockedPeriod(\|new GetBlockedPeriodQuery(" \
  tests/Availability --include="*.php"
```

In each file: wrap the first positional argument (the `$id`) in `new BlockedPeriodId(...)` or `new AvailabilityHoldId(...)`. The `$roomId` and `$reservationId` args stay as strings.

---

## Task 13: Run full suite + deptrac + commit

- [ ] **Run unit tests**

```bash
make unit-test
```

Expected: all green.

- [ ] **Run functional tests**

```bash
make functional-test
```

Expected: all green.

- [ ] **Run deptrac**

```bash
make deptrac
```

Expected: no violations (`Shared` is accessible to all layers per `deptrac.yaml`).

- [ ] **Run PHPStan**

```bash
make static-code-analysis
```

Expected: no errors.

- [ ] **Commit Availability slice**

```bash
git add src/Shared/Domain/ValueObject/AvailabilityHoldId.php \
        src/Shared/Domain/ValueObject/BlockedPeriodId.php \
        src/Availability tests/Availability
git commit -m "refactor(availability): replace string \$id with typed VOs on AvailabilityHold and BlockedPeriod aggregates"
```

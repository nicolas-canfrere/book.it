# Shared ID Value Objects — Hotel & Operator (aggregate $id only)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace raw `string $id` on two aggregate root domain models with typed VOs: `Hotel::$id` and `Operator::$id`.

**Architecture:** Two VOs (`HotelId`, `OperatorId`) in `Shared\Domain\ValueObject\`. Only the aggregate's own identity field changes — cross-context reference IDs (`$roomId`, `$reservationId`, `$hotelId` in other contexts, etc.) stay as raw strings in this tranche. The cascade goes Domain model → Domain ports → Infrastructure → Application commands/queries → UI → serializers → tests, committed as two slices (Hotel then Operator). `Notification` context is skipped — it has no aggregate root with its own `$id`. Published contracts (`HotelView`, `OperatorView`) are **not changed** — they are cross-context APIs with `string $id`.

**Tech Stack:** PHP 8.4, Symfony 8, DBAL (no ORM). All commands via `make`. Branch `refactor/shared-id-value-objects` already exists and is active.

---

## File Map

**Create:**
- `src/Shared/Domain/ValueObject/HotelId.php`
- `src/Shared/Domain/ValueObject/OperatorId.php`

**Extend:**
- `tests/Shared/Domain/ValueObject/IdValueObjectsTest.php` — add 2 test methods

**Modify — Hotel:**
- `src/Hotel/Domain/Model/Hotel.php` — `string $id` → `HotelId`
- `src/Hotel/Domain/Port/HotelIdGeneratorInterface.php` — return `HotelId`
- `src/Hotel/Domain/Port/HotelRepositoryInterface.php` — `get(HotelId)`
- `src/Hotel/Infrastructure/Service/HotelIdGenerator.php` — return `new HotelId(...)`
- `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php` — `add()`, `save()` use `->value`; `get(HotelId)`; `hydrate()` wraps in `new HotelId`
- `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommand.php` — `HotelId $id`
- `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandler.php` — `$hotel->id->value` in event dispatch
- `src/Hotel/Application/UseCase/ClassifyHotel/ClassifyHotelCommand.php` — `HotelId $hotelId`
- `src/Hotel/Application/UseCase/ClassifyHotel/ClassifyHotelCommandHandler.php` — `->value` in exception + event
- `src/Hotel/Application/UseCase/DeclareHotelAmenities/DeclareHotelAmenitiesCommand.php` — `HotelId $hotelId`
- `src/Hotel/Application/UseCase/DeclareHotelAmenities/DeclareHotelAmenitiesCommandHandler.php` — `->value` in exception + event
- `src/Hotel/Application/UseCase/GetHotel/GetHotelQuery.php` — `HotelId $hotelId`
- `src/Hotel/UI/Http/Controller/GetHotel/GetHotelController.php` — wrap `$id` in `new HotelId`
- `src/Hotel/UI/Http/Controller/ClassifyHotel/ClassifyHotelController.php` — wrap `$id` in `new HotelId`
- `src/Hotel/UI/Http/Controller/DeclareHotelAmenities/DeclareHotelAmenitiesController.php` — wrap `$id` in `new HotelId`
- `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelController.php` — `$command->id->value` for URL generation
- `src/Hotel/UI/Http/Controller/HotelSerializer.php` — `$hotel->id->value`
- `tests/Hotel/Infrastructure/Persistence/InMemory/InMemoryHotelRepository.php` — update `add()`, `save()`, `get(HotelId)`
- `tests/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandlerTest.php`
- `tests/Hotel/Application/UseCase/DeclareHotelAmenities/DeclareHotelAmenitiesCommandHandlerTest.php`
- `tests/Hotel/Application/UseCase/ListHotels/ListHotelsQueryHandlerTest.php`
- `tests/Hotel/Infrastructure/Contract/DoctrineHotelFinderTest.php`
- `tests/Hotel/Infrastructure/Persistence/Doctrine/HotelRepositoryAmenitiesTest.php`
- `tests/Hotel/Unit/Application/UseCase/ClassifyHotel/ClassifyHotelCommandHandlerTest.php`

**Modify — Operator:**
- `src/Operator/Domain/Model/Operator.php` — `string $id` → `OperatorId`
- `src/Operator/Domain/Port/OperatorIdGeneratorInterface.php` — return `OperatorId`
- `src/Operator/Domain/Port/ExternalAccountRegistrarInterface.php` — all three methods take `OperatorId`
- `src/Operator/Infrastructure/Service/OperatorIdGenerator.php` — return `new OperatorId(...)`
- `src/Operator/Infrastructure/Persistence/Doctrine/OperatorRepository.php` — `$operator->id->value` in `add()`
- `src/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapter.php` — update signatures + use `->value`
- `src/Operator/Application/UseCase/RegisterOperator/RegisterOperatorCommand.php` — `OperatorId $id`
- `src/Operator/Application/UseCase/RegisterOperator/RegisterOperatorCommandHandler.php` — `$command->id->value` in logger calls
- `src/Operator/Application/UseCase/AssignAdminRoleToOperator/AssignAdminRoleToOperatorCommand.php` — `OperatorId $operatorId`
- `src/Operator/UI/Http/Controller/RegisterOperator/RegisterOperatorController.php` — `$command->id->value` in response
- `src/Operator/UI/Console/RegisterAdminOperatorCommand.php` — `$registerCommand->id->value` in output
- `tests/Operator/Infrastructure/Persistence/InMemory/InMemoryOperatorRepository.php`
- `tests/Operator/Infrastructure/ExternalAccount/NullExternalAccountRegistrar.php`
- `tests/Operator/Infrastructure/ExternalAccount/ThrowingExternalAccountRegistrar.php`
- `tests/Operator/Application/UseCase/RegisterOperator/RegisterOperatorCommandHandlerTest.php`
- `tests/Operator/Application/UseCase/AssignAdminRoleToOperator/AssignAdminRoleToOperatorCommandHandlerTest.php`

---

## Task 1: Create HotelId + OperatorId VOs + extend test file

- [ ] **Create HotelId.php**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class HotelId
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

- [ ] **Create OperatorId.php**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class OperatorId
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

- [ ] **Extend IdValueObjectsTest.php**

The file already has tests for `BookerId`, `AvailabilityHoldId`, `BlockedPeriodId`. Add these two methods and the two new `use` statements:

```php
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\OperatorId;

#[Test]
public function hotel_id_exposes_value_and_casts_to_string(): void
{
    $id = new HotelId(self::UUID);
    self::assertSame(self::UUID, $id->value);
    self::assertSame(self::UUID, (string) $id);
}

#[Test]
public function operator_id_exposes_value_and_casts_to_string(): void
{
    $id = new OperatorId(self::UUID);
    self::assertSame(self::UUID, $id->value);
    self::assertSame(self::UUID, (string) $id);
}
```

- [ ] **Run — expect green**

```bash
make unit-test
```

---

## Task 2: Hotel — Domain layer

- [ ] **Update Hotel.php**

Only the `$id` field changes. `withStarRating()` and `withAmenities()` both copy `$this->id` — they work unchanged once `$id` is `HotelId`.

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Model;

use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Domain\ValueObject\HotelId;

final readonly class Hotel
{
    /**
     * @param array<HotelAmenity> $amenities
     */
    public function __construct(
        public HotelId $id,
        public string $name,
        public Address $address,
        public \DateTimeImmutable $createdAt,
        public ?StarRating $starRating = null,
        public array $amenities = [],
    ) {
    }

    public function withStarRating(?StarRating $starRating): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            address: $this->address,
            createdAt: $this->createdAt,
            starRating: $starRating,
            amenities: $this->amenities,
        );
    }

    /**
     * @param array<HotelAmenity> $amenities
     */
    public function withAmenities(array $amenities): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            address: $this->address,
            createdAt: $this->createdAt,
            starRating: $this->starRating,
            amenities: $amenities,
        );
    }
}
```

- [ ] **Update HotelIdGeneratorInterface.php**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Port;

use App\Shared\Domain\ValueObject\HotelId;

interface HotelIdGeneratorInterface
{
    public function generate(): HotelId;
}
```

- [ ] **Update HotelRepositoryInterface.php**

Read the file first. Only `get()` changes; all other signatures stay as-is.

```php
use App\Shared\Domain\ValueObject\HotelId;

public function get(HotelId $id): ?Hotel;
```

---

## Task 3: Hotel — Infrastructure

- [ ] **Update HotelIdGenerator.php**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Infrastructure\Service;

use App\Hotel\Domain\Port\HotelIdGeneratorInterface;
use App\Shared\Domain\ValueObject\HotelId;
use Symfony\Component\Uid\Uuid;

final class HotelIdGenerator implements HotelIdGeneratorInterface
{
    public function generate(): HotelId
    {
        return new HotelId(Uuid::v4()->toString());
    }
}
```

- [ ] **Update HotelRepository.php**

Read the file first. Four spots change: `add()`, `save()`, `get()`, and `hydrate()`. Everything else (pagination queries, `existsByNameAndAddress`, `listByRoomId`) stays unchanged.

```php
use App\Shared\Domain\ValueObject\HotelId;

public function add(Hotel $hotel): void
{
    $this->hotelConnection->insert('hotel', [
        'id' => $hotel->id->value,
        // other fields unchanged
    ]);
}

public function save(Hotel $hotel): void
{
    $this->hotelConnection->update(
        'hotel',
        [
            // updated fields unchanged
        ],
        ['id' => $hotel->id->value],
    );
}

public function get(HotelId $id): ?Hotel
{
    $row = $this->hotelConnection->fetchAssociative(
        'SELECT id, name, street_address, postal_code, city, country, created_at, star_rating_stars, star_rating_superior FROM hotel WHERE id = :id',
        ['id' => $id->value],
    );

    if (false === $row) {
        return null;
    }

    return $this->hydrate($row);
}

// In hydrate():
private function hydrate(array $row): Hotel
{
    return new Hotel(
        new HotelId($row['id']),
        $row['name'],
        // other fields unchanged
    );
}
```

---

## Task 4: Hotel — Application layer

`RegisterHotelCommandFactory` calls `$this->hotelIdGenerator->generate()` and passes it directly to `RegisterHotelCommand` — once the generator returns `HotelId`, no factory change is needed.

- [ ] **Update RegisterHotelCommand.php**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\HotelId;

final readonly class RegisterHotelCommand implements SyncCommandInterface
{
    public function __construct(
        public HotelId $id,
        public string $name,
        public Address $address,
        public \DateTimeImmutable $createdAt,
        public ?StarRating $starRating = null,
    ) {
    }
}
```

- [ ] **Update RegisterHotelCommandHandler.php**

`HotelRegistered::$hotelId` is `string` — pass `$hotel->id->value`. The `Hotel` constructor call passes `$command->id` directly (both `HotelId` after Task 2).

```php
$this->eventDispatcher->dispatch(new HotelRegistered(
    hotelId: $hotel->id->value,
    name: $hotel->name,
    city: $hotel->address->city,
    country: $hotel->address->country,
    starRating: $hotel->starRating?->stars,
    createdAt: $hotel->createdAt,
));
```

- [ ] **Update ClassifyHotelCommand.php**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\ClassifyHotel;

use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\HotelId;

final readonly class ClassifyHotelCommand implements SyncCommandInterface
{
    public function __construct(
        public HotelId $hotelId,
        public ?StarRating $starRating,
    ) {
    }
}
```

- [ ] **Update ClassifyHotelCommandHandler.php**

Read the file first. Two changes: `HotelNotFoundException` takes `string` (pass `->value`), `StarRatingClassified` event takes `string $hotelId` (pass `->value`). The `repository->get($command->hotelId)` call now passes `HotelId` directly — no `->value` there.

```php
$hotel = $this->hotelRepository->get($command->hotelId);  // HotelId — no change

if (null === $hotel) {
    throw new HotelNotFoundException($command->hotelId->value);
}

// ... (star rating logic unchanged)

$this->eventDispatcher->dispatch(new StarRatingClassified(
    hotelId: $command->hotelId->value,
    // other fields unchanged
));
```

- [ ] **Update DeclareHotelAmenitiesCommand.php**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\DeclareHotelAmenities;

use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\HotelId;

final readonly class DeclareHotelAmenitiesCommand implements SyncCommandInterface
{
    /**
     * @param list<HotelAmenity> $amenities
     */
    public function __construct(
        public HotelId $hotelId,
        public array $amenities,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Update DeclareHotelAmenitiesCommandHandler.php**

Same pattern as ClassifyHotelCommandHandler: `->value` for exception and `HotelAmenityDeclared` event dispatch; `repository->get($command->hotelId)` passes `HotelId` directly.

```php
$hotel = $this->hotelRepository->get($command->hotelId);

if (null === $hotel) {
    throw new HotelNotFoundException($command->hotelId->value);
}

// ... (amenities logic unchanged)

$this->eventDispatcher->dispatch(new HotelAmenityDeclared(
    hotelId: $command->hotelId->value,
    // other fields unchanged
));
```

- [ ] **Update GetHotelQuery.php**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\GetHotel;

use App\Hotel\Domain\Model\Hotel;
use App\Shared\Application\Bus\SyncQueryInterface;
use App\Shared\Domain\ValueObject\HotelId;

/** @implements SyncQueryInterface<Hotel|null> */
final readonly class GetHotelQuery implements SyncQueryInterface
{
    public function __construct(public HotelId $hotelId)
    {
    }
}
```

(`GetHotelQueryHandler` passes `$query->hotelId` to `HotelRepositoryInterface::get()` — both now `HotelId`, no change needed.)

---

## Task 5: Hotel — UI + Serializer

- [ ] **Update GetHotelController.php**

```php
use App\Shared\Domain\ValueObject\HotelId;

public function __invoke(string $id): Response
{
    $hotel = $this->queryBus->ask(new GetHotelQuery(new HotelId($id)));
    // rest unchanged
}
```

- [ ] **Update ClassifyHotelController.php**

Read the file first. The route `__invoke(string $id, ...)` passes `$id` as `hotelId:` to the command:

```php
use App\Shared\Domain\ValueObject\HotelId;

// in __invoke:
$command = new ClassifyHotelCommand(
    hotelId: new HotelId($id),
    // other args unchanged
);
```

- [ ] **Update DeclareHotelAmenitiesController.php**

Same pattern as ClassifyHotelController:

```php
use App\Shared\Domain\ValueObject\HotelId;

// in __invoke:
$command = new DeclareHotelAmenitiesCommand(
    hotelId: new HotelId($id),
    // other args unchanged
);
```

- [ ] **Update RegisterHotelController.php**

Read the file first. Two spots change: `GetHotelQuery($command->id)` stays as-is (both `HotelId`). The URL generator receives `$command->id` as a route param — use `->value` to ensure a plain string:

```php
['Location' => $this->urlGenerator->generate('hotel_get_hotel', ['id' => $command->id->value])]
```

- [ ] **Update HotelSerializer.php**

```php
return [
    'id' => $hotel->id->value,
    // remaining fields unchanged (all strings)
];
```

---

## Task 6: Hotel — Update tests

- [ ] **Update InMemoryHotelRepository.php**

```php
use App\Shared\Domain\ValueObject\HotelId;

public function add(Hotel $hotel): void
{
    $this->hotels[$hotel->id->value] = $hotel;
}

public function save(Hotel $hotel): void
{
    $this->hotels[$hotel->id->value] = $hotel;
}

public function get(HotelId $id): ?Hotel
{
    return $this->hotels[$id->value] ?? null;
}
```

- [ ] **Scan and update all Hotel unit tests that build commands or models with raw string `$id`**

```bash
grep -rln "new Hotel(\|new RegisterHotelCommand(\|new ClassifyHotelCommand(\|new DeclareHotelAmenitiesCommand(\|new GetHotelQuery(" \
  tests/Hotel --include="*.php"
```

Files found (verify list matches): `RegisterHotelCommandHandlerTest.php`, `ClassifyHotelCommandHandlerTest.php`, `DeclareHotelAmenitiesCommandHandlerTest.php`, `ListHotelsQueryHandlerTest.php`, `DoctrineHotelFinderTest.php`, `HotelRepositoryAmenitiesTest.php`.

In each file:
- `new Hotel('some-uuid', ...)` → `new Hotel(new HotelId('some-uuid'), ...)`
- `new RegisterHotelCommand('some-uuid', ...)` → `new RegisterHotelCommand(new HotelId('some-uuid'), ...)`
- `new ClassifyHotelCommand('some-uuid', ...)` → `new ClassifyHotelCommand(new HotelId('some-uuid'), ...)`
- `new DeclareHotelAmenitiesCommand('some-uuid', ...)` → `new DeclareHotelAmenitiesCommand(new HotelId('some-uuid'), ...)`
- `new GetHotelQuery('some-uuid')` → `new GetHotelQuery(new HotelId('some-uuid'))`
- Add `use App\Shared\Domain\ValueObject\HotelId;` to each file's imports.

---

## Task 7: Run Hotel slice + commit

- [ ] **Run tests**

```bash
make unit-test && make functional-test
```

Expected: all green.

- [ ] **Commit**

```bash
git add src/Shared/Domain/ValueObject/HotelId.php \
        tests/Shared/Domain/ValueObject/IdValueObjectsTest.php \
        src/Hotel tests/Hotel
git commit -m "refactor(hotel): replace string \$id with HotelId VO on Hotel aggregate"
```

---

## Task 8: Operator — Domain layer

- [ ] **Update Operator.php**

```php
<?php

declare(strict_types=1);

namespace App\Operator\Domain\Model;

use App\Shared\Domain\ValueObject\OperatorId;

final readonly class Operator
{
    public function __construct(
        public OperatorId $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
```

- [ ] **Update OperatorIdGeneratorInterface.php**

```php
<?php

declare(strict_types=1);

namespace App\Operator\Domain\Port;

use App\Shared\Domain\ValueObject\OperatorId;

interface OperatorIdGeneratorInterface
{
    public function generate(): OperatorId;
}
```

- [ ] **Update ExternalAccountRegistrarInterface.php**

All three methods change parameter type. `assignAdminRole` is Operator-specific (no Booker equivalent).

```php
<?php

declare(strict_types=1);

namespace App\Operator\Domain\Port;

use App\Shared\Domain\ValueObject\OperatorId;

interface ExternalAccountRegistrarInterface
{
    public function register(OperatorId $operatorId, string $email, string $password): void;

    public function unregister(OperatorId $operatorId): void;

    public function assignAdminRole(OperatorId $operatorId): void;
}
```

(`OperatorRepositoryInterface` only has `add(Operator)` and `existsByEmail(string)` — no `get(string $id)` method exists, nothing to change.)

---

## Task 9: Operator — Infrastructure

- [ ] **Update OperatorIdGenerator.php**

```php
<?php

declare(strict_types=1);

namespace App\Operator\Infrastructure\Service;

use App\Operator\Domain\Port\OperatorIdGeneratorInterface;
use App\Shared\Domain\ValueObject\OperatorId;
use Symfony\Component\Uid\Uuid;

final class OperatorIdGenerator implements OperatorIdGeneratorInterface
{
    public function generate(): OperatorId
    {
        return new OperatorId(Uuid::v4()->toString());
    }
}
```

- [ ] **Update OperatorRepository.php**

Read the file first. Only `add()` changes — `$operator->id` → `$operator->id->value`.

```php
public function add(Operator $operator): void
{
    $this->operatorConnection->insert('operator', [
        'id' => $operator->id->value,
        // other fields unchanged
    ]);
}
```

- [ ] **Update SecurityAccountRegistrarAdapter.php**

Read the file first. Update all three method signatures to `OperatorId` and replace bare `$operatorId` usages as string with `$operatorId->value`.

```php
use App\Shared\Domain\ValueObject\OperatorId;

public function register(OperatorId $operatorId, string $email, string $password): void
{
    // replace all usages of $operatorId as string with $operatorId->value
}

public function unregister(OperatorId $operatorId): void
{
    // replace all usages of $operatorId as string with $operatorId->value
}

public function assignAdminRole(OperatorId $operatorId): void
{
    // replace all usages of $operatorId as string with $operatorId->value
}
```

---

## Task 10: Operator — Application layer

`RegisterOperatorCommandFactory` calls `$this->operatorIdGenerator->generate()` and passes it directly to `RegisterOperatorCommand` — once the generator returns `OperatorId`, no factory change is needed.

- [ ] **Update RegisterOperatorCommand.php**

```php
<?php

declare(strict_types=1);

namespace App\Operator\Application\UseCase\RegisterOperator;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\OperatorId;

final readonly class RegisterOperatorCommand implements SyncCommandInterface
{
    public function __construct(
        public OperatorId $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public string $password,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
```

- [ ] **Update RegisterOperatorCommandHandler.php**

Read the file first. The `register()`, `unregister()`, and `new Operator(...)` calls already pass `$command->id` (which is now `OperatorId`) — no change there. Only the two logger calls use `$command->id` as a string value:

```php
// logger error call:
$this->logger->error('...', [
    'operator_id' => $command->id->value,
    // other fields unchanged
]);

// logger info call:
$this->logger->info('...', [
    'operator_id' => $command->id->value,
    // other fields unchanged
]);
```

- [ ] **Update AssignAdminRoleToOperatorCommand.php**

```php
<?php

declare(strict_types=1);

namespace App\Operator\Application\UseCase\AssignAdminRoleToOperator;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\OperatorId;

final readonly class AssignAdminRoleToOperatorCommand implements SyncCommandInterface
{
    public function __construct(public OperatorId $operatorId)
    {
    }
}
```

(`AssignAdminRoleToOperatorCommandHandler` passes `$command->operatorId` to `accountRegistrar->assignAdminRole()` — both now `OperatorId`, no change needed.)

---

## Task 11: Operator — UI + Console

- [ ] **Update RegisterOperatorController.php**

Read the file first. The response body contains `'id' => $command->id` — use `->value`:

```php
return new JsonResponse([
    'id' => $command->id->value,
    // other fields unchanged
], Response::HTTP_CREATED);
```

- [ ] **Update RegisterAdminOperatorCommand.php** (console command)

Read the file first. Two spots: the output string interpolation uses `$registerCommand->id` as a string (use `->value`); the `AssignAdminRoleToOperatorCommand` call passes `$registerCommand->id` which is now `OperatorId` — types match, no change there.

```php
// line that outputs the registered operator id:
$output->writeln(sprintf(
    '<info>Admin operator "%s" registered with id %s</info>',
    $registerCommand->email,
    $registerCommand->id->value,  // was: $registerCommand->id
));
```

---

## Task 12: Operator — Update tests

- [ ] **Update InMemoryOperatorRepository.php**

Read the file first. Only `add()` uses `$operator->id` — update to `$operator->id->value`. The repository has no `get(string $id)` method.

```php
public function add(Operator $operator): void
{
    $this->operators[$operator->id->value] = $operator;
}
```

- [ ] **Update NullExternalAccountRegistrar.php**

```php
use App\Shared\Domain\ValueObject\OperatorId;

public function register(OperatorId $operatorId, string $email, string $password): void {}

public function unregister(OperatorId $operatorId): void {}

public function assignAdminRole(OperatorId $operatorId): void {}
```

- [ ] **Update ThrowingExternalAccountRegistrar.php**

```php
use App\Shared\Domain\ValueObject\OperatorId;

public function register(OperatorId $operatorId, string $email, string $password): void
{
    throw new ExternalAccountCreationException($email, new \RuntimeException('Keycloak unavailable'));
}

public function unregister(OperatorId $operatorId): void {}

public function assignAdminRole(OperatorId $operatorId): void {}
```

- [ ] **Scan and update all Operator tests that build commands or models with raw string `$id`**

```bash
grep -rln "new Operator(\|new RegisterOperatorCommand(\|new AssignAdminRoleToOperatorCommand(" \
  tests/Operator --include="*.php"
```

Files found: `RegisterOperatorCommandHandlerTest.php`, `AssignAdminRoleToOperatorCommandHandlerTest.php`.

In each file, wrap the first positional `$id` or `$operatorId` arg in `new OperatorId(...)`. Update mock assertions for `register`, `unregister`, and `assignAdminRole`:

```php
use App\Shared\Domain\ValueObject\OperatorId;

// in makeCommand() or equivalent helper:
new RegisterOperatorCommand(
    new OperatorId('uuid-1'),
    'Jean', 'Dupont', 'jean@example.com', '+33612345678',
    'password123',
    new \DateTimeImmutable('2025-01-01'),
)

// mock assertion example:
$this->accountRegistrar->expects(self::once())
    ->method('register')
    ->with(new OperatorId('uuid-1'), 'jean@example.com', 'password123');

// AssignAdminRoleToOperatorCommandHandlerTest:
new AssignAdminRoleToOperatorCommand(new OperatorId('uuid-1'))

$this->accountRegistrar->expects(self::once())
    ->method('assignAdminRole')
    ->with(new OperatorId('uuid-1'));
```

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

- [ ] **Commit Operator slice**

```bash
git add src/Shared/Domain/ValueObject/OperatorId.php \
        src/Operator tests/Operator
git commit -m "refactor(operator): replace string \$id with OperatorId VO on Operator aggregate"
```

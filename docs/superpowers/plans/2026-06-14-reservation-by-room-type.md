# Reservation by Room Type Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace `roomId` with `roomTypeId` in `POST /api/v1/reservations` so the Booker selects a room type and the system picks an available physical room internally.

**Architecture:** The `CreateReservationCommand` will carry a `RoomTypeId` instead of a `RoomId`. A new `AvailableRoomPickerInterface` domain port (implemented in Reservation infrastructure) resolves a physical `RoomId` by querying all rooms of the type (via a new Room published contract `RoomsByTypeFinderInterface`) and checking availability for each. The handler then proceeds with the resolved `RoomId` as before — the `Reservation` aggregate is unchanged.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL, PHPUnit, deptrac

---

## File Map

### New files
- `src/Room/Application/Contract/RoomsByTypeFinderInterface.php` — Room published contract: find all `RoomId[]` for a given `RoomTypeId`
- `src/Room/Infrastructure/Contract/DoctrineRoomsByTypeFinder.php` — DBAL implementation of the above
- `src/Reservation/Domain/Port/AvailableRoomPickerInterface.php` — Reservation domain port: pick the first available room of a type for a period
- `src/Reservation/Infrastructure/Service/AvailableRoomPicker.php` — Combines `RoomsByTypeFinderInterface` + `RoomAvailabilityCheckerInterface` to resolve a `RoomId`

### Modified files
- `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommand.php` — `RoomId $roomId` → `RoomTypeId $roomTypeId`
- `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php` — replace `RoomExistsInterface` with `AvailableRoomPickerInterface`; resolve `roomId` at the top of `__invoke`
- `src/Reservation/Application/Service/CreateReservationCommandFactory.php` — `string $roomId` / `new RoomId(...)` → `string $roomTypeId` / `new RoomTypeId(...)`
- `src/Reservation/Domain/Exception/RoomNotAvailableException.php` — accept `RoomTypeId` instead of `RoomId`
- `src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationRequest.php` — `$roomId` → `$roomTypeId`
- `src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationController.php` — pass `$request->roomTypeId` to factory
- `config/services/reservation.yaml` — register `AvailableRoomPicker` alias
- `deptrac-contexts.yaml` — allow `Reservation\Infrastructure` to import `Room\Application\Contract\RoomsByTypeFinderInterface`

### Deleted files
- `src/Reservation/Domain/Port/RoomExistsInterface.php` — dead code after handler refactor
- `src/Reservation/Infrastructure/Service/RoomExistenceChecker.php` — dead code after handler refactor

### Test files
- `tests/Room/Infrastructure/Contract/DoctrineRoomsByTypeFinderTest.php` — NEW unit
- `tests/Reservation/Infrastructure/Service/AvailableRoomPickerTest.php` — NEW unit
- `tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php` — MODIFY
- `tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php` — MODIFY

---

## Task 1: Room published contract — `RoomsByTypeFinderInterface` + `DoctrineRoomsByTypeFinder`

**Files:**
- Create: `src/Room/Application/Contract/RoomsByTypeFinderInterface.php`
- Create: `src/Room/Infrastructure/Contract/DoctrineRoomsByTypeFinder.php`
- Create: `tests/Room/Infrastructure/Contract/DoctrineRoomsByTypeFinderTest.php`
- Modify: `deptrac-contexts.yaml`

- [ ] **Step 1.1 — Write the failing unit test**

```php
// tests/Room/Infrastructure/Contract/DoctrineRoomsByTypeFinderTest.php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Infrastructure\Contract;

use App\Room\Infrastructure\Contract\DoctrineRoomsByTypeFinder;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineRoomsByTypeFinderTest extends TestCase
{
    public function testReturnsRoomIdsForGivenType(): void
    {
        $roomTypeId = new RoomTypeId('aaaaaaaa-0000-4000-8000-000000000001');
        $roomId1 = 'bbbbbbbb-0000-4000-8000-000000000001';
        $roomId2 = 'bbbbbbbb-0000-4000-8000-000000000002';

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                'SELECT id FROM rooms WHERE room_type_id = :roomTypeId',
                ['roomTypeId' => $roomTypeId->value],
            )
            ->willReturn([['id' => $roomId1], ['id' => $roomId2]]);

        $finder = new DoctrineRoomsByTypeFinder($connection);
        $result = $finder->findByType($roomTypeId);

        $this->assertCount(2, $result);
        $this->assertEquals(new RoomId($roomId1), $result[0]);
        $this->assertEquals(new RoomId($roomId2), $result[1]);
    }

    public function testReturnsEmptyArrayWhenNoRoomsForType(): void
    {
        $roomTypeId = new RoomTypeId('aaaaaaaa-0000-4000-8000-000000000001');

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $finder = new DoctrineRoomsByTypeFinder($connection);
        $result = $finder->findByType($roomTypeId);

        $this->assertSame([], $result);
    }
}
```

- [ ] **Step 1.2 — Run the test to confirm it fails**

```bash
docker compose exec php bin/phpunit tests/Room/Infrastructure/Contract/DoctrineRoomsByTypeFinderTest.php --testdox
```

Expected: error — class `DoctrineRoomsByTypeFinder` not found.

- [ ] **Step 1.3 — Create `RoomsByTypeFinderInterface`**

```php
// src/Room/Application/Contract/RoomsByTypeFinderInterface.php
<?php

declare(strict_types=1);

namespace App\Room\Application\Contract;

use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;

interface RoomsByTypeFinderInterface
{
    /** @return RoomId[] */
    public function findByType(RoomTypeId $roomTypeId): array;
}
```

- [ ] **Step 1.4 — Create `DoctrineRoomsByTypeFinder`**

The constructor parameter is named `$roomConnection` — Symfony autowires it to `doctrine.dbal.room_connection` automatically (no explicit YAML wiring needed).

```php
// src/Room/Infrastructure/Contract/DoctrineRoomsByTypeFinder.php
<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Contract;

use App\Room\Application\Contract\RoomsByTypeFinderInterface;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use Doctrine\DBAL\Connection;

final readonly class DoctrineRoomsByTypeFinder implements RoomsByTypeFinderInterface
{
    public function __construct(private Connection $roomConnection)
    {
    }

    public function findByType(RoomTypeId $roomTypeId): array
    {
        $rows = $this->roomConnection->fetchAllAssociative(
            'SELECT id FROM rooms WHERE room_type_id = :roomTypeId',
            ['roomTypeId' => $roomTypeId->value],
        );

        return array_map(
            static fn(array $row) => new RoomId($row['id']),
            $rows,
        );
    }
}
```

- [ ] **Step 1.5 — Run the test to confirm it passes**

```bash
docker compose exec php bin/phpunit tests/Room/Infrastructure/Contract/DoctrineRoomsByTypeFinderTest.php --testdox
```

Expected: 2 tests, 2 assertions, OK.

- [ ] **Step 1.6 — Update `deptrac-contexts.yaml`**

In `deptrac-contexts.yaml`, find the existing allowlist entry that permits `App\Room\Application\Contract\RoomFinderInterface` to be used by `Reservation\Infrastructure`. Add `RoomsByTypeFinderInterface` following the identical pattern. Also ensure `RoomsByTypeFinderInterface` is declared in the Room Contract layer definition alongside `RoomFinderInterface`.

- [ ] **Step 1.7 — Run deptrac to verify no violations**

```bash
docker compose exec php make deptrac
```

Expected: no violations.

- [ ] **Step 1.8 — Commit**

```bash
git add src/Room/Application/Contract/RoomsByTypeFinderInterface.php \
        src/Room/Infrastructure/Contract/DoctrineRoomsByTypeFinder.php \
        tests/Room/Infrastructure/Contract/DoctrineRoomsByTypeFinderTest.php \
        deptrac-contexts.yaml
git commit -m "feat(room): publish RoomsByTypeFinderInterface contract + Doctrine implementation"
```

---

## Task 2: Reservation picker — `AvailableRoomPickerInterface` + `AvailableRoomPicker`

**Files:**
- Create: `src/Reservation/Domain/Port/AvailableRoomPickerInterface.php`
- Create: `src/Reservation/Infrastructure/Service/AvailableRoomPicker.php`
- Create: `tests/Reservation/Infrastructure/Service/AvailableRoomPickerTest.php`
- Modify: `config/services/reservation.yaml`

- [ ] **Step 2.1 — Write the failing unit test**

`RoomAvailabilityCheckerInterface` signature (already in Reservation domain): `isAvailable(RoomId $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool`

```php
// tests/Reservation/Infrastructure/Service/AvailableRoomPickerTest.php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;
use App\Reservation\Infrastructure\Service\AvailableRoomPicker;
use App\Room\Application\Contract\RoomsByTypeFinderInterface;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AvailableRoomPickerTest extends TestCase
{
    private RoomTypeId $roomTypeId;
    private \DateTimeImmutable $checkIn;
    private \DateTimeImmutable $checkOut;

    protected function setUp(): void
    {
        $this->roomTypeId = new RoomTypeId('aaaaaaaa-0000-4000-8000-000000000001');
        $this->checkIn = new \DateTimeImmutable('2026-08-01');
        $this->checkOut = new \DateTimeImmutable('2026-08-05');
    }

    public function testReturnsFirstAvailableRoom(): void
    {
        $roomId1 = new RoomId('bbbbbbbb-0000-4000-8000-000000000001');
        $roomId2 = new RoomId('bbbbbbbb-0000-4000-8000-000000000002');

        $roomsByTypeFinder = $this->createMock(RoomsByTypeFinderInterface::class);
        $roomsByTypeFinder->method('findByType')->willReturn([$roomId1, $roomId2]);

        $availabilityChecker = $this->createMock(RoomAvailabilityCheckerInterface::class);
        $availabilityChecker
            ->method('isAvailable')
            ->willReturnMap([
                [$roomId1, $this->checkIn, $this->checkOut, false],
                [$roomId2, $this->checkIn, $this->checkOut, true],
            ]);

        $picker = new AvailableRoomPicker($roomsByTypeFinder, $availabilityChecker);
        $result = $picker->pick($this->roomTypeId, $this->checkIn, $this->checkOut);

        $this->assertEquals($roomId2, $result);
    }

    public function testReturnsNullWhenNoRoomAvailable(): void
    {
        $roomId = new RoomId('bbbbbbbb-0000-4000-8000-000000000001');

        $roomsByTypeFinder = $this->createMock(RoomsByTypeFinderInterface::class);
        $roomsByTypeFinder->method('findByType')->willReturn([$roomId]);

        $availabilityChecker = $this->createMock(RoomAvailabilityCheckerInterface::class);
        $availabilityChecker->method('isAvailable')->willReturn(false);

        $picker = new AvailableRoomPicker($roomsByTypeFinder, $availabilityChecker);
        $result = $picker->pick($this->roomTypeId, $this->checkIn, $this->checkOut);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenRoomTypeHasNoRooms(): void
    {
        $roomsByTypeFinder = $this->createMock(RoomsByTypeFinderInterface::class);
        $roomsByTypeFinder->method('findByType')->willReturn([]);

        $availabilityChecker = $this->createMock(RoomAvailabilityCheckerInterface::class);
        $availabilityChecker->expects($this->never())->method('isAvailable');

        $picker = new AvailableRoomPicker($roomsByTypeFinder, $availabilityChecker);
        $result = $picker->pick($this->roomTypeId, $this->checkIn, $this->checkOut);

        $this->assertNull($result);
    }
}
```

- [ ] **Step 2.2 — Run the test to confirm it fails**

```bash
docker compose exec php bin/phpunit tests/Reservation/Infrastructure/Service/AvailableRoomPickerTest.php --testdox
```

Expected: error — class `AvailableRoomPicker` not found.

- [ ] **Step 2.3 — Create `AvailableRoomPickerInterface`**

```php
// src/Reservation/Domain/Port/AvailableRoomPickerInterface.php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;

interface AvailableRoomPickerInterface
{
    public function pick(RoomTypeId $roomTypeId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): ?RoomId;
}
```

- [ ] **Step 2.4 — Create `AvailableRoomPicker`**

```php
// src/Reservation/Infrastructure/Service/AvailableRoomPicker.php
<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\AvailableRoomPickerInterface;
use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;
use App\Room\Application\Contract\RoomsByTypeFinderInterface;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;

final readonly class AvailableRoomPicker implements AvailableRoomPickerInterface
{
    public function __construct(
        private RoomsByTypeFinderInterface $roomsByTypeFinder,
        private RoomAvailabilityCheckerInterface $availabilityChecker,
    ) {
    }

    public function pick(RoomTypeId $roomTypeId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): ?RoomId
    {
        foreach ($this->roomsByTypeFinder->findByType($roomTypeId) as $roomId) {
            if ($this->availabilityChecker->isAvailable($roomId, $checkIn, $checkOut)) {
                return $roomId;
            }
        }

        return null;
    }
}
```

- [ ] **Step 2.5 — Run the test to confirm it passes**

```bash
docker compose exec php bin/phpunit tests/Reservation/Infrastructure/Service/AvailableRoomPickerTest.php --testdox
```

Expected: 3 tests, OK.

- [ ] **Step 2.6 — Register the alias in `config/services/reservation.yaml`**

Add the interface-to-implementation alias so Symfony injects `AvailableRoomPicker` wherever `AvailableRoomPickerInterface` is type-hinted:

```yaml
App\Reservation\Domain\Port\AvailableRoomPickerInterface: '@App\Reservation\Infrastructure\Service\AvailableRoomPicker'
```

- [ ] **Step 2.7 — Verify the container compiles**

```bash
docker compose exec php bin/console cache:clear
```

Expected: no errors.

- [ ] **Step 2.8 — Commit**

```bash
git add src/Reservation/Domain/Port/AvailableRoomPickerInterface.php \
        src/Reservation/Infrastructure/Service/AvailableRoomPicker.php \
        tests/Reservation/Infrastructure/Service/AvailableRoomPickerTest.php \
        config/services/reservation.yaml
git commit -m "feat(reservation): add AvailableRoomPickerInterface + AvailableRoomPicker infra service"
```

---

## Task 3: Update command, factory, handler, exception — remove dead code

**Files:**
- Modify: `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommand.php`
- Modify: `src/Reservation/Application/Service/CreateReservationCommandFactory.php`
- Modify: `src/Reservation/Domain/Exception/RoomNotAvailableException.php`
- Modify: `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php`
- Modify: `tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php`
- Delete: `src/Reservation/Domain/Port/RoomExistsInterface.php`
- Delete: `src/Reservation/Infrastructure/Service/RoomExistenceChecker.php`

- [ ] **Step 3.1 — Update the exception**

```php
// src/Reservation/Domain/Exception/RoomNotAvailableException.php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

use App\Shared\Domain\ValueObject\RoomTypeId;

final class RoomNotAvailableException extends \DomainException
{
    public function __construct(RoomTypeId $roomTypeId)
    {
        parent::__construct(sprintf('No room available for type "%s" on the requested period.', $roomTypeId->value));
    }
}
```

- [ ] **Step 3.2 — Update `CreateReservationCommand`**

```php
// src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommand.php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CreateReservation;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\RoomTypeId;

final readonly class CreateReservationCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public RoomTypeId $roomTypeId,
        public string $bookerId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $guestCount,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 3.3 — Update `CreateReservationCommandFactory`**

```php
// src/Reservation/Application/Service/CreateReservationCommandFactory.php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\Service;

use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommand;
use App\Reservation\Domain\Port\ReservationIdGeneratorInterface;
use App\Shared\Domain\ValueObject\RoomTypeId;

final readonly class CreateReservationCommandFactory
{
    public function __construct(private ReservationIdGeneratorInterface $idGenerator)
    {
    }

    public function create(
        string $roomTypeId,
        string $bookerId,
        string $checkIn,
        string $checkOut,
        int $guestCount,
    ): CreateReservationCommand {
        return new CreateReservationCommand(
            id: $this->idGenerator->generate(),
            roomTypeId: new RoomTypeId($roomTypeId),
            bookerId: $bookerId,
            checkIn: new \DateTimeImmutable($checkIn),
            checkOut: new \DateTimeImmutable($checkOut),
            guestCount: $guestCount,
            createdAt: new \DateTimeImmutable(),
        );
    }
}
```

- [ ] **Step 3.4 — Update the handler integration test (make it fail first)**

Open `tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php`.

Replace the `RoomExistsInterface` stub with an `AvailableRoomPickerInterface` stub, and update the command construction to use `roomTypeId`. The resolved `$roomId` is what the picker returns, and all downstream calls (capacity, pricing, cancellation) still receive it.

The full updated test file:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Application\UseCase\CreateReservation;

use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommand;
use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommandHandler;
use App\Reservation\Domain\Exception\BookerNotFoundException;
use App\Reservation\Domain\Exception\GuestCapacityExceededException;
use App\Reservation\Domain\Exception\RoomNotAvailableException;
use App\Reservation\Domain\Port\AvailableRoomPickerInterface;
use App\Reservation\Domain\Port\BookerExistsInterface;
use App\Reservation\Domain\Port\CancellationPolicyFetcherInterface;
use App\Reservation\Domain\Port\PricingQuoteFetcherInterface;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\PricingQuote;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Application\Transaction\TransactionManagerInterface;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class CreateReservationCommandHandlerTest extends TestCase
{
    private AvailableRoomPickerInterface&\PHPUnit\Framework\MockObject\MockObject $roomPicker;
    private BookerExistsInterface&\PHPUnit\Framework\MockObject\MockObject $bookerExists;
    private ReservationRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository;
    private PricingQuoteFetcherInterface&\PHPUnit\Framework\MockObject\MockObject $pricingQuoteFetcher;
    private CancellationPolicyFetcherInterface&\PHPUnit\Framework\MockObject\MockObject $cancellationPolicyFetcher;
    private EventDispatcherInterface&\PHPUnit\Framework\MockObject\MockObject $eventDispatcher;
    private AsyncCommandDispatcherInterface&\PHPUnit\Framework\MockObject\MockObject $asyncDispatcher;
    private CreateReservationCommandHandler $handler;

    private RoomId $resolvedRoomId;
    private RoomTypeId $roomTypeId;

    protected function setUp(): void
    {
        $this->resolvedRoomId = new RoomId('bbbbbbbb-0000-4000-8000-000000000001');
        $this->roomTypeId = new RoomTypeId('aaaaaaaa-0000-4000-8000-000000000001');

        $this->roomPicker = $this->createMock(AvailableRoomPickerInterface::class);
        $this->bookerExists = $this->createMock(BookerExistsInterface::class);
        $this->repository = $this->createMock(ReservationRepositoryInterface::class);

        $roomCapacityFetcher = $this->createMock(\App\Reservation\Domain\Port\RoomCapacityFetcherInterface::class);
        $roomCapacityFetcher->method('fetchCapacity')->willReturn(4);

        $this->pricingQuoteFetcher = $this->createMock(PricingQuoteFetcherInterface::class);
        $this->pricingQuoteFetcher->method('fetch')->willReturn(new PricingQuote(10000, []));

        $this->cancellationPolicyFetcher = $this->createMock(CancellationPolicyFetcherInterface::class);
        $this->cancellationPolicyFetcher->method('fetch')->willReturn(new CancellationTerms(null));

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->asyncDispatcher = $this->createMock(AsyncCommandDispatcherInterface::class);

        $transactionManager = $this->createMock(TransactionManagerInterface::class);
        $transactionManager->method('transactional')->willReturnCallback(static fn(\Closure $fn) => $fn());

        $this->handler = new CreateReservationCommandHandler(
            repository: $this->repository,
            roomPicker: $this->roomPicker,
            bookerExists: $this->bookerExists,
            roomCapacityFetcher: $roomCapacityFetcher,
            pricingQuoteFetcher: $this->pricingQuoteFetcher,
            cancellationPolicyFetcher: $this->cancellationPolicyFetcher,
            eventDispatcher: $this->eventDispatcher,
            transactionManager: $transactionManager,
            asyncDispatcher: $this->asyncDispatcher,
        );
    }

    private function makeCommand(int $guestCount = 2): CreateReservationCommand
    {
        return new CreateReservationCommand(
            id: 'cccccccc-0000-4000-8000-000000000001',
            roomTypeId: $this->roomTypeId,
            bookerId: 'dddddddd-0000-4000-8000-000000000001',
            checkIn: new \DateTimeImmutable('2026-08-01'),
            checkOut: new \DateTimeImmutable('2026-08-05'),
            guestCount: $guestCount,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function testCreatesReservationSuccessfully(): void
    {
        $this->roomPicker->method('pick')->willReturn($this->resolvedRoomId);
        $this->bookerExists->method('exists')->willReturn(true);
        $this->repository->expects($this->once())->method('add');
        $this->eventDispatcher->expects($this->once())->method('dispatch');
        $this->asyncDispatcher->expects($this->once())->method('dispatch');

        ($this->handler)($this->makeCommand());
    }

    public function testThrowsWhenNoRoomAvailable(): void
    {
        $this->roomPicker->method('pick')->willReturn(null);

        $this->expectException(RoomNotAvailableException::class);
        ($this->handler)($this->makeCommand());
    }

    public function testThrowsWhenBookerDoesNotExist(): void
    {
        $this->roomPicker->method('pick')->willReturn($this->resolvedRoomId);
        $this->bookerExists->method('exists')->willReturn(false);

        $this->expectException(BookerNotFoundException::class);
        ($this->handler)($this->makeCommand());
    }

    public function testThrowsWhenGuestCountExceedsCapacity(): void
    {
        $this->roomPicker->method('pick')->willReturn($this->resolvedRoomId);
        $this->bookerExists->method('exists')->willReturn(true);

        $this->expectException(GuestCapacityExceededException::class);
        ($this->handler)($this->makeCommand(guestCount: 99));
    }
}
```

- [ ] **Step 3.5 — Run the test to confirm it fails**

```bash
docker compose exec php bin/phpunit tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php --testdox
```

Expected: FAIL — `CreateReservationCommandHandler` constructor does not match yet.

- [ ] **Step 3.6 — Update `CreateReservationCommandHandler`**

```php
// src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CreateReservation;

use App\Reservation\Application\UseCase\ExpireReservation\ExpireReservationCommand;
use App\Reservation\Domain\Exception\BookerNotFoundException;
use App\Reservation\Domain\Exception\GuestCapacityExceededException;
use App\Reservation\Domain\Exception\RoomNotAvailableException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Port\AvailableRoomPickerInterface;
use App\Reservation\Domain\Port\BookerExistsInterface;
use App\Reservation\Domain\Port\CancellationPolicyFetcherInterface;
use App\Reservation\Domain\Port\PricingQuoteFetcherInterface;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\Port\RoomCapacityFetcherInterface;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Application\Transaction\TransactionManagerInterface;
use App\Shared\Domain\Event\ReservationCreated;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class CreateReservationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private AvailableRoomPickerInterface $roomPicker,
        private BookerExistsInterface $bookerExists,
        private RoomCapacityFetcherInterface $roomCapacityFetcher,
        private PricingQuoteFetcherInterface $pricingQuoteFetcher,
        private CancellationPolicyFetcherInterface $cancellationPolicyFetcher,
        private EventDispatcherInterface $eventDispatcher,
        private TransactionManagerInterface $transactionManager,
        private AsyncCommandDispatcherInterface $asyncDispatcher,
    ) {
    }

    public function __invoke(CreateReservationCommand $command): void
    {
        $roomId = $this->roomPicker->pick($command->roomTypeId, $command->checkIn, $command->checkOut);

        if (null === $roomId) {
            throw new RoomNotAvailableException($command->roomTypeId);
        }

        if (!$this->bookerExists->exists($command->bookerId)) {
            throw new BookerNotFoundException($command->bookerId);
        }

        $capacity = $this->roomCapacityFetcher->fetchCapacity($roomId);
        if ($command->guestCount > $capacity) {
            throw new GuestCapacityExceededException($command->guestCount, $capacity);
        }

        $pricingQuote = $this->pricingQuoteFetcher->fetch($roomId, $command->checkIn, $command->checkOut);
        $cancellationTerms = $this->cancellationPolicyFetcher->fetch($roomId);

        $reservation = new Reservation(
            id: $command->id,
            roomId: $roomId,
            bookerId: $command->bookerId,
            period: new DatePeriod($command->checkIn, $command->checkOut),
            totalPrice: $pricingQuote->totalAmountCents,
            cancellationTerms: $cancellationTerms,
            priceBreakdown: $pricingQuote->breakdown,
            guestCount: new GuestCount($command->guestCount),
            createdAt: $command->createdAt,
        );

        $this->transactionManager->transactional(function () use ($reservation): void {
            $this->repository->add($reservation);

            $this->eventDispatcher->dispatch(new ReservationCreated(
                reservationId: $reservation->id,
                roomId: $reservation->roomId->value,
                bookerId: $reservation->bookerId,
                checkIn: $reservation->period->checkIn,
                checkOut: $reservation->period->checkOut,
                totalPrice: $reservation->totalPrice,
                cancellationTermsDaysThreshold: $reservation->cancellationTerms->daysThreshold,
                priceBreakdown: $reservation->priceBreakdown->toArray(),
            ));
        });

        $this->asyncDispatcher->dispatch(
            new ExpireReservationCommand($reservation->id),
            900_000,
        );
    }
}
```

Note: `RoomAvailabilityCheckerInterface` is no longer a direct dependency of the handler — `AvailableRoomPicker` receives it via DI. The import and constructor parameter are fully removed.

- [ ] **Step 3.7 — Run the handler test to confirm it passes**

```bash
docker compose exec php bin/phpunit tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php --testdox
```

Expected: all tests pass.

- [ ] **Step 3.8 — Delete dead code**

```bash
rm src/Reservation/Domain/Port/RoomExistsInterface.php
rm src/Reservation/Infrastructure/Service/RoomExistenceChecker.php
```

Also remove any `RoomExistsInterface` and `RoomExistenceChecker` references from `config/services/reservation.yaml` if they are explicitly listed there.

- [ ] **Step 3.9 — Run static analysis and unit test suite**

```bash
docker compose exec php make static-code-analysis
docker compose exec php make unit-test
```

Expected: no errors, all tests green.

- [ ] **Step 3.10 — Commit**

```bash
git add src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommand.php \
        src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php \
        src/Reservation/Application/Service/CreateReservationCommandFactory.php \
        src/Reservation/Domain/Exception/RoomNotAvailableException.php \
        tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php
git rm src/Reservation/Domain/Port/RoomExistsInterface.php \
       src/Reservation/Infrastructure/Service/RoomExistenceChecker.php
git commit -m "feat(reservation): accept roomTypeId in CreateReservation — pick available room internally"
```

---

## Task 4: Update controller + request DTO + OpenAPI

**Files:**
- Modify: `src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationRequest.php`
- Modify: `src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationController.php`
- Modify: `tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php`

- [ ] **Step 4.1 — Update `CreateReservationRequest`**

Replace `$roomId` with `$roomTypeId`:

```php
// src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationRequest.php
<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\CreateReservation;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class CreateReservationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        #[OA\Property(type: 'string', format: 'uuid')]
        public ?string $roomTypeId = null,
        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        #[OA\Property(type: 'string', format: 'uuid')]
        public ?string $bookerId = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(type: 'string', format: 'date', example: '2026-06-01')]
        public ?string $checkIn = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[Assert\GreaterThan(propertyPath: 'checkIn')]
        #[OA\Property(type: 'string', format: 'date', example: '2026-06-05')]
        public ?string $checkOut = null,
        #[Assert\NotBlank]
        #[Assert\Range(min: 1, max: 20)]
        #[OA\Property(type: 'integer', example: 2)]
        public ?int $guestCount = null,
    ) {
    }

    #[Assert\Callback]
    public function validateCheckInNotInPast(ExecutionContextInterface $context): void
    {
        if (null === $this->checkIn) {
            return;
        }

        $today = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->format('Y-m-d');
        if ($this->checkIn < $today) {
            $context->buildViolation('checkIn must be today or in the future (UTC).')
                ->atPath('checkIn')
                ->addViolation();
        }
    }
}
```

- [ ] **Step 4.2 — Update `CreateReservationController`**

Change `$request->roomId` to `$request->roomTypeId` in the factory call:

```php
$command = $this->commandFactory->create(
    (string) $request->roomTypeId,
    (string) $request->bookerId,
    (string) $request->checkIn,
    (string) $request->checkOut,
    (int) $request->guestCount,
);
```

Only this line changes. The rest of the controller stays identical.

- [ ] **Step 4.3 — Update the functional test**

Open `tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php`.

Find every place where `'roomId' => ...` appears in the request payload and replace it with `'roomTypeId' => ...`. Pass a valid `roomTypeId` UUID that corresponds to a room type that has at least one room with pricing configured in the test fixtures.

- [ ] **Step 4.4 — Run the functional test**

```bash
docker compose exec php make functional-test
```

Expected: all functional tests pass.

- [ ] **Step 4.5 — Regenerate OpenAPI spec**

```bash
docker compose exec php make openapi
```

Verify in `openapi.yaml` that the `CreateReservationRequest` schema now shows `roomTypeId` (not `roomId`).

- [ ] **Step 4.6 — Run full test suite and linter**

```bash
docker compose exec php make lint
docker compose exec php make test
```

Expected: no violations, all tests green.

- [ ] **Step 4.7 — Commit**

```bash
git add src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationRequest.php \
        src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationController.php \
        tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php \
        openapi.yaml
git commit -m "feat(reservation): controller accepts roomTypeId instead of roomId"
```

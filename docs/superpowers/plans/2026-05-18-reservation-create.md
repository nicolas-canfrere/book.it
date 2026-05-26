# CreateReservation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement `POST /reservations` (201 Created) and all supporting layers for the `CreateReservation` use case — domain model, ports, command handler, four cross-context adapters, Doctrine repository, migration, HTTP controller, and tests.

**Architecture:** New bounded context `Reservation` with the standard four-layer hexagonal structure (`Domain → Application → Infrastructure → UI`). External contexts (Room, Booker, Availability, Pricing) are consumed via ports in `Reservation/Domain/Port/`. A new `DomainEventBusInterface` is added to `Shared` (backed by Messenger's `domain.event.bus` with `allow_no_handlers: true`) so `ReservationCreated` can be dispatched without any subscriber yet. A minimal `GetReservationQueryHandler` is included because the controller needs it to build the 201 response after dispatch.

**Tech Stack:** PHP 8.4, Symfony 8.0, PostgreSQL 16 (DBAL `Connection $bookit`), Symfony Messenger, PHPUnit (`#[Group('unit')]` / `#[Group('functional')]`).

---

## File Map

| File | Status | Responsibility |
|------|--------|----------------|
| `src/Reservation/Domain/ValueObject/DatePeriod.php` | CREATE | checkIn/checkOut pair with invariant |
| `src/Reservation/Domain/Model/ReservationStatus.php` | CREATE | Enum: pending \| confirmed \| cancelled |
| `src/Reservation/Domain/Model/Reservation.php` | CREATE | Aggregate root |
| `src/Reservation/Domain/Port/ReservationRepositoryInterface.php` | CREATE | Persistence port |
| `src/Reservation/Domain/Port/ReservationIdGeneratorInterface.php` | CREATE | UUID generation port |
| `src/Reservation/Domain/Port/RoomExistsInterface.php` | CREATE | Cross-context: Room |
| `src/Reservation/Domain/Port/BookerExistsInterface.php` | CREATE | Cross-context: Booker |
| `src/Reservation/Domain/Port/RoomAvailabilityCheckerInterface.php` | CREATE | Cross-context: Availability |
| `src/Reservation/Domain/Port/PriceCalculatorInterface.php` | CREATE | Cross-context: Pricing |
| `src/Reservation/Domain/Exception/RoomNotFoundException.php` | CREATE | |
| `src/Reservation/Domain/Exception/BookerNotFoundException.php` | CREATE | |
| `src/Reservation/Domain/Exception/RoomNotAvailableException.php` | CREATE | |
| `src/Reservation/Domain/Exception/RoomNotBookableException.php` | CREATE | |
| `src/Reservation/Domain/Exception/ReservationNotFoundException.php` | CREATE | (used by future use cases) |
| `src/Reservation/Domain/Exception/InvalidReservationTransitionException.php` | CREATE | (used by future use cases) |
| `src/Reservation/Domain/Event/ReservationCreated.php` | CREATE | Domain event payload |
| `src/Shared/Application/Bus/DomainEventBusInterface.php` | CREATE | Shared bus port |
| `src/Shared/Infrastructure/Bus/MessengerDomainEventBus.php` | CREATE | Messenger-backed adapter |
| `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommand.php` | CREATE | |
| `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php` | CREATE | Orchestrates the 5-step flow |
| `src/Reservation/Application/UseCase/GetReservation/GetReservationQuery.php` | CREATE | Minimal read for 201 response |
| `src/Reservation/Application/UseCase/GetReservation/GetReservationQueryHandler.php` | CREATE | |
| `src/Reservation/Application/Service/CreateReservationCommandFactory.php` | CREATE | Generates ID + createdAt |
| `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php` | CREATE | DBAL persistence adapter |
| `src/Reservation/Infrastructure/Persistence/Doctrine/RoomExistenceChecker.php` | CREATE | Uses Room\RoomRepositoryInterface |
| `src/Reservation/Infrastructure/Persistence/Doctrine/BookerExistenceChecker.php` | CREATE | Uses Booker\BookerRepositoryInterface |
| `src/Reservation/Infrastructure/Service/AvailabilityChecker.php` | CREATE | Dispatches CheckAvailabilityQuery |
| `src/Reservation/Infrastructure/Service/PricingCalculator.php` | CREATE | Dispatches GetPricingQuoteQuery |
| `src/Reservation/Infrastructure/Service/UuidReservationIdGenerator.php` | CREATE | Symfony UID |
| `src/Reservation/UI/Http/Controller/ReservationSerializer.php` | CREATE | array shape for JSON responses |
| `src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationRequest.php` | CREATE | Request DTO with validation |
| `src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationController.php` | CREATE | POST /reservations → 201 |
| `config/services/reservation.yaml` | CREATE | DI wiring for the context |
| `config/services/exceptions.yaml` | MODIFY | Add 5 Reservation exception mappings |
| `config/packages/messenger.yaml` | MODIFY | Add domain.event.bus |
| `migrations/Version20260518000000.php` | CREATE | reservation table |
| `tests/Reservation/Domain/ValueObject/DatePeriodTest.php` | CREATE | |
| `tests/Reservation/Domain/Model/ReservationTest.php` | CREATE | |
| `tests/Reservation/Infrastructure/FakeRoomExistenceChecker.php` | CREATE | |
| `tests/Reservation/Infrastructure/FakeBookerExistenceChecker.php` | CREATE | |
| `tests/Reservation/Infrastructure/FakeRoomAvailabilityChecker.php` | CREATE | |
| `tests/Reservation/Infrastructure/FakePriceCalculator.php` | CREATE | |
| `tests/Reservation/Infrastructure/FakeDomainEventBus.php` | CREATE | |
| `tests/Reservation/Infrastructure/Persistence/InMemory/InMemoryReservationRepository.php` | CREATE | |
| `tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php` | CREATE | |
| `tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php` | CREATE | |

---

## Task 1: DatePeriod Value Object

**Files:**
- Create: `src/Reservation/Domain/ValueObject/DatePeriod.php`
- Test: `tests/Reservation/Domain/ValueObject/DatePeriodTest.php`

- [ ] **Step 1.1 — Write the failing tests**

```php
<?php
// tests/Reservation/Domain/ValueObject/DatePeriodTest.php
declare(strict_types=1);

namespace App\Tests\Reservation\Domain\ValueObject;

use App\Reservation\Domain\ValueObject\DatePeriod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DatePeriodTest extends TestCase
{
    #[Test]
    public function itCreatesAValidPeriod(): void
    {
        $period = new DatePeriod(
            new \DateTimeImmutable('2026-06-01'),
            new \DateTimeImmutable('2026-06-05'),
        );

        self::assertSame('2026-06-01', $period->checkIn->format('Y-m-d'));
        self::assertSame('2026-06-05', $period->checkOut->format('Y-m-d'));
    }

    #[Test]
    public function itRejectsCheckOutBeforeCheckIn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DatePeriod(
            new \DateTimeImmutable('2026-06-05'),
            new \DateTimeImmutable('2026-06-01'),
        );
    }

    #[Test]
    public function itRejectsCheckOutEqualToCheckIn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DatePeriod(
            new \DateTimeImmutable('2026-06-01'),
            new \DateTimeImmutable('2026-06-01'),
        );
    }
}
```

- [ ] **Step 1.2 — Run tests, expect FAIL (class not found)**

```bash
docker compose exec php vendor/bin/phpunit tests/Reservation/Domain/ValueObject/DatePeriodTest.php
```

Expected: error — `App\Reservation\Domain\ValueObject\DatePeriod not found`

- [ ] **Step 1.3 — Write the implementation**

```php
<?php
// src/Reservation/Domain/ValueObject/DatePeriod.php
declare(strict_types=1);

namespace App\Reservation\Domain\ValueObject;

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

- [ ] **Step 1.4 — Run tests, expect PASS**

```bash
docker compose exec php vendor/bin/phpunit tests/Reservation/Domain/ValueObject/DatePeriodTest.php
```

Expected: 3 tests, 3 assertions, OK

- [ ] **Step 1.5 — Commit**

```bash
git add src/Reservation/Domain/ValueObject/DatePeriod.php tests/Reservation/Domain/ValueObject/DatePeriodTest.php
git commit -m "feat(reservation): add DatePeriod value object"
```

---

## Task 2: ReservationStatus Enum + Reservation Aggregate

**Files:**
- Create: `src/Reservation/Domain/Model/ReservationStatus.php`
- Create: `src/Reservation/Domain/Model/Reservation.php`
- Test: `tests/Reservation/Domain/Model/ReservationTest.php`

- [ ] **Step 2.1 — Write the failing tests**

```php
<?php
// tests/Reservation/Domain/Model/ReservationTest.php
declare(strict_types=1);

namespace App\Tests\Reservation\Domain\Model;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\DatePeriod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationTest extends TestCase
{
    private const string ID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private const string ROOM_ID = '550e8400-e29b-41d4-a716-446655440001';
    private const string BOOKER_ID = '550e8400-e29b-41d4-a716-446655440002';

    #[Test]
    public function itInitializesWithPendingStatus(): void
    {
        $reservation = new Reservation(
            id: self::ID,
            roomId: self::ROOM_ID,
            bookerId: self::BOOKER_ID,
            period: new DatePeriod(
                new \DateTimeImmutable('2026-06-01'),
                new \DateTimeImmutable('2026-06-05'),
            ),
            totalPrice: 42000,
            createdAt: new \DateTimeImmutable('2026-05-18T10:00:00Z'),
        );

        self::assertSame(self::ID, $reservation->id);
        self::assertSame(self::ROOM_ID, $reservation->roomId);
        self::assertSame(self::BOOKER_ID, $reservation->bookerId);
        self::assertSame('2026-06-01', $reservation->period->checkIn->format('Y-m-d'));
        self::assertSame('2026-06-05', $reservation->period->checkOut->format('Y-m-d'));
        self::assertSame(42000, $reservation->totalPrice);
        self::assertSame(ReservationStatus::Pending, $reservation->status);
        self::assertSame('2026-05-18T10:00:00+00:00', $reservation->createdAt->format(\DateTimeInterface::ATOM));
    }

    #[Test]
    public function itAllowsZeroPrice(): void
    {
        $reservation = new Reservation(
            id: self::ID,
            roomId: self::ROOM_ID,
            bookerId: self::BOOKER_ID,
            period: new DatePeriod(
                new \DateTimeImmutable('2026-06-01'),
                new \DateTimeImmutable('2026-06-05'),
            ),
            totalPrice: 0,
            createdAt: new \DateTimeImmutable(),
        );

        self::assertSame(0, $reservation->totalPrice);
        self::assertSame(ReservationStatus::Pending, $reservation->status);
    }
}
```

- [ ] **Step 2.2 — Run tests, expect FAIL**

```bash
docker compose exec php vendor/bin/phpunit tests/Reservation/Domain/Model/ReservationTest.php
```

Expected: error — classes not found

- [ ] **Step 2.3 — Write ReservationStatus enum**

```php
<?php
// src/Reservation/Domain/Model/ReservationStatus.php
declare(strict_types=1);

namespace App\Reservation\Domain\Model;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
```

- [ ] **Step 2.4 — Write Reservation aggregate**

```php
<?php
// src/Reservation/Domain/Model/Reservation.php
declare(strict_types=1);

namespace App\Reservation\Domain\Model;

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
}
```

Note: `status` is mutable (not readonly) so `confirm()` and `cancel()` methods (future plans) can mutate it.

- [ ] **Step 2.5 — Run tests, expect PASS**

```bash
docker compose exec php vendor/bin/phpunit tests/Reservation/Domain/Model/ReservationTest.php
```

Expected: 2 tests, OK

- [ ] **Step 2.6 — Commit**

```bash
git add src/Reservation/Domain/Model/ tests/Reservation/Domain/Model/ReservationTest.php
git commit -m "feat(reservation): add Reservation aggregate and ReservationStatus enum"
```

---

## Task 3: Domain Ports, Exceptions & Domain Event

No tests needed — these are contracts and simple value classes.

**Files to create (all in one step):**

- [ ] **Step 3.1 — Create all port interfaces**

```php
<?php
// src/Reservation/Domain/Port/ReservationRepositoryInterface.php
declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Reservation\Domain\Model\Reservation;

interface ReservationRepositoryInterface
{
    public function add(Reservation $reservation): void;

    public function get(string $id): ?Reservation;
}
```

```php
<?php
// src/Reservation/Domain/Port/ReservationIdGeneratorInterface.php
declare(strict_types=1);

namespace App\Reservation\Domain\Port;

interface ReservationIdGeneratorInterface
{
    public function generate(): string;
}
```

```php
<?php
// src/Reservation/Domain/Port/RoomExistsInterface.php
declare(strict_types=1);

namespace App\Reservation\Domain\Port;

interface RoomExistsInterface
{
    public function exists(string $roomId): bool;
}
```

```php
<?php
// src/Reservation/Domain/Port/BookerExistsInterface.php
declare(strict_types=1);

namespace App\Reservation\Domain\Port;

interface BookerExistsInterface
{
    public function exists(string $bookerId): bool;
}
```

```php
<?php
// src/Reservation/Domain/Port/RoomAvailabilityCheckerInterface.php
declare(strict_types=1);

namespace App\Reservation\Domain\Port;

interface RoomAvailabilityCheckerInterface
{
    public function isAvailable(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool;
}
```

```php
<?php
// src/Reservation/Domain/Port/PriceCalculatorInterface.php
declare(strict_types=1);

namespace App\Reservation\Domain\Port;

interface PriceCalculatorInterface
{
    /**
     * Returns total price in cents.
     *
     * @throws \App\Reservation\Domain\Exception\RoomNotBookableException when no base rate is configured
     */
    public function calculate(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): int;
}
```

- [ ] **Step 3.2 — Create domain exceptions**

```php
<?php
// src/Reservation/Domain/Exception/RoomNotFoundException.php
declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

final class RoomNotFoundException extends \RuntimeException
{
    public function __construct(string $roomId)
    {
        parent::__construct(sprintf('Room "%s" not found.', $roomId));
    }
}
```

```php
<?php
// src/Reservation/Domain/Exception/BookerNotFoundException.php
declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

final class BookerNotFoundException extends \RuntimeException
{
    public function __construct(string $bookerId)
    {
        parent::__construct(sprintf('Booker "%s" not found.', $bookerId));
    }
}
```

```php
<?php
// src/Reservation/Domain/Exception/RoomNotAvailableException.php
declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

final class RoomNotAvailableException extends \RuntimeException
{
    public function __construct(string $roomId)
    {
        parent::__construct(sprintf('Room "%s" is not available for the requested period.', $roomId));
    }
}
```

```php
<?php
// src/Reservation/Domain/Exception/RoomNotBookableException.php
declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

final class RoomNotBookableException extends \RuntimeException
{
    public function __construct(string $roomId)
    {
        parent::__construct(sprintf('Room "%s" has no pricing configured.', $roomId));
    }
}
```

```php
<?php
// src/Reservation/Domain/Exception/ReservationNotFoundException.php
declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

final class ReservationNotFoundException extends \RuntimeException
{
    public function __construct(string $reservationId)
    {
        parent::__construct(sprintf('Reservation "%s" not found.', $reservationId));
    }
}
```

```php
<?php
// src/Reservation/Domain/Exception/InvalidReservationTransitionException.php
declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

use App\Reservation\Domain\Model\ReservationStatus;

final class InvalidReservationTransitionException extends \RuntimeException
{
    public function __construct(ReservationStatus $from, ReservationStatus $to)
    {
        parent::__construct(sprintf(
            'Cannot transition reservation from "%s" to "%s".',
            $from->value,
            $to->value,
        ));
    }
}
```

- [ ] **Step 3.3 — Create ReservationCreated domain event**

```php
<?php
// src/Reservation/Domain/Event/ReservationCreated.php
declare(strict_types=1);

namespace App\Reservation\Domain\Event;

final readonly class ReservationCreated
{
    public function __construct(
        public string $reservationId,
        public string $roomId,
        public string $bookerId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $totalPrice,
    ) {
    }
}
```

- [ ] **Step 3.4 — Commit**

```bash
git add src/Reservation/Domain/
git commit -m "feat(reservation): add domain ports, exceptions, and ReservationCreated event"
```

---

## Task 4: DomainEventBus (Shared)

**Files:**
- Create: `src/Shared/Application/Bus/DomainEventBusInterface.php`
- Create: `src/Shared/Infrastructure/Bus/MessengerDomainEventBus.php`
- Modify: `config/packages/messenger.yaml`

- [ ] **Step 4.1 — Create the DomainEventBusInterface**

```php
<?php
// src/Shared/Application/Bus/DomainEventBusInterface.php
declare(strict_types=1);

namespace App\Shared\Application\Bus;

interface DomainEventBusInterface
{
    public function dispatch(object $event): void;
}
```

- [ ] **Step 4.2 — Create the Messenger-backed implementation**

```php
<?php
// src/Shared/Infrastructure/Bus/MessengerDomainEventBus.php
declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\AsyncInternalMessageDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerDomainEventBus implements AsyncInternalMessageDispatcherInterface
{
    public function __construct(private MessageBusInterface $domainEventBus)
    {
    }

    public function dispatch(object $event): void
    {
        $this->domainEventBus->dispatch($event);
    }
}
```

Symfony's `HandleTrait` is NOT used here — we dispatch and forget (no return value expected).

- [ ] **Step 4.3 — Add domain.event.bus to messenger.yaml**

In `config/packages/messenger.yaml`, add the new bus under `buses:` and name the injection argument:

```yaml
framework:
    messenger:
        default_bus: messenger.bus.default
        buses:
            sync.command.bus:
                middleware:
                    - 'App\Shared\Infrastructure\Bus\Middleware\ExceptionThrowerMiddleware'
            sync.query.bus:
                middleware:
                    - 'App\Shared\Infrastructure\Bus\Middleware\ExceptionThrowerMiddleware'
            domain.event.bus:
                default_middleware:
                    enabled: true
                    allow_no_handlers: true
                    allow_no_senders: true
            messenger.bus.default:
        # ... rest of file unchanged
```

The `MessengerDomainEventBus` expects `MessageBusInterface $domainEventBus` — Symfony matches by argument name (`$domainEventBus` → `domain.event.bus`). No explicit binding needed.

- [ ] **Step 4.4 — Verify container compiles**

```bash
docker compose exec php bin/console debug:container --env=test App\\Shared\\Infrastructure\\Bus\\MessengerDomainEventBus 2>&1 | tail -5
```

Expected: service found (or no error if autowired)

- [ ] **Step 4.5 — Commit**

```bash
git add src/Shared/Application/Bus/DomainEventBusInterface.php src/Shared/Infrastructure/Bus/MessengerDomainEventBus.php config/packages/messenger.yaml
git commit -m "feat(shared): add DomainEventBusInterface backed by Messenger domain.event.bus"
```

---

## Task 5: Test Doubles

No tests for the doubles themselves — they are test infrastructure.

**Files:**
- Create: `tests/Reservation/Infrastructure/FakeRoomExistenceChecker.php`
- Create: `tests/Reservation/Infrastructure/FakeBookerExistenceChecker.php`
- Create: `tests/Reservation/Infrastructure/FakeRoomAvailabilityChecker.php`
- Create: `tests/Reservation/Infrastructure/FakePriceCalculator.php`
- Create: `tests/Reservation/Infrastructure/FakeDomainEventBus.php`
- Create: `tests/Reservation/Infrastructure/Persistence/InMemory/InMemoryReservationRepository.php`

- [ ] **Step 5.1 — Create the four cross-context fakes**

```php
<?php
// tests/Reservation/Infrastructure/FakeRoomExistenceChecker.php
declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Reservation\Domain\Port\RoomExistsInterface;

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

```php
<?php
// tests/Reservation/Infrastructure/FakeBookerExistenceChecker.php
declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Reservation\Domain\Port\BookerExistsInterface;

final class FakeBookerExistenceChecker implements BookerExistsInterface
{
    private bool $exists = true;

    public function setExists(bool $exists): void
    {
        $this->exists = $exists;
    }

    public function exists(string $bookerId): bool
    {
        return $this->exists;
    }
}
```

```php
<?php
// tests/Reservation/Infrastructure/FakeRoomAvailabilityChecker.php
declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;

final class FakeRoomAvailabilityChecker implements RoomAvailabilityCheckerInterface
{
    private bool $available = true;

    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    public function isAvailable(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        return $this->available;
    }
}
```

```php
<?php
// tests/Reservation/Infrastructure/FakePriceCalculator.php
declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Port\PriceCalculatorInterface;

final class FakePriceCalculator implements PriceCalculatorInterface
{
    private ?int $price = 42000;

    public function setPrice(?int $price): void
    {
        $this->price = $price;
    }

    public function calculate(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): int
    {
        if ($this->price === null) {
            throw new RoomNotBookableException($roomId);
        }

        return $this->price;
    }
}
```

- [ ] **Step 5.2 — Create FakeDomainEventBus**

```php
<?php
// tests/Reservation/Infrastructure/FakeDomainEventBus.php
declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Shared\Application\Bus\AsyncInternalMessageDispatcherInterface;

final class FakeDomainEventBus implements AsyncInternalMessageDispatcherInterface
{
    /** @var list<object> */
    private array $dispatched = [];

    public function dispatch(object $event): void
    {
        $this->dispatched[] = $event;
    }

    /** @return list<object> */
    public function getDispatched(): array
    {
        return $this->dispatched;
    }

    public function getLastDispatched(): ?object
    {
        if ([] === $this->dispatched) {
            return null;
        }

        return $this->dispatched[array_key_last($this->dispatched)];
    }
}
```

- [ ] **Step 5.3 — Create InMemoryReservationRepository**

```php
<?php
// tests/Reservation/Infrastructure/Persistence/InMemory/InMemoryReservationRepository.php
declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Persistence\InMemory;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;

final class InMemoryReservationRepository implements ReservationRepositoryInterface
{
    /** @var array<string, Reservation> */
    private array $store = [];

    public function add(Reservation $reservation): void
    {
        $this->store[$reservation->id] = $reservation;
    }

    public function get(string $id): ?Reservation
    {
        return $this->store[$id] ?? null;
    }
}
```

- [ ] **Step 5.4 — Commit**

```bash
git add tests/Reservation/Infrastructure/
git commit -m "test(reservation): add in-memory test doubles for CreateReservation"
```

---

## Task 6: CreateReservation + GetReservation Use Cases (TDD)

**Files:**
- Create: `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommand.php`
- Create: `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php`
- Create: `src/Reservation/Application/UseCase/GetReservation/GetReservationQuery.php`
- Create: `src/Reservation/Application/UseCase/GetReservation/GetReservationQueryHandler.php`
- Test: `tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php`

- [ ] **Step 6.1 — Write the failing handler tests**

```php
<?php
// tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php
declare(strict_types=1);

namespace App\Tests\Reservation\Application\UseCase\CreateReservation;

use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommand;
use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommandHandler;
use App\Reservation\Domain\Event\ReservationCreated;
use App\Reservation\Domain\Exception\BookerNotFoundException;
use App\Reservation\Domain\Exception\RoomNotAvailableException;
use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Exception\RoomNotFoundException;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Tests\Reservation\Infrastructure\FakeBookerExistenceChecker;
use App\Tests\Reservation\Infrastructure\FakeDomainEventBus;
use App\Tests\Reservation\Infrastructure\FakePriceCalculator;
use App\Tests\Reservation\Infrastructure\FakeRoomAvailabilityChecker;
use App\Tests\Reservation\Infrastructure\FakeRoomExistenceChecker;
use App\Tests\Reservation\Infrastructure\Persistence\InMemory\InMemoryReservationRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CreateReservationCommandHandlerTest extends TestCase
{
    private const string RESERVATION_ID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private const string ROOM_ID = '550e8400-e29b-41d4-a716-446655440001';
    private const string BOOKER_ID = '550e8400-e29b-41d4-a716-446655440002';

    private InMemoryReservationRepository $repository;
    private FakeRoomExistenceChecker $roomExists;
    private FakeBookerExistenceChecker $bookerExists;
    private FakeRoomAvailabilityChecker $availabilityChecker;
    private FakePriceCalculator $priceCalculator;
    private FakeDomainEventBus $eventBus;
    private CreateReservationCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryReservationRepository();
        $this->roomExists = new FakeRoomExistenceChecker();
        $this->bookerExists = new FakeBookerExistenceChecker();
        $this->availabilityChecker = new FakeRoomAvailabilityChecker();
        $this->priceCalculator = new FakePriceCalculator();
        $this->eventBus = new FakeDomainEventBus();

        $this->handler = new CreateReservationCommandHandler(
            $this->repository,
            $this->roomExists,
            $this->bookerExists,
            $this->availabilityChecker,
            $this->priceCalculator,
            $this->eventBus,
        );
    }

    private function makeCommand(string $id = self::RESERVATION_ID): CreateReservationCommand
    {
        return new CreateReservationCommand(
            id: $id,
            roomId: self::ROOM_ID,
            bookerId: self::BOOKER_ID,
            checkIn: new \DateTimeImmutable('2026-06-01'),
            checkOut: new \DateTimeImmutable('2026-06-05'),
            createdAt: new \DateTimeImmutable('2026-05-18T10:00:00Z'),
        );
    }

    #[Test]
    public function itCreatesAReservationInPendingStateAndDispatchesEvent(): void
    {
        ($this->handler)($this->makeCommand());

        $reservation = $this->repository->get(self::RESERVATION_ID);
        self::assertNotNull($reservation);
        self::assertSame(self::RESERVATION_ID, $reservation->id);
        self::assertSame(self::ROOM_ID, $reservation->roomId);
        self::assertSame(self::BOOKER_ID, $reservation->bookerId);
        self::assertSame('2026-06-01', $reservation->period->checkIn->format('Y-m-d'));
        self::assertSame('2026-06-05', $reservation->period->checkOut->format('Y-m-d'));
        self::assertSame(42000, $reservation->totalPrice);
        self::assertSame(ReservationStatus::Pending, $reservation->status);

        $event = $this->eventBus->getLastDispatched();
        self::assertInstanceOf(ReservationCreated::class, $event);
        self::assertSame(self::RESERVATION_ID, $event->reservationId);
        self::assertSame(self::ROOM_ID, $event->roomId);
        self::assertSame(self::BOOKER_ID, $event->bookerId);
        self::assertSame(42000, $event->totalPrice);
    }

    #[Test]
    public function itAcceptsZeroPrice(): void
    {
        $this->priceCalculator->setPrice(0);

        ($this->handler)($this->makeCommand());

        $reservation = $this->repository->get(self::RESERVATION_ID);
        self::assertNotNull($reservation);
        self::assertSame(0, $reservation->totalPrice);
    }

    #[Test]
    public function itThrowsWhenRoomDoesNotExist(): void
    {
        $this->roomExists->setExists(false);
        $this->expectException(RoomNotFoundException::class);

        ($this->handler)($this->makeCommand());
    }

    #[Test]
    public function itThrowsWhenBookerDoesNotExist(): void
    {
        $this->bookerExists->setExists(false);
        $this->expectException(BookerNotFoundException::class);

        ($this->handler)($this->makeCommand());
    }

    #[Test]
    public function itThrowsWhenRoomIsNotAvailable(): void
    {
        $this->availabilityChecker->setAvailable(false);
        $this->expectException(RoomNotAvailableException::class);

        ($this->handler)($this->makeCommand());
    }

    #[Test]
    public function itThrowsWhenRoomHasNoPricing(): void
    {
        $this->priceCalculator->setPrice(null);
        $this->expectException(RoomNotBookableException::class);

        ($this->handler)($this->makeCommand());
    }
}
```

- [ ] **Step 6.2 — Run tests, expect FAIL**

```bash
docker compose exec php vendor/bin/phpunit tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php
```

Expected: error — `CreateReservationCommand` not found

- [ ] **Step 6.3 — Write CreateReservationCommand**

```php
<?php
// src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommand.php
declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CreateReservation;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class CreateReservationCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public string $roomId,
        public string $bookerId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 6.4 — Write CreateReservationCommandHandler**

```php
<?php
// src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php
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
use App\Shared\Application\Bus\AsyncInternalMessageDispatcherInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class CreateReservationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private RoomExistsInterface $roomExists,
        private BookerExistsInterface $bookerExists,
        private RoomAvailabilityCheckerInterface $availabilityChecker,
        private PriceCalculatorInterface $priceCalculator,
        private AsyncInternalMessageDispatcherInterface $eventBus,
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

        $this->repository->add($reservation);

        $this->eventBus->dispatch(new ReservationCreated(
            reservationId: $reservation->id,
            roomId: $reservation->roomId,
            bookerId: $reservation->bookerId,
            checkIn: $reservation->period->checkIn,
            checkOut: $reservation->period->checkOut,
            totalPrice: $reservation->totalPrice,
        ));
    }
}
```

- [ ] **Step 6.5 — Run handler tests, expect PASS**

```bash
docker compose exec php vendor/bin/phpunit tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php
```

Expected: 6 tests, OK

- [ ] **Step 6.6 — Write GetReservation query + handler**

```php
<?php
// src/Reservation/Application/UseCase/GetReservation/GetReservationQuery.php
declare(strict_types=1);

namespace App\Reservation\Application\UseCase\GetReservation;

use App\Shared\Application\Bus\SyncQueryInterface;

/** @implements SyncQueryInterface<?array> */
final readonly class GetReservationQuery implements SyncQueryInterface
{
    public function __construct(public string $id)
    {
    }
}
```

```php
<?php
// src/Reservation/Application/UseCase/GetReservation/GetReservationQueryHandler.php
declare(strict_types=1);

namespace App\Reservation\Application\UseCase\GetReservation;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetReservationQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private ReservationRepositoryInterface $repository)
    {
    }

    public function __invoke(GetReservationQuery $query): ?Reservation
    {
        return $this->repository->get($query->id);
    }
}
```

- [ ] **Step 6.7 — Commit**

```bash
git add src/Reservation/Application/ tests/Reservation/Application/
git commit -m "feat(reservation): add CreateReservation and GetReservation use cases"
```

---

## Task 7: CreateReservationCommandFactory

**Files:**
- Create: `src/Reservation/Application/Service/CreateReservationCommandFactory.php`

No test — trivial composition that wraps UUID generation and `DateTimeImmutable` construction.

- [ ] **Step 7.1 — Write the factory**

```php
<?php
// src/Reservation/Application/Service/CreateReservationCommandFactory.php
declare(strict_types=1);

namespace App\Reservation\Application\Service;

use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommand;
use App\Reservation\Domain\Port\ReservationIdGeneratorInterface;

final readonly class CreateReservationCommandFactory
{
    public function __construct(private ReservationIdGeneratorInterface $idGenerator)
    {
    }

    public function create(
        string $roomId,
        string $bookerId,
        string $checkIn,
        string $checkOut,
    ): CreateReservationCommand {
        return new CreateReservationCommand(
            id: $this->idGenerator->generate(),
            roomId: $roomId,
            bookerId: $bookerId,
            checkIn: new \DateTimeImmutable($checkIn),
            checkOut: new \DateTimeImmutable($checkOut),
            createdAt: new \DateTimeImmutable(),
        );
    }
}
```

- [ ] **Step 7.2 — Commit**

```bash
git add src/Reservation/Application/Service/CreateReservationCommandFactory.php
git commit -m "feat(reservation): add CreateReservationCommandFactory"
```

---

## Task 8: Infrastructure Adapters

**Files:**
- Create: `src/Reservation/Infrastructure/Persistence/Doctrine/RoomExistenceChecker.php`
- Create: `src/Reservation/Infrastructure/Persistence/Doctrine/BookerExistenceChecker.php`
- Create: `src/Reservation/Infrastructure/Service/AvailabilityChecker.php`
- Create: `src/Reservation/Infrastructure/Service/PricingCalculator.php`
- Create: `src/Reservation/Infrastructure/Service/UuidReservationIdGenerator.php`

No tests — these adapters are thin wrappers; verified by functional tests in Task 11.

- [ ] **Step 8.1 — Create RoomExistenceChecker**

Same strategy as `Availability\Infrastructure\Persistence\Doctrine\RoomExistenceChecker` — delegates to the Room repository.

```php
<?php
// src/Reservation/Infrastructure/Persistence/Doctrine/RoomExistenceChecker.php
declare(strict_types=1);

namespace App\Reservation\Infrastructure\Persistence\Doctrine;

use App\Reservation\Domain\Port\RoomExistsInterface;
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

- [ ] **Step 8.2 — Create BookerExistenceChecker**

```php
<?php
// src/Reservation/Infrastructure/Persistence/Doctrine/BookerExistenceChecker.php
declare(strict_types=1);

namespace App\Reservation\Infrastructure\Persistence\Doctrine;

use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Reservation\Domain\Port\BookerExistsInterface;

final readonly class BookerExistenceChecker implements BookerExistsInterface
{
    public function __construct(private BookerRepositoryInterface $bookerRepository)
    {
    }

    public function exists(string $bookerId): bool
    {
        return null !== $this->bookerRepository->get($bookerId);
    }
}
```

- [ ] **Step 8.3 — Create AvailabilityChecker**

Dispatches `CheckAvailabilityQuery` via `SyncQueryBusInterface`.

```php
<?php
// src/Reservation/Infrastructure/Service/AvailabilityChecker.php
declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Availability\Application\UseCase\CheckAvailability\CheckAvailabilityQuery;
use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class AvailabilityChecker implements RoomAvailabilityCheckerInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function isAvailable(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        return $this->queryBus->ask(new CheckAvailabilityQuery($roomId, $checkIn, $checkOut));
    }
}
```

- [ ] **Step 8.4 — Create PricingCalculator**

Dispatches `GetPricingQuoteQuery` and translates `RoomHasNoBaseRateException` → `RoomNotBookableException`.

```php
<?php
// src/Reservation/Infrastructure/Service/PricingCalculator.php
declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Pricing\Application\UseCase\GetPricingQuote\GetPricingQuoteQuery;
use App\Pricing\Domain\Exception\RoomHasNoBaseRateException;
use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Port\PriceCalculatorInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class PricingCalculator implements PriceCalculatorInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function calculate(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): int
    {
        try {
            /** @var array{totalAmountCents: int} $result */
            $result = $this->queryBus->ask(new GetPricingQuoteQuery($roomId, $checkIn, $checkOut));

            return $result['totalAmountCents'];
        } catch (RoomHasNoBaseRateException) {
            throw new RoomNotBookableException($roomId);
        }
    }
}
```

- [ ] **Step 8.5 — Create UuidReservationIdGenerator**

```php
<?php
// src/Reservation/Infrastructure/Service/UuidReservationIdGenerator.php
declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\ReservationIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class UuidReservationIdGenerator implements ReservationIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}
```

- [ ] **Step 8.6 — Commit**

```bash
git add src/Reservation/Infrastructure/
git commit -m "feat(reservation): add infrastructure adapters for Room, Booker, Availability, Pricing"
```

---

## Task 9: Doctrine Repository + Migration

**Files:**
- Create: `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php`
- Create: `migrations/Version20260518000000.php`

- [ ] **Step 9.1 — Write the Doctrine repository**

```php
<?php
// src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php
declare(strict_types=1);

namespace App\Reservation\Infrastructure\Persistence\Doctrine;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\DatePeriod;
use Doctrine\DBAL\Connection;

final readonly class ReservationRepository implements ReservationRepositoryInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function add(Reservation $reservation): void
    {
        $this->bookit->insert('reservation', [
            'id' => $reservation->id,
            'room_id' => $reservation->roomId,
            'booker_id' => $reservation->bookerId,
            'check_in' => $reservation->period->checkIn->format('Y-m-d'),
            'check_out' => $reservation->period->checkOut->format('Y-m-d'),
            'total_price' => $reservation->totalPrice,
            'status' => $reservation->status->value,
            'created_at' => $reservation->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $id): ?Reservation
    {
        /** @var array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, status: string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, room_id, booker_id, check_in, check_out, total_price, status, created_at
               FROM reservation
              WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @param array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, status: string, created_at: string} $row
     */
    private function hydrate(array $row): Reservation
    {
        $reservation = new Reservation(
            id: $row['id'],
            roomId: $row['room_id'],
            bookerId: $row['booker_id'],
            period: new DatePeriod(
                new \DateTimeImmutable($row['check_in']),
                new \DateTimeImmutable($row['check_out']),
            ),
            totalPrice: (int) $row['total_price'],
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
        $reservation->status = ReservationStatus::from($row['status']);

        return $reservation;
    }
}
```

- [ ] **Step 9.2 — Write the migration**

```php
<?php
// migrations/Version20260518000000.php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reservation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE reservation (
                id UUID NOT NULL,
                room_id UUID NOT NULL,
                booker_id UUID NOT NULL,
                check_in DATE NOT NULL,
                check_out DATE NOT NULL,
                total_price INTEGER NOT NULL,
                status VARCHAR(20) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_reservation_room_id ON reservation (room_id)');
        $this->addSql('CREATE INDEX idx_reservation_booker_id ON reservation (booker_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_reservation_booker_id');
        $this->addSql('DROP INDEX idx_reservation_room_id');
        $this->addSql('DROP TABLE reservation');
    }
}
```

- [ ] **Step 9.3 — Run the migration**

```bash
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

Expected: migration executed, no errors

- [ ] **Step 9.4 — Commit**

```bash
git add src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php migrations/Version20260518000000.php
git commit -m "feat(reservation): add Doctrine repository and reservation table migration"
```

---

## Task 10: HTTP Layer

**Files:**
- Create: `src/Reservation/UI/Http/Controller/ReservationSerializer.php`
- Create: `src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationRequest.php`
- Create: `src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationController.php`

- [ ] **Step 10.1 — Write ReservationSerializer**

```php
<?php
// src/Reservation/UI/Http/Controller/ReservationSerializer.php
declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller;

use App\Reservation\Domain\Model\Reservation;

final readonly class ReservationSerializer
{
    /**
     * @return array{id: string, roomId: string, bookerId: string, checkIn: string, checkOut: string, totalPrice: int, status: string, createdAt: string}
     */
    public function serialize(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'roomId' => $reservation->roomId,
            'bookerId' => $reservation->bookerId,
            'checkIn' => $reservation->period->checkIn->format('Y-m-d'),
            'checkOut' => $reservation->period->checkOut->format('Y-m-d'),
            'totalPrice' => $reservation->totalPrice,
            'status' => $reservation->status->value,
            'createdAt' => $reservation->createdAt
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
```

- [ ] **Step 10.2 — Write CreateReservationRequest**

Cross-field validation: `checkOut > checkIn` via `#[Assert\GreaterThan(propertyPath: 'checkIn')]`; `checkIn >= today (UTC)` via a `#[Assert\Callback]` method.

```php
<?php
// src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationRequest.php
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
        public ?string $roomId = null,

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
    ) {
    }

    #[Assert\Callback]
    public function validateCheckInNotInPast(ExecutionContextInterface $context): void
    {
        if ($this->checkIn === null) {
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

- [ ] **Step 10.3 — Write CreateReservationController**

```php
<?php
// src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationController.php
declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\CreateReservation;

use App\Reservation\Application\Service\CreateReservationCommandFactory;
use App\Reservation\Application\UseCase\GetReservation\GetReservationQuery;
use App\Reservation\UI\Http\Controller\ReservationSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class CreateReservationController
{
    public function __construct(
        private CreateReservationCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private ReservationSerializer $serializer,
    ) {
    }

    #[Route('/api/reservations', name: 'reservation_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a reservation',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: CreateReservationRequest::class)),
        ),
        tags: ['Reservation'],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Reservation created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'bookerId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'checkIn', type: 'string', format: 'date', example: '2026-06-01'),
                        new OA\Property(property: 'checkOut', type: 'string', format: 'date', example: '2026-06-05'),
                        new OA\Property(property: 'totalPrice', type: 'integer', example: 42000),
                        new OA\Property(property: 'status', type: 'string', example: 'pending'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Room or booker not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Room not available', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'No pricing configured or validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] CreateReservationRequest $request,
    ): Response {
        $command = $this->commandFactory->create(
            (string) $request->roomId,
            (string) $request->bookerId,
            (string) $request->checkIn,
            (string) $request->checkOut,
        );
        $this->commandBus->execute($command);

        $reservation = $this->queryBus->ask(new GetReservationQuery($command->id));
        if (null === $reservation) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->serializer->serialize($reservation), Response::HTTP_CREATED);
    }
}
```

- [ ] **Step 10.4 — Commit**

```bash
git add src/Reservation/UI/
git commit -m "feat(reservation): add CreateReservation HTTP controller, request DTO, and serializer"
```

---

## Task 11: Service Config + Exception Mappings

**Files:**
- Create: `config/services/reservation.yaml`
- Modify: `config/services/exceptions.yaml`

- [ ] **Step 11.1 — Create config/services/reservation.yaml**

```yaml
# config/services/reservation.yaml
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

    App\Reservation\Domain\:
        resource: '../../src/Reservation/Domain/'
        exclude:
            - '../../src/Reservation/Domain/Model/'
            - '../../src/Reservation/Domain/Event/'

    App\Reservation\Application\:
        resource: '../../src/Reservation/Application/'
        exclude:
            - '../../src/Reservation/Application/**/*Command.php'
            - '../../src/Reservation/Application/**/*Query.php'

    App\Reservation\Infrastructure\:
        resource: '../../src/Reservation/Infrastructure/'

    App\Reservation\UI\:
        resource: '../../src/Reservation/UI/'
        exclude:
            - '../../src/Reservation/UI/**/*Request.php'
```

- [ ] **Step 11.2 — Add exception mappings to config/services/exceptions.yaml**

Append the following entries under `$map:` in `config/services/exceptions.yaml`:

```yaml
                App\Reservation\Domain\Exception\RoomNotFoundException:
                    type: 'https://book.it/problems/room-not-found'
                    title: 'Room Not Found'
                    status: 404
                App\Reservation\Domain\Exception\BookerNotFoundException:
                    type: 'https://book.it/problems/booker-not-found'
                    title: 'Booker Not Found'
                    status: 404
                App\Reservation\Domain\Exception\RoomNotAvailableException:
                    type: 'https://book.it/problems/room-not-available'
                    title: 'Room Not Available'
                    status: 409
                App\Reservation\Domain\Exception\RoomNotBookableException:
                    type: 'https://book.it/problems/room-not-bookable'
                    title: 'Room Not Bookable'
                    status: 422
                App\Reservation\Domain\Exception\InvalidReservationTransitionException:
                    type: 'https://book.it/problems/invalid-reservation-transition'
                    title: 'Invalid Reservation Transition'
                    status: 409
                App\Reservation\Domain\Exception\ReservationNotFoundException:
                    type: 'https://book.it/problems/reservation-not-found'
                    title: 'Reservation Not Found'
                    status: 404
```

- [ ] **Step 11.3 — Verify the container compiles**

```bash
docker compose exec php bin/console debug:container --env=test App\\Reservation\\Application\\UseCase\\CreateReservation\\CreateReservationCommandHandler
```

Expected: service found, no errors

- [ ] **Step 11.4 — Run all unit tests**

```bash
docker compose exec php vendor/bin/phpunit --group unit
```

Expected: all existing tests + 8 new Reservation tests, green

- [ ] **Step 11.5 — Commit**

```bash
git add config/services/reservation.yaml config/services/exceptions.yaml
git commit -m "feat(reservation): configure DI services and exception mappings"
```

---

## Task 12: Functional Tests

**Files:**
- Create: `tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php`

- [ ] **Step 12.1 — Write the functional tests**

```php
<?php
// tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php
declare(strict_types=1);

namespace App\Tests\Reservation\UI\Http\Controller\CreateReservation;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class CreateReservationControllerTest extends WebTestCase
{
    #[Test]
    public function itCreatesAReservationAndReturns201(): void
    {
        $client = static::createClient();
        [$roomId, $bookerId] = $this->setupRoomAndBooker($client);
        $this->setBaseRate($client, $roomId, 10000);

        $client->request(
            method: 'POST',
            uri: '/api/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomId' => $roomId,
                'bookerId' => $bookerId,
                'checkIn' => '2030-06-01',
                'checkOut' => '2030-06-05',
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{id: string, roomId: string, bookerId: string, checkIn: string, checkOut: string, totalPrice: int, status: string, createdAt: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['id']);
        self::assertSame($roomId, $body['roomId']);
        self::assertSame($bookerId, $body['bookerId']);
        self::assertSame('2030-06-01', $body['checkIn']);
        self::assertSame('2030-06-05', $body['checkOut']);
        self::assertSame(40000, $body['totalPrice']); // 4 nights × 10000
        self::assertSame('pending', $body['status']);
        self::assertNotEmpty($body['createdAt']);
    }

    #[Test]
    public function itReturns404WhenRoomDoesNotExist(): void
    {
        $client = static::createClient();
        [, $bookerId] = $this->setupRoomAndBooker($client);

        $client->request(
            method: 'POST',
            uri: '/api/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomId' => '00000000-0000-4000-8000-000000000001',
                'bookerId' => $bookerId,
                'checkIn' => '2030-06-01',
                'checkOut' => '2030-06-05',
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-not-found', $body['type']);
        self::assertSame(Response::HTTP_NOT_FOUND, $body['status']);
    }

    #[Test]
    public function itReturns404WhenBookerDoesNotExist(): void
    {
        $client = static::createClient();
        [$roomId] = $this->setupRoomAndBooker($client);

        $client->request(
            method: 'POST',
            uri: '/api/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomId' => $roomId,
                'bookerId' => '00000000-0000-4000-8000-000000000002',
                'checkIn' => '2030-06-01',
                'checkOut' => '2030-06-05',
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        /** @var array{type: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/booker-not-found', $body['type']);
    }

    #[Test]
    public function itReturns409WhenRoomIsNotAvailable(): void
    {
        $client = static::createClient();
        [$roomId, $bookerId] = $this->setupRoomAndBooker($client);
        $this->setBaseRate($client, $roomId, 10000);
        $this->blockPeriod($client, $roomId, '2030-06-01', '2030-06-10');

        $client->request(
            method: 'POST',
            uri: '/api/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomId' => $roomId,
                'bookerId' => $bookerId,
                'checkIn' => '2030-06-03',
                'checkOut' => '2030-06-07',
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());

        /** @var array{type: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-not-available', $body['type']);
    }

    #[Test]
    public function itReturns422WhenRoomHasNoPricing(): void
    {
        $client = static::createClient();
        [$roomId, $bookerId] = $this->setupRoomAndBooker($client);
        // Intentionally NOT setting a base rate

        $client->request(
            method: 'POST',
            uri: '/api/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomId' => $roomId,
                'bookerId' => $bookerId,
                'checkIn' => '2030-06-01',
                'checkOut' => '2030-06-05',
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        /** @var array{type: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-not-bookable', $body['type']);
    }

    #[Test]
    public function itReturns422WhenRequestBodyIsInvalid(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['roomId' => 'not-a-uuid', 'checkIn' => '2030-06-01'], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    /** @return array{string, string} [roomId, bookerId] */
    private function setupRoomAndBooker(KernelBrowser $client): array
    {
        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Test Hotel',
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
            uri: '/api/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'firstName' => 'Alice',
                'lastName' => 'Martin',
                'email' => 'alice.' . uniqid() . '@example.com',
                'phone' => '+33612345678',
                'dateOfBirth' => '1990-01-01',
            ], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $bookerBody */
        $bookerBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return [$roomBody['id'], $bookerBody['id']];
    }

    private function setBaseRate(KernelBrowser $client, string $roomId, int $amountCents): void
    {
        $client->request(
            method: 'PUT',
            uri: "/api/rooms/{$roomId}/base-rate",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amountCents' => $amountCents], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    private function blockPeriod(KernelBrowser $client, string $roomId, string $checkIn, string $checkOut): void
    {
        $client->request(
            method: 'POST',
            uri: "/api/rooms/{$roomId}/blocked-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => $checkIn, 'checkOut' => $checkOut], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    }
}
```

- [ ] **Step 12.2 — Run functional tests**

```bash
docker compose exec php vendor/bin/phpunit tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php --group functional
```

Expected: 6 tests, green

- [ ] **Step 12.3 — Run the full test suite**

```bash
docker compose exec php vendor/bin/phpunit
```

Expected: all tests green, no regressions

- [ ] **Step 12.4 — Run linting + architecture checks**

```bash
make lint
```

Expected: no violations

- [ ] **Step 12.5 — Commit**

```bash
git add tests/Reservation/UI/
git commit -m "test(reservation): add functional tests for POST /reservations"
```

---

## Spec Coverage Verification

| Spec requirement | Task |
|-----------------|------|
| `DatePeriod` VO — `checkOut > checkIn` | Task 1 |
| `Reservation` aggregate — all fields, status = `pending` | Task 2 |
| `ReservationStatus` enum: pending \| confirmed \| cancelled | Task 2 |
| `RoomNotFoundException`, `BookerNotFoundException`, `RoomNotAvailableException`, `RoomNotBookableException`, `InvalidReservationTransitionException`, `ReservationNotFoundException` | Task 3 |
| `ReservationCreated` event with correct payload | Tasks 3 + 6 |
| `CreateReservationCommandHandler` — 5-step flow | Task 6 |
| Event dispatched on success | Task 6 |
| All 4 external ports: RoomExists, BookerExists, AvailabilityChecker, PriceCalculator | Tasks 3 + 8 |
| Infrastructure adapters for each port | Task 8 |
| `DomainEventBus` — dispatch without subscribers | Task 4 |
| `POST /reservations` → 201 | Task 10 |
| Request validation: UUID v4, valid dates, `checkOut > checkIn`, `checkIn >= today` | Task 10 |
| Exception → HTTP status mappings | Task 11 |
| Unit tests — aggregate, DatePeriod, handler scenarios | Tasks 1, 2, 6 |
| Functional tests — 201, 404 room, 404 booker, 409, 422 no rate, 422 invalid DTO | Task 12 |

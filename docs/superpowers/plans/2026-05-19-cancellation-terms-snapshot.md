# Cancellation Terms Snapshot on Reservation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Attach a `CancellationTerms` snapshot (from the Room's current `CancellationPolicy` in the Pricing context) to every `Reservation` at creation time, store it as a nullable int column, expose it in `ReservationCreated`, and expose it in the serialized API response.

**Architecture:** A new domain port `CancellationPolicyFetcherInterface` is added to the Reservation context; the infrastructure adapter crosses into Pricing via the query bus, mapping the result (or its absence) to a `CancellationTerms` value object. `CancellationTerms` lives in the Reservation domain and is self-contained — no Pricing dependency leaks into Domain or Application layers. Persistence is raw DBAL (no ORM); a manual migration adds the column.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL (raw SQL), PHPUnit, Symfony Messenger query bus

---

## File Map

| Action | Path | Purpose |
|---|---|---|
| Create | `src/Reservation/Domain/ValueObject/CancellationTerms.php` | Value object: named constructors + `isRefundable()` |
| Create | `src/Reservation/Domain/Port/CancellationPolicyFetcherInterface.php` | Domain port: fetch `CancellationTerms` for a Room |
| Create | `src/Reservation/Infrastructure/Service/PricingCancellationPolicyFetcher.php` | Adapter: crosses to Pricing query bus |
| Create | `tests/Reservation/Domain/ValueObject/CancellationTermsTest.php` | Unit tests for the value object |
| Create | `tests/Reservation/Infrastructure/FakeCancellationPolicyFetcher.php` | Test double for the port |
| Create | `tests/Reservation/Infrastructure/Service/PricingCancellationPolicyFetcherTest.php` | Unit test for the adapter |
| Modify | `src/Reservation/Domain/Model/Reservation.php` | Add `cancellationTerms: CancellationTerms` property |
| Modify | `src/Reservation/Domain/Event/ReservationCreated.php` | Add `cancellationTerms: CancellationTerms` |
| Modify | `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php` | Inject fetcher, fetch terms, pass to model and event |
| Modify | `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php` | Add column to INSERT, SELECT, and hydration |
| Modify | `src/Reservation/UI/Http/Controller/ReservationSerializer.php` | Add `cancellationTerms` to JSON output |
| Modify | `tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php` | Add fake fetcher, assert on stored terms and event |
| Create | `migrations/Version202605XX000000.php` | Add `cancellation_terms_days_threshold INT NULL` to `reservation` |

---

### Task 1: `CancellationTerms` value object

**Files:**
- Create: `src/Reservation/Domain/ValueObject/CancellationTerms.php`
- Create: `tests/Reservation/Domain/ValueObject/CancellationTermsTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Reservation/Domain/ValueObject/CancellationTermsTest.php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\ValueObject;

use App\Reservation\Domain\ValueObject\CancellationTerms;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CancellationTermsTest extends TestCase
{
    #[Test]
    public function alwaysRefundableHasNullThreshold(): void
    {
        $terms = CancellationTerms::alwaysRefundable();

        self::assertNull($terms->daysThreshold);
    }

    #[Test]
    public function withThresholdStoresTheDays(): void
    {
        $terms = CancellationTerms::withThreshold(7);

        self::assertSame(7, $terms->daysThreshold);
    }

    #[Test]
    public function withThresholdRejectsZeroOrNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CancellationTerms::withThreshold(0);
    }

    #[Test]
    public function alwaysRefundableIsAlwaysRefundable(): void
    {
        $terms = CancellationTerms::alwaysRefundable();

        self::assertTrue($terms->isRefundable(
            new \DateTimeImmutable('2026-06-09'),
            new \DateTimeImmutable('2026-06-10'),
        ));
    }

    #[Test]
    public function cancellationStrictlyBeforeDeadlineIsRefundable(): void
    {
        // threshold 3 days, check-in June 10 → deadline June 7
        // cancel on June 6 (strictly before June 7) → refundable
        $terms = CancellationTerms::withThreshold(3);

        self::assertTrue($terms->isRefundable(
            new \DateTimeImmutable('2026-06-06'),
            new \DateTimeImmutable('2026-06-10'),
        ));
    }

    #[Test]
    public function cancellationOnDeadlineDayIsNotRefundable(): void
    {
        // threshold 3 days, check-in June 10 → deadline June 7
        // cancel on June 7 (on the deadline) → NOT refundable
        $terms = CancellationTerms::withThreshold(3);

        self::assertFalse($terms->isRefundable(
            new \DateTimeImmutable('2026-06-07'),
            new \DateTimeImmutable('2026-06-10'),
        ));
    }

    #[Test]
    public function cancellationAfterDeadlineIsNotRefundable(): void
    {
        $terms = CancellationTerms::withThreshold(3);

        self::assertFalse($terms->isRefundable(
            new \DateTimeImmutable('2026-06-09'),
            new \DateTimeImmutable('2026-06-10'),
        ));
    }

    #[Test]
    public function timeOfDayIsIgnored(): void
    {
        // cancel at 23:59 on June 6 — still a June 6 cancellation, still refundable
        $terms = CancellationTerms::withThreshold(3);

        self::assertTrue($terms->isRefundable(
            new \DateTimeImmutable('2026-06-06T23:59:59Z'),
            new \DateTimeImmutable('2026-06-10T14:00:00Z'),
        ));
    }
}
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
make unit-test -- --filter CancellationTermsTest
```

Expected: class not found error.

- [ ] **Step 3: Implement `CancellationTerms`**

```php
// src/Reservation/Domain/ValueObject/CancellationTerms.php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\ValueObject;

final readonly class CancellationTerms
{
    private function __construct(
        public ?int $daysThreshold,
    ) {
    }

    public static function alwaysRefundable(): self
    {
        return new self(null);
    }

    public static function withThreshold(int $days): self
    {
        if ($days <= 0) {
            throw new \InvalidArgumentException('Days threshold must be greater than zero.');
        }

        return new self($days);
    }

    public function isRefundable(\DateTimeImmutable $cancelledAt, \DateTimeImmutable $checkIn): bool
    {
        if (null === $this->daysThreshold) {
            return true;
        }

        $cancelDate = new \DateTimeImmutable($cancelledAt->format('Y-m-d'));
        $deadline = (new \DateTimeImmutable($checkIn->format('Y-m-d')))
            ->modify("-{$this->daysThreshold} days");

        return $cancelDate < $deadline;
    }
}
```

- [ ] **Step 4: Run the test to confirm it passes**

```bash
make unit-test -- --filter CancellationTermsTest
```

Expected: all 7 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Reservation/Domain/ValueObject/CancellationTerms.php \
        tests/Reservation/Domain/ValueObject/CancellationTermsTest.php
git commit -m "feat(reservation): add CancellationTerms value object with refund eligibility logic"
```

---

### Task 2: Port interface + infrastructure adapter

**Files:**
- Create: `src/Reservation/Domain/Port/CancellationPolicyFetcherInterface.php`
- Create: `src/Reservation/Infrastructure/Service/PricingCancellationPolicyFetcher.php`
- Create: `tests/Reservation/Infrastructure/FakeCancellationPolicyFetcher.php`
- Create: `tests/Reservation/Infrastructure/Service/PricingCancellationPolicyFetcherTest.php`

- [ ] **Step 1: Create the port interface**

```php
// src/Reservation/Domain/Port/CancellationPolicyFetcherInterface.php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Reservation\Domain\ValueObject\CancellationTerms;

interface CancellationPolicyFetcherInterface
{
    public function fetch(string $roomId): CancellationTerms;
}
```

- [ ] **Step 2: Create the test double**

```php
// tests/Reservation/Infrastructure/FakeCancellationPolicyFetcher.php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Reservation\Domain\Port\CancellationPolicyFetcherInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;

final class FakeCancellationPolicyFetcher implements CancellationPolicyFetcherInterface
{
    private CancellationTerms $terms;

    public function __construct()
    {
        $this->terms = CancellationTerms::alwaysRefundable();
    }

    public function setTerms(CancellationTerms $terms): void
    {
        $this->terms = $terms;
    }

    public function fetch(string $roomId): CancellationTerms
    {
        return $this->terms;
    }
}
```

- [ ] **Step 3: Write the failing adapter test**

```php
// tests/Reservation/Infrastructure/Service/PricingCancellationPolicyFetcherTest.php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Service;

use App\Pricing\Domain\Exception\CancellationPolicyNotFoundException;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Reservation\Infrastructure\Service\PricingCancellationPolicyFetcher;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PricingCancellationPolicyFetcherTest extends TestCase
{
    #[Test]
    public function itReturnsCancellationTermsWithThresholdWhenPolicyExists(): void
    {
        $queryBus = $this->createMock(SyncQueryBusInterface::class);
        $queryBus->method('ask')->willReturn(
            new CancellationPolicy('room-id', 7, new \DateTimeImmutable()),
        );

        $fetcher = new PricingCancellationPolicyFetcher($queryBus);
        $terms = $fetcher->fetch('room-id');

        self::assertSame(7, $terms->daysThreshold);
    }

    #[Test]
    public function itReturnsAlwaysRefundableWhenNoPolicyExists(): void
    {
        $queryBus = $this->createMock(SyncQueryBusInterface::class);
        $queryBus->method('ask')->willThrowException(
            new CancellationPolicyNotFoundException('room-id'),
        );

        $fetcher = new PricingCancellationPolicyFetcher($queryBus);
        $terms = $fetcher->fetch('room-id');

        self::assertNull($terms->daysThreshold);
    }
}
```

- [ ] **Step 4: Run the test to confirm it fails**

```bash
make unit-test -- --filter PricingCancellationPolicyFetcherTest
```

Expected: class not found error.

- [ ] **Step 5: Implement the adapter**

```php
// src/Reservation/Infrastructure/Service/PricingCancellationPolicyFetcher.php
<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Pricing\Application\UseCase\GetCancellationPolicy\GetCancellationPolicyQuery;
use App\Pricing\Domain\Exception\CancellationPolicyNotFoundException;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Reservation\Domain\Port\CancellationPolicyFetcherInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class PricingCancellationPolicyFetcher implements CancellationPolicyFetcherInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function fetch(string $roomId): CancellationTerms
    {
        try {
            /** @var CancellationPolicy $policy */
            $policy = $this->queryBus->ask(new GetCancellationPolicyQuery($roomId));

            return CancellationTerms::withThreshold($policy->daysThreshold);
        } catch (CancellationPolicyNotFoundException) {
            return CancellationTerms::alwaysRefundable();
        }
    }
}
```

- [ ] **Step 6: Run the test to confirm it passes**

```bash
make unit-test -- --filter PricingCancellationPolicyFetcherTest
```

Expected: 2 tests pass.

- [ ] **Step 7: Commit**

```bash
git add src/Reservation/Domain/Port/CancellationPolicyFetcherInterface.php \
        src/Reservation/Infrastructure/Service/PricingCancellationPolicyFetcher.php \
        tests/Reservation/Infrastructure/FakeCancellationPolicyFetcher.php \
        tests/Reservation/Infrastructure/Service/PricingCancellationPolicyFetcherTest.php
git commit -m "feat(reservation): add CancellationPolicyFetcherInterface port and PricingCancellationPolicyFetcher adapter"
```

---

### Task 3: Update `Reservation` model and `ReservationCreated` event

**Files:**
- Modify: `src/Reservation/Domain/Model/Reservation.php`
- Modify: `src/Reservation/Domain/Event/ReservationCreated.php`

- [ ] **Step 1: Add `cancellationTerms` to `Reservation`**

Replace the constructor in `src/Reservation/Domain/Model/Reservation.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

use App\Reservation\Domain\Exception\InvalidReservationTransitionException;
use App\Reservation\Domain\ValueObject\CancellationTerms;
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
        public readonly CancellationTerms $cancellationTerms,
        public readonly \DateTimeImmutable $createdAt,
    ) {
        $this->status = ReservationStatus::Pending;
    }

    public function expire(): void
    {
        if (ReservationStatus::Pending !== $this->status) {
            throw new InvalidReservationTransitionException($this->status, ReservationStatus::Expired);
        }

        $this->status = ReservationStatus::Expired;
    }
}
```

- [ ] **Step 2: Add `cancellationTerms` to `ReservationCreated`**

Replace `src/Reservation/Domain/Event/ReservationCreated.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Event;

use App\Reservation\Domain\ValueObject\CancellationTerms;

final readonly class ReservationCreated
{
    public function __construct(
        public string $reservationId,
        public string $roomId,
        public string $bookerId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $totalPrice,
        public CancellationTerms $cancellationTerms,
    ) {
    }
}
```

- [ ] **Step 3: Run static analysis to confirm no type errors**

```bash
make phpstan
```

Expected: errors pointing to the handler, repository, and tests that now miss the new constructor argument — that's correct; they'll be fixed in subsequent tasks.

- [ ] **Step 4: Commit**

```bash
git add src/Reservation/Domain/Model/Reservation.php \
        src/Reservation/Domain/Event/ReservationCreated.php
git commit -m "feat(reservation): add cancellationTerms field to Reservation model and ReservationCreated event"
```

---

### Task 4: Update `CreateReservationCommandHandler` + its unit test

**Files:**
- Modify: `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php`
- Modify: `tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php`

- [ ] **Step 1: Update the handler**

Replace `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CreateReservation;

use App\Reservation\Application\UseCase\ExpireReservation\ExpireReservationCommand;
use App\Reservation\Domain\Event\ReservationCreated;
use App\Reservation\Domain\Exception\BookerNotFoundException;
use App\Reservation\Domain\Exception\RoomNotAvailableException;
use App\Reservation\Domain\Exception\RoomNotFoundException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Port\BookerExistsInterface;
use App\Reservation\Domain\Port\CancellationPolicyFetcherInterface;
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
        private CancellationPolicyFetcherInterface $cancellationPolicyFetcher,
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
        $cancellationTerms = $this->cancellationPolicyFetcher->fetch($command->roomId);

        $reservation = new Reservation(
            id: $command->id,
            roomId: $command->roomId,
            bookerId: $command->bookerId,
            period: new DatePeriod($command->checkIn, $command->checkOut),
            totalPrice: $totalPrice,
            cancellationTerms: $cancellationTerms,
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
                cancellationTerms: $reservation->cancellationTerms,
            ));
        });

        $this->asyncDispatcher->dispatch(
            new ExpireReservationCommand($reservation->id),
            900_000,
        );
    }
}
```

- [ ] **Step 2: Update the unit test**

Replace `tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Application\UseCase\CreateReservation;

use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommand;
use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommandHandler;
use App\Reservation\Application\UseCase\ExpireReservation\ExpireReservationCommand;
use App\Reservation\Domain\Event\ReservationCreated;
use App\Reservation\Domain\Exception\BookerNotFoundException;
use App\Reservation\Domain\Exception\RoomNotAvailableException;
use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Exception\RoomNotFoundException;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Tests\Fake\FakeAsyncCommandDispatcher;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Fake\FakeTransactionManager;
use App\Tests\Reservation\Infrastructure\FakeBookerExistenceChecker;
use App\Tests\Reservation\Infrastructure\FakeCancellationPolicyFetcher;
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
    private FakeCancellationPolicyFetcher $cancellationPolicyFetcher;
    private FakeEventDispatcher $eventDispatcher;
    private FakeTransactionManager $transactionManager;
    private FakeAsyncCommandDispatcher $asyncDispatcher;
    private CreateReservationCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryReservationRepository();
        $this->roomExists = new FakeRoomExistenceChecker();
        $this->bookerExists = new FakeBookerExistenceChecker();
        $this->availabilityChecker = new FakeRoomAvailabilityChecker();
        $this->priceCalculator = new FakePriceCalculator();
        $this->cancellationPolicyFetcher = new FakeCancellationPolicyFetcher();
        $this->eventDispatcher = new FakeEventDispatcher();
        $this->transactionManager = new FakeTransactionManager();
        $this->asyncDispatcher = new FakeAsyncCommandDispatcher();

        $this->handler = new CreateReservationCommandHandler(
            $this->repository,
            $this->roomExists,
            $this->bookerExists,
            $this->availabilityChecker,
            $this->priceCalculator,
            $this->cancellationPolicyFetcher,
            $this->eventDispatcher,
            $this->transactionManager,
            $this->asyncDispatcher,
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
        self::assertNull($reservation->cancellationTerms->daysThreshold);

        $event = $this->eventDispatcher->getLastDispatched();
        self::assertInstanceOf(ReservationCreated::class, $event);
        self::assertSame(self::RESERVATION_ID, $event->reservationId);
        self::assertSame(self::ROOM_ID, $event->roomId);
        self::assertSame(self::BOOKER_ID, $event->bookerId);
        self::assertSame(42000, $event->totalPrice);
        self::assertNull($event->cancellationTerms->daysThreshold);
    }

    #[Test]
    public function itStoresCancellationTermsWithThresholdWhenPolicyIsSet(): void
    {
        $this->cancellationPolicyFetcher->setTerms(CancellationTerms::withThreshold(7));

        ($this->handler)($this->makeCommand());

        $reservation = $this->repository->get(self::RESERVATION_ID);
        self::assertNotNull($reservation);
        self::assertSame(7, $reservation->cancellationTerms->daysThreshold);

        $event = $this->eventDispatcher->getLastDispatched();
        self::assertInstanceOf(ReservationCreated::class, $event);
        self::assertSame(7, $event->cancellationTerms->daysThreshold);
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

    #[Test]
    public function itDispatchesDelayedExpireCommandAfterCreation(): void
    {
        ($this->handler)($this->makeCommand());

        $dispatched = $this->asyncDispatcher->getLastDispatched();
        self::assertInstanceOf(ExpireReservationCommand::class, $dispatched);
        self::assertSame(self::RESERVATION_ID, $dispatched->reservationId);
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
}
```

- [ ] **Step 3: Run unit tests**

```bash
make unit-test -- --filter CreateReservationCommandHandlerTest
```

Expected: all tests pass.

- [ ] **Step 4: Commit**

```bash
git add src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php \
        tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php
git commit -m "feat(reservation): inject CancellationPolicyFetcher into CreateReservationCommandHandler"
```

---

### Task 5: Update `ReservationRepository` + migration

**Files:**
- Modify: `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php`
- Create: new migration file (via `make generate-migration`, then fill in manually)

- [ ] **Step 1: Update the repository**

Replace `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Persistence\Doctrine;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use Doctrine\DBAL\Connection;

final readonly class ReservationRepository implements \App\Reservation\Domain\Port\ReservationRepositoryInterface
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
            'cancellation_terms_days_threshold' => $reservation->cancellationTerms->daysThreshold,
            'status' => $reservation->status->value,
            'created_at' => $reservation->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $id): ?Reservation
    {
        /** @var array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, cancellation_terms_days_threshold: int|string|null, status: string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, room_id, booker_id, check_in, check_out, total_price,
                    cancellation_terms_days_threshold, status, created_at
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
     * @param array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, cancellation_terms_days_threshold: int|string|null, status: string, created_at: string} $row
     */
    private function hydrate(array $row): Reservation
    {
        $cancellationTerms = null !== $row['cancellation_terms_days_threshold']
            ? CancellationTerms::withThreshold((int) $row['cancellation_terms_days_threshold'])
            : CancellationTerms::alwaysRefundable();

        $reservation = new Reservation(
            id: $row['id'],
            roomId: $row['room_id'],
            bookerId: $row['booker_id'],
            period: new DatePeriod(
                new \DateTimeImmutable($row['check_in']),
                new \DateTimeImmutable($row['check_out']),
            ),
            totalPrice: (int) $row['total_price'],
            cancellationTerms: $cancellationTerms,
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
        $reservation->status = ReservationStatus::from($row['status']);

        return $reservation;
    }
}
```

- [ ] **Step 2: Generate the migration stub**

```bash
make generate-migration
```

This creates a new `migrations/VersionYYYYMMDDHHmmss.php`. Open it and fill in the `up()` and `down()` methods:

```php
public function getDescription(): string
{
    return 'Add cancellation_terms_days_threshold to reservation table';
}

public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE reservation ADD COLUMN cancellation_terms_days_threshold INT NULL');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE reservation DROP COLUMN cancellation_terms_days_threshold');
}
```

- [ ] **Step 3: Run the migration**

```bash
make migrate
```

Expected: migration applied successfully.

- [ ] **Step 4: Run the full test suite**

```bash
make test
```

Expected: all tests pass (unit + integration + functional).

- [ ] **Step 5: Commit**

```bash
git add src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php \
        migrations/
git commit -m "feat(reservation): persist cancellation_terms_days_threshold on reservation table"
```

---

### Task 6: Update `ReservationSerializer` and `InMemoryReservationRepository`

**Files:**
- Modify: `src/Reservation/UI/Http/Controller/ReservationSerializer.php`
- Modify: `tests/Reservation/Infrastructure/Persistence/InMemory/InMemoryReservationRepository.php` (verify it still compiles — no change expected but confirm)

- [ ] **Step 1: Update the serializer**

Replace `src/Reservation/UI/Http/Controller/ReservationSerializer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller;

use App\Reservation\Domain\Model\Reservation;

final readonly class ReservationSerializer
{
    /**
     * @return array{id: string, roomId: string, bookerId: string, checkIn: string, checkOut: string, totalPrice: int, status: string, cancellationTerms: array{daysThreshold: int|null}, createdAt: string}
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
            'cancellationTerms' => [
                'daysThreshold' => $reservation->cancellationTerms->daysThreshold,
            ],
            'createdAt' => $reservation->createdAt
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
```

- [ ] **Step 2: Run the full test suite**

```bash
make test
```

Expected: all tests pass.

- [ ] **Step 3: Update OpenAPI spec**

```bash
make openapi
```

Verify `public/openapi.yaml` (or equivalent) now includes `cancellationTerms` in the reservation response schema. If `#[OA\Response]` annotations on the `GetReservation` or `CreateReservation` controllers reference a reservation schema, update them to include:

```php
new OA\Property(
    property: 'cancellationTerms',
    properties: [
        new OA\Property(property: 'daysThreshold', type: 'integer', nullable: true, example: 7),
    ],
    type: 'object',
),
```

- [ ] **Step 4: Commit**

```bash
git add src/Reservation/UI/Http/Controller/ReservationSerializer.php \
        public/openapi.yaml
git commit -m "feat(reservation): expose cancellationTerms in Reservation API response"
```

---

### Task 7: Final checks

- [ ] **Step 1: Run the complete test suite**

```bash
make test
```

Expected: all unit + integration + functional tests pass.

- [ ] **Step 2: Run linting and architecture checks**

```bash
make lint
```

This runs PHP CS Fixer and deptrac. Expected: no violations. In particular, confirm:
- `PricingCancellationPolicyFetcher` is in `Infrastructure\` — it may depend on Pricing
- `CancellationPolicyFetcherInterface` is in `Domain\Port\` — it depends on nothing outside Domain
- `CreateReservationCommandHandler` is in `Application\` — it only depends on the port interface, never on Pricing directly

- [ ] **Step 3: Run static analysis**

```bash
make phpstan
```

Expected: no errors.

- [ ] **Step 4: Commit if any CS Fixer auto-fixes were applied**

```bash
git add -p
git commit -m "chore: apply CS Fixer formatting"
```

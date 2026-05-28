# Guest Count + Capacity Validation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `guestCount` field to `CreateReservation`, validated against the `GuestCapacity` of the Room Type at creation time, and persisted on the `Reservation`.

**Architecture:** New domain value object `GuestCount` + domain exception `GuestCapacityExceededException` + domain port `RoomCapacityFetcherInterface`. The handler fetches capacity via the port (DBAL cross-context query on `room` + `room_type` tables) and throws if `guestCount > capacity`. The repository, serializer, and request DTO are all updated.

**Tech Stack:** PHP 8.4, Symfony 8.0, PostgreSQL (raw DBAL — no ORM), PHPUnit, Doctrine Migrations.

---

## File Map

| Action | File | Purpose |
|--------|------|---------|
| Create | `src/Reservation/Domain/ValueObject/GuestCount.php` | Validated int 1–20 |
| Create | `src/Reservation/Domain/Exception/GuestCapacityExceededException.php` | Domain exception |
| Create | `src/Reservation/Domain/Port/RoomCapacityFetcherInterface.php` | Port: fetch GuestCapacity from Room context |
| Create | `src/Reservation/Infrastructure/Service/RoomCapacityFetcher.php` | DBAL impl of the port |
| Create | `tests/Reservation/Infrastructure/FakeRoomCapacityFetcher.php` | Test double for the port |
| Create | `tests/Reservation/Domain/ValueObject/GuestCountTest.php` | Unit tests for VO |
| Modify | `src/Reservation/Domain/Model/Reservation.php` | Add `guestCount: GuestCount` readonly property |
| Modify | `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommand.php` | Add `guestCount: int` |
| Modify | `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php` | Inject port, add capacity check |
| Modify | `src/Reservation/Application/Service/CreateReservationCommandFactory.php` | Accept and forward `guestCount` |
| Modify | `src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationRequest.php` | Add `guestCount` field |
| Modify | `src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationController.php` | Pass `guestCount` to factory + update OA |
| Modify | `src/Reservation/UI/Http/Controller/ReservationSerializer.php` | Include `guestCount` in response |
| Modify | `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php` | Persist and hydrate `guest_count` column |
| Modify | `config/services/exceptions.yaml` | Map `GuestCapacityExceededException` → 422 |
| Generate | `migrations/VersionXXX.php` | Add `guest_count` column to `reservation` table |
| Modify | `tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php` | Add `guestCount` to command, add capacity exceeded test |
| Modify | `tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php` | Add `guestCount` to all requests, add 422 capacity test |

---

## Task 1: GuestCount value object + domain exception

**Files:**
- Create: `src/Reservation/Domain/ValueObject/GuestCount.php`
- Create: `src/Reservation/Domain/Exception/GuestCapacityExceededException.php`
- Create: `tests/Reservation/Domain/ValueObject/GuestCountTest.php`

- [ ] **Step 1: Write the failing tests**

```php
// tests/Reservation/Domain/ValueObject/GuestCountTest.php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\ValueObject;

use App\Reservation\Domain\ValueObject\GuestCount;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GuestCountTest extends TestCase
{
    #[Test]
    public function itCreatesAValidGuestCount(): void
    {
        $count = new GuestCount(3);

        self::assertSame(3, $count->value);
    }

    #[Test]
    public function itAcceptsMinimumValue(): void
    {
        $count = new GuestCount(1);

        self::assertSame(1, $count->value);
    }

    #[Test]
    public function itAcceptsMaximumValue(): void
    {
        $count = new GuestCount(20);

        self::assertSame(20, $count->value);
    }

    #[Test]
    public function itRejectsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new GuestCount(0);
    }

    #[Test]
    public function itRejectsAboveMaximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new GuestCount(21);
    }
}
```

- [ ] **Step 2: Run to verify RED**

```bash
docker compose exec unit-test vendor/bin/phpunit tests/Reservation/Domain/ValueObject/GuestCountTest.php
```

Expected: FAIL — class `GuestCount` not found.

- [ ] **Step 3: Create GuestCount**

```php
// src/Reservation/Domain/ValueObject/GuestCount.php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\ValueObject;

final readonly class GuestCount
{
    public function __construct(public int $value)
    {
        if ($value < 1 || $value > 20) {
            throw new \InvalidArgumentException(
                sprintf('Guest count must be between 1 and 20, got %d.', $value)
            );
        }
    }
}
```

- [ ] **Step 4: Create GuestCapacityExceededException**

```php
// src/Reservation/Domain/Exception/GuestCapacityExceededException.php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

final class GuestCapacityExceededException extends \DomainException
{
    public function __construct(int $guestCount, int $capacity)
    {
        parent::__construct(
            sprintf('Guest count %d exceeds room capacity of %d.', $guestCount, $capacity)
        );
    }
}
```

- [ ] **Step 5: Run to verify GREEN**

```bash
docker compose exec unit-test vendor/bin/phpunit tests/Reservation/Domain/ValueObject/GuestCountTest.php
```

Expected: PASS, 5 tests.

- [ ] **Step 6: Register exception in exceptions.yaml**

Add to `config/services/exceptions.yaml` under the `$map` entries:

```yaml
App\Reservation\Domain\Exception\GuestCapacityExceededException:
    type: 'https://book.it/problems/guest-capacity-exceeded'
    title: 'Guest Capacity Exceeded'
    status: 422
```

- [ ] **Step 7: Commit**

```bash
git add src/Reservation/Domain/ValueObject/GuestCount.php \
        src/Reservation/Domain/Exception/GuestCapacityExceededException.php \
        tests/Reservation/Domain/ValueObject/GuestCountTest.php \
        config/services/exceptions.yaml
git commit -m "feat(reservation): add GuestCount VO and GuestCapacityExceededException"
```

---

## Task 2: Domain port + test double

**Files:**
- Create: `src/Reservation/Domain/Port/RoomCapacityFetcherInterface.php`
- Create: `tests/Reservation/Infrastructure/FakeRoomCapacityFetcher.php`

- [ ] **Step 1: Create the port interface**

```php
// src/Reservation/Domain/Port/RoomCapacityFetcherInterface.php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

interface RoomCapacityFetcherInterface
{
    public function fetchCapacity(string $roomId): int;
}
```

- [ ] **Step 2: Create the fake**

```php
// tests/Reservation/Infrastructure/FakeRoomCapacityFetcher.php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Reservation\Domain\Port\RoomCapacityFetcherInterface;

final class FakeRoomCapacityFetcher implements RoomCapacityFetcherInterface
{
    private int $capacity = 10;

    public function setCapacity(int $capacity): void
    {
        $this->capacity = $capacity;
    }

    public function fetchCapacity(string $roomId): int
    {
        return $this->capacity;
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Reservation/Domain/Port/RoomCapacityFetcherInterface.php \
        tests/Reservation/Infrastructure/FakeRoomCapacityFetcher.php
git commit -m "feat(reservation): add RoomCapacityFetcherInterface and fake"
```

---

## Task 3: Application layer — Command + Handler + Reservation model

**Files:**
- Modify: `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommand.php`
- Modify: `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php`
- Modify: `src/Reservation/Domain/Model/Reservation.php`
- Modify: `tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php`

- [ ] **Step 1: Write the failing tests**

Update the test file. The `makeCommand()` helper gets a `guestCount` param. Two new tests: capacity exceeded, and guestCount is stored on the reservation.

Replace the full content of `tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Application\UseCase\CreateReservation;

use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommand;
use App\Reservation\Application\UseCase\CreateReservation\CreateReservationCommandHandler;
use App\Reservation\Application\UseCase\ExpireReservation\ExpireReservationCommand;
use App\Reservation\Domain\Event\ReservationCreated;
use App\Reservation\Domain\Exception\BookerNotFoundException;
use App\Reservation\Domain\Exception\GuestCapacityExceededException;
use App\Reservation\Domain\Exception\RoomNotAvailableException;
use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Exception\RoomNotFoundException;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\NightPrice;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Reservation\Domain\ValueObject\PricingQuoteSnapshot;
use App\Tests\Fake\FakeAsyncCommandDispatcher;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Fake\FakeTransactionManager;
use App\Tests\Reservation\Infrastructure\FakeBookerExistenceChecker;
use App\Tests\Reservation\Infrastructure\FakeCancellationPolicyFetcher;
use App\Tests\Reservation\Infrastructure\FakePricingQuoteFetcher;
use App\Tests\Reservation\Infrastructure\FakeRoomAvailabilityChecker;
use App\Tests\Reservation\Infrastructure\FakeRoomCapacityFetcher;
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
    private FakeRoomCapacityFetcher $roomCapacityFetcher;
    private FakePricingQuoteFetcher $pricingQuoteFetcher;
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
        $this->roomCapacityFetcher = new FakeRoomCapacityFetcher();
        $this->pricingQuoteFetcher = new FakePricingQuoteFetcher();
        $this->cancellationPolicyFetcher = new FakeCancellationPolicyFetcher();
        $this->eventDispatcher = new FakeEventDispatcher();
        $this->transactionManager = new FakeTransactionManager();
        $this->asyncDispatcher = new FakeAsyncCommandDispatcher();

        $this->handler = new CreateReservationCommandHandler(
            $this->repository,
            $this->roomExists,
            $this->bookerExists,
            $this->availabilityChecker,
            $this->roomCapacityFetcher,
            $this->pricingQuoteFetcher,
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
        self::assertSame(2, $reservation->guestCount->value);
        self::assertSame(ReservationStatus::Pending, $reservation->status);
        self::assertNull($reservation->cancellationTerms->daysThreshold);
        self::assertCount(4, $reservation->priceBreakdown->nights);
        self::assertSame('2026-06-01', $reservation->priceBreakdown->nights[0]->date);

        $event = $this->eventDispatcher->getLastDispatched();
        self::assertInstanceOf(ReservationCreated::class, $event);
        self::assertSame(self::RESERVATION_ID, $event->reservationId);
        self::assertSame(self::ROOM_ID, $event->roomId);
        self::assertSame(self::BOOKER_ID, $event->bookerId);
        self::assertSame(42000, $event->totalPrice);
        self::assertNull($event->cancellationTerms->daysThreshold);
        self::assertCount(4, $event->priceBreakdown->nights);
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
    public function itStoresPriceBreakdownFromQuote(): void
    {
        $this->pricingQuoteFetcher->setSnapshot(new PricingQuoteSnapshot(
            19000,
            new PriceBreakdown([
                new NightPrice('2026-06-01', 10000, null, 10000),
                new NightPrice('2026-06-02', 10000, 10, 9000),
            ]),
        ));

        ($this->handler)($this->makeCommand());

        $reservation = $this->repository->get(self::RESERVATION_ID);
        self::assertNotNull($reservation);
        self::assertSame(19000, $reservation->totalPrice);
        self::assertCount(2, $reservation->priceBreakdown->nights);
        self::assertSame(10000, $reservation->priceBreakdown->nights[0]->effectiveAmountCents);
        self::assertSame(10, $reservation->priceBreakdown->nights[1]->discountPercent);
        self::assertSame(9000, $reservation->priceBreakdown->nights[1]->effectiveAmountCents);

        $event = $this->eventDispatcher->getLastDispatched();
        self::assertInstanceOf(ReservationCreated::class, $event);
        self::assertSame(19000, $event->totalPrice);
        self::assertCount(2, $event->priceBreakdown->nights);
    }

    #[Test]
    public function itAcceptsZeroPrice(): void
    {
        $this->pricingQuoteFetcher->setSnapshot(
            new PricingQuoteSnapshot(0, new PriceBreakdown([])),
        );

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
    public function itThrowsWhenGuestCountExceedsRoomCapacity(): void
    {
        $this->roomCapacityFetcher->setCapacity(1);
        $this->expectException(GuestCapacityExceededException::class);

        ($this->handler)($this->makeCommand(guestCount: 2));
    }

    #[Test]
    public function itThrowsWhenRoomHasNoPricing(): void
    {
        $this->pricingQuoteFetcher->setSnapshot(null);
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

    private function makeCommand(string $id = self::RESERVATION_ID, int $guestCount = 2): CreateReservationCommand
    {
        return new CreateReservationCommand(
            id: $id,
            roomId: self::ROOM_ID,
            bookerId: self::BOOKER_ID,
            checkIn: new \DateTimeImmutable('2026-06-01'),
            checkOut: new \DateTimeImmutable('2026-06-05'),
            guestCount: $guestCount,
            createdAt: new \DateTimeImmutable('2026-05-18T10:00:00Z'),
        );
    }
}
```

- [ ] **Step 2: Run to verify RED**

```bash
docker compose exec unit-test vendor/bin/phpunit tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php
```

Expected: FAIL — `CreateReservationCommand` has no `guestCount` parameter.

- [ ] **Step 3: Update CreateReservationCommand**

```php
// src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommand.php
<?php

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
        public int $guestCount,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 4: Update Reservation domain model**

```php
// src/Reservation/Domain/Model/Reservation.php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

use App\Reservation\Domain\Exception\InvalidReservationTransitionException;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;

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
        public readonly PriceBreakdown $priceBreakdown,
        public readonly GuestCount $guestCount,
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

    public function confirm(): void
    {
        if (ReservationStatus::Pending !== $this->status) {
            throw new InvalidReservationTransitionException($this->status, ReservationStatus::Confirmed);
        }

        $this->status = ReservationStatus::Confirmed;
    }

    public function cancelPending(): void
    {
        if (ReservationStatus::Pending !== $this->status) {
            throw new InvalidReservationTransitionException($this->status, ReservationStatus::Cancelled);
        }

        $this->status = ReservationStatus::Cancelled;
    }
}
```

- [ ] **Step 5: Update CreateReservationCommandHandler**

```php
// src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CreateReservation;

use App\Reservation\Application\UseCase\ExpireReservation\ExpireReservationCommand;
use App\Reservation\Domain\Event\ReservationCreated;
use App\Reservation\Domain\Exception\BookerNotFoundException;
use App\Reservation\Domain\Exception\GuestCapacityExceededException;
use App\Reservation\Domain\Exception\RoomNotAvailableException;
use App\Reservation\Domain\Exception\RoomNotFoundException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Port\BookerExistsInterface;
use App\Reservation\Domain\Port\CancellationPolicyFetcherInterface;
use App\Reservation\Domain\Port\PricingQuoteFetcherInterface;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;
use App\Reservation\Domain\Port\RoomCapacityFetcherInterface;
use App\Reservation\Domain\Port\RoomExistsInterface;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
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
        if (!$this->roomExists->exists($command->roomId)) {
            throw new RoomNotFoundException($command->roomId);
        }

        if (!$this->bookerExists->exists($command->bookerId)) {
            throw new BookerNotFoundException($command->bookerId);
        }

        if (!$this->availabilityChecker->isAvailable($command->roomId, $command->checkIn, $command->checkOut)) {
            throw new RoomNotAvailableException($command->roomId);
        }

        $capacity = $this->roomCapacityFetcher->fetchCapacity($command->roomId);
        if ($command->guestCount > $capacity) {
            throw new GuestCapacityExceededException($command->guestCount, $capacity);
        }

        $pricingQuote = $this->pricingQuoteFetcher->fetch($command->roomId, $command->checkIn, $command->checkOut);
        $cancellationTerms = $this->cancellationPolicyFetcher->fetch($command->roomId);

        $reservation = new Reservation(
            id: $command->id,
            roomId: $command->roomId,
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
                roomId: $reservation->roomId,
                bookerId: $reservation->bookerId,
                checkIn: $reservation->period->checkIn,
                checkOut: $reservation->period->checkOut,
                totalPrice: $reservation->totalPrice,
                cancellationTerms: $reservation->cancellationTerms,
                priceBreakdown: $reservation->priceBreakdown,
            ));
        });

        $this->asyncDispatcher->dispatch(
            new ExpireReservationCommand($reservation->id),
            900_000,
        );
    }
}
```

- [ ] **Step 6: Run to verify GREEN**

```bash
docker compose exec unit-test vendor/bin/phpunit tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php
```

Expected: PASS, all tests green.

- [ ] **Step 7: Run full unit suite to catch regressions**

```bash
docker compose exec unit-test vendor/bin/phpunit --group unit
```

Expected: PASS. If `InMemoryReservationRepository` hydrates a `Reservation`, update it to pass `guestCount: new GuestCount(1)` as a default.

- [ ] **Step 8: Commit**

```bash
git add src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommand.php \
        src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php \
        src/Reservation/Domain/Model/Reservation.php \
        tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php
git commit -m "feat(reservation): add guestCount to command, handler capacity check, and Reservation model"
```

---

## Task 4: Persistence — Repository + Migration

**Files:**
- Modify: `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php`
- Generate: `migrations/VersionXXX.php`

- [ ] **Step 1: Update ReservationRepository**

```php
// src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php
<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Persistence\Doctrine;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
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
            'guest_count' => $reservation->guestCount->value,
            'cancellation_terms_days_threshold' => $reservation->cancellationTerms->daysThreshold,
            'price_breakdown' => json_encode($reservation->priceBreakdown->toArray()) ?: '[]',
            'status' => $reservation->status->value,
            'created_at' => $reservation->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function save(Reservation $reservation): void
    {
        $this->bookit->update('reservation', ['status' => $reservation->status->value], ['id' => $reservation->id]);
    }

    public function get(string $id): ?Reservation
    {
        /** @var array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, guest_count: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, room_id, booker_id, check_in, check_out, total_price, guest_count,
                    cancellation_terms_days_threshold, price_breakdown, status, created_at
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
     * @param array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, guest_count: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string} $row
     */
    private function hydrate(array $row): Reservation
    {
        $threshold = $row['cancellation_terms_days_threshold'];
        $cancellationTerms = null !== $threshold
            ? CancellationTerms::withThreshold((int) $threshold)
            : CancellationTerms::alwaysRefundable();

        /** @var list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}> $nights */
        $nights = json_decode($row['price_breakdown'], true);
        $priceBreakdown = PriceBreakdown::fromArray($nights);

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
            priceBreakdown: $priceBreakdown,
            guestCount: new GuestCount((int) $row['guest_count']),
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
        $reservation->status = ReservationStatus::from($row['status']);

        return $reservation;
    }
}
```

- [ ] **Step 2: Generate migration boilerplate**

```bash
docker compose exec php make generate-migration
```

Note the generated filename (e.g., `migrations/Version20260528XXXXXX.php`).

- [ ] **Step 3: Write migration SQL**

Open the generated file and replace the `up()` and `down()` bodies:

```php
public function getDescription(): string
{
    return 'Add guest_count column to reservation table';
}

public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE reservation ADD COLUMN guest_count SMALLINT NOT NULL DEFAULT 1');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE reservation DROP COLUMN guest_count');
}
```

- [ ] **Step 4: Run migration**

```bash
docker compose exec php make migrate
```

Expected: migration applied successfully.

- [ ] **Step 5: Commit**

```bash
git add src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php \
        migrations/
git commit -m "feat(reservation): persist guest_count in reservation table"
```

---

## Task 5: Infrastructure — RoomCapacityFetcher

**Files:**
- Create: `src/Reservation/Infrastructure/Service/RoomCapacityFetcher.php`

The `Reservation` infrastructure layer is auto-scanned via `resource: '../../src/Reservation/Infrastructure/'` in `config/services/reservation.yaml`. Since there is exactly one implementation of `RoomCapacityFetcherInterface`, Symfony's autowiring will resolve it automatically — no explicit YAML entry needed.

- [ ] **Step 1: Create RoomCapacityFetcher**

```php
// src/Reservation/Infrastructure/Service/RoomCapacityFetcher.php
<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\RoomCapacityFetcherInterface;
use Doctrine\DBAL\Connection;

final readonly class RoomCapacityFetcher implements RoomCapacityFetcherInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function fetchCapacity(string $roomId): int
    {
        $capacity = $this->bookit->fetchOne(
            'SELECT rt.guest_capacity
               FROM room r
               JOIN room_type rt ON rt.id = r.room_type_id
              WHERE r.id = :roomId',
            ['roomId' => $roomId],
        );

        return (int) $capacity;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Reservation/Infrastructure/Service/RoomCapacityFetcher.php
git commit -m "feat(reservation): implement RoomCapacityFetcher via DBAL"
```

---

## Task 6: UI layer + functional test

**Files:**
- Modify: `src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationRequest.php`
- Modify: `src/Reservation/Application/Service/CreateReservationCommandFactory.php`
- Modify: `src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationController.php`
- Modify: `src/Reservation/UI/Http/Controller/ReservationSerializer.php`
- Modify: `tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php`

- [ ] **Step 1: Write the failing functional tests**

Replace the full content of `tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php`:

```php
<?php

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
        [$roomId, $bookerId] = $this->setupRoomAndBooker($client, guestCapacity: 3);
        $this->setBaseRate($client, $roomId, 10000);

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomId' => $roomId,
                'bookerId' => $bookerId,
                'checkIn' => '2030-06-01',
                'checkOut' => '2030-06-05',
                'guestCount' => 2,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{id: string, roomId: string, bookerId: string, checkIn: string, checkOut: string, totalPrice: int, status: string, guestCount: int, createdAt: string, cancellationTerms: array{daysThreshold: int|null}, priceBreakdown: list<mixed>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['id']);
        self::assertSame($roomId, $body['roomId']);
        self::assertSame($bookerId, $body['bookerId']);
        self::assertSame('2030-06-01', $body['checkIn']);
        self::assertSame('2030-06-05', $body['checkOut']);
        self::assertSame(40000, $body['totalPrice']); // 4 nights × 10000
        self::assertSame('pending', $body['status']);
        self::assertSame(2, $body['guestCount']);
        self::assertNotEmpty($body['createdAt']);
        self::assertNull($body['cancellationTerms']['daysThreshold']);
        self::assertNotEmpty($body['priceBreakdown']);
        $firstNight = $body['priceBreakdown'][0];
        self::assertIsArray($firstNight);
        self::assertArrayHasKey('date', $firstNight);
        self::assertArrayHasKey('rateAmountCents', $firstNight);
        self::assertArrayHasKey('discountPercent', $firstNight);
        self::assertArrayHasKey('effectiveAmountCents', $firstNight);
    }

    #[Test]
    public function itReturns422WhenGuestCountExceedsRoomCapacity(): void
    {
        $client = static::createClient();
        [$roomId, $bookerId] = $this->setupRoomAndBooker($client, guestCapacity: 1);
        $this->setBaseRate($client, $roomId, 10000);

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomId' => $roomId,
                'bookerId' => $bookerId,
                'checkIn' => '2030-06-01',
                'checkOut' => '2030-06-05',
                'guestCount' => 2,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/guest-capacity-exceeded', $body['type']);
    }

    #[Test]
    public function itReturns404WhenRoomDoesNotExist(): void
    {
        $client = static::createClient();
        [, $bookerId] = $this->setupRoomAndBooker($client);

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomId' => '00000000-0000-4000-8000-000000000001',
                'bookerId' => $bookerId,
                'checkIn' => '2030-06-01',
                'checkOut' => '2030-06-05',
                'guestCount' => 1,
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
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomId' => $roomId,
                'bookerId' => '00000000-0000-4000-8000-000000000002',
                'checkIn' => '2030-06-01',
                'checkOut' => '2030-06-05',
                'guestCount' => 1,
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
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomId' => $roomId,
                'bookerId' => $bookerId,
                'checkIn' => '2030-06-03',
                'checkOut' => '2030-06-07',
                'guestCount' => 1,
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
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomId' => $roomId,
                'bookerId' => $bookerId,
                'checkIn' => '2030-06-01',
                'checkOut' => '2030-06-05',
                'guestCount' => 1,
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
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['roomId' => 'not-a-uuid', 'checkIn' => '2030-06-01'], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    /** @return array{string, string} [roomId, bookerId] */
    private function setupRoomAndBooker(KernelBrowser $client, int $guestCapacity = 2): array
    {
        $client->request(
            method: 'POST',
            uri: '/api/v1/hotels',
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
            uri: "/api/v1/hotels/{$hotelBody['id']}/room-types",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Standard',
                'livingSpaceCount' => 1,
                'guestCapacity' => $guestCapacity,
                'isAccessible' => false,
                'bedComposition' => [['type' => 'double', 'count' => 1]],
            ], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $roomTypeBody */
        $roomTypeBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelBody['id']}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1, 'roomTypeId' => $roomTypeBody['id']], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $roomBody */
        $roomBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request(
            method: 'POST',
            uri: '/api/v1/bookers',
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
            uri: "/api/v1/rooms/{$roomId}/base-rate",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amount' => $amountCents / 100], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    private function blockPeriod(KernelBrowser $client, string $roomId, string $checkIn, string $checkOut): void
    {
        $client->request(
            method: 'POST',
            uri: "/api/v1/rooms/{$roomId}/blocked-periods",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['checkIn' => $checkIn, 'checkOut' => $checkOut], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    }
}
```

- [ ] **Step 2: Run to verify RED**

```bash
docker compose exec php vendor/bin/phpunit tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php
```

Expected: FAIL — request payload missing `guestCount`, factory not updated.

- [ ] **Step 3: Update CreateReservationRequest**

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
        #[Assert\NotNull]
        #[Assert\Range(min: 1, max: 20)]
        #[OA\Property(type: 'integer', example: 2, minimum: 1, maximum: 20)]
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

- [ ] **Step 4: Update CreateReservationCommandFactory**

```php
// src/Reservation/Application/Service/CreateReservationCommandFactory.php
<?php

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
        int $guestCount,
    ): CreateReservationCommand {
        return new CreateReservationCommand(
            id: $this->idGenerator->generate(),
            roomId: $roomId,
            bookerId: $bookerId,
            checkIn: new \DateTimeImmutable($checkIn),
            checkOut: new \DateTimeImmutable($checkOut),
            guestCount: $guestCount,
            createdAt: new \DateTimeImmutable(),
        );
    }
}
```

- [ ] **Step 5: Update CreateReservationController**

In `src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationController.php`, update the `__invoke` method body and add `guestCount` to the OA response properties:

```php
public function __invoke(
    #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] CreateReservationRequest $request,
): Response {
    $command = $this->commandFactory->create(
        (string) $request->roomId,
        (string) $request->bookerId,
        (string) $request->checkIn,
        (string) $request->checkOut,
        (int) $request->guestCount,
    );
    $this->commandBus->execute($command);

    $reservation = $this->queryBus->ask(new GetReservationQuery($command->id));
    if (null === $reservation) {
        throw new NotFoundHttpException();
    }

    return new JsonResponse($this->serializer->serialize($reservation), Response::HTTP_CREATED);
}
```

Also add `new OA\Property(property: 'guestCount', type: 'integer', example: 2)` to the OA response properties list (after `totalPrice`). Also add a `422` response entry for capacity exceeded:

```php
new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'No pricing, capacity exceeded, or validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
```

- [ ] **Step 6: Update ReservationSerializer**

```php
// src/Reservation/UI/Http/Controller/ReservationSerializer.php
<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller;

use App\Reservation\Domain\Model\Reservation;

final readonly class ReservationSerializer
{
    /**
     * @return array{
     *     id: string,
     *     roomId: string,
     *     bookerId: string,
     *     checkIn: string,
     *     checkOut: string,
     *     totalPrice: int,
     *     guestCount: int,
     *     status: string,
     *     cancellationTerms: array{daysThreshold: int|null},
     *     priceBreakdown: list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}>,
     *     createdAt: string
     * }
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
            'guestCount' => $reservation->guestCount->value,
            'status' => $reservation->status->value,
            'cancellationTerms' => [
                'daysThreshold' => $reservation->cancellationTerms->daysThreshold,
            ],
            'priceBreakdown' => $reservation->priceBreakdown->toArray(),
            'createdAt' => $reservation->createdAt
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
```

- [ ] **Step 7: Run to verify GREEN**

```bash
docker compose exec php vendor/bin/phpunit tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php
```

Expected: PASS, all tests green.

- [ ] **Step 8: Run full test suite**

```bash
docker compose exec unit-test vendor/bin/phpunit --group unit && \
docker compose exec php vendor/bin/phpunit --group functional
```

Expected: all green.

- [ ] **Step 9: Run linters**

```bash
docker compose exec php make lint
```

Expected: no errors.

- [ ] **Step 10: Regenerate OpenAPI spec**

```bash
docker compose exec php make openapi
```

- [ ] **Step 11: Commit**

```bash
git add src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationRequest.php \
        src/Reservation/Application/Service/CreateReservationCommandFactory.php \
        src/Reservation/UI/Http/Controller/CreateReservation/CreateReservationController.php \
        src/Reservation/UI/Http/Controller/ReservationSerializer.php \
        tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php \
        public/openapi.yaml
git commit -m "feat(reservation): expose guestCount in API request and response"
```

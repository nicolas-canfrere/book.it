# Price Breakdown Snapshot on Reservation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Capture a `PriceBreakdown` snapshot (the per-night detail from `GetPricingQuote`) on every `Reservation` at creation time, persist it as JSONB, expose it in `ReservationCreated`, and expose it in the API response.

**Architecture:** Three new value objects are added to the Reservation domain — `NightPrice`, `PriceBreakdown`, and `PricingQuoteSnapshot` (transport object). The existing `PriceCalculatorInterface` port is renamed to `PricingQuoteFetcherInterface` and now returns a `PricingQuoteSnapshot` carrying both the total and the breakdown, eliminating the second cross-context call. Persistence is raw DBAL; a migration adds a `price_breakdown JSONB NOT NULL` column. Symfony autowiring resolves the new port automatically — no DI config changes required.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL (raw SQL + JSONB), PHPUnit, Symfony Messenger query bus

---

## File Map

| Action | Path | Purpose |
|---|---|---|
| Create | `src/Reservation/Domain/ValueObject/NightPrice.php` | Immutable VO: one night's pricing detail |
| Create | `src/Reservation/Domain/ValueObject/PriceBreakdown.php` | VO: ordered list of NightPrices + array serialization |
| Create | `src/Reservation/Domain/ValueObject/PricingQuoteSnapshot.php` | Transport VO: total + breakdown returned by the port |
| Create | `src/Reservation/Domain/Port/PricingQuoteFetcherInterface.php` | New port replacing PriceCalculatorInterface |
| Delete | `src/Reservation/Domain/Port/PriceCalculatorInterface.php` | Replaced by PricingQuoteFetcherInterface |
| Create | `src/Reservation/Infrastructure/Service/PricingQuoteFetcher.php` | Adapter: crosses to Pricing query bus, builds PricingQuoteSnapshot |
| Delete | `src/Reservation/Infrastructure/Service/PricingCalculator.php` | Replaced by PricingQuoteFetcher |
| Create | `tests/Reservation/Domain/ValueObject/NightPriceTest.php` | Unit tests for NightPrice |
| Create | `tests/Reservation/Domain/ValueObject/PriceBreakdownTest.php` | Unit tests for PriceBreakdown (array round-trip) |
| Create | `tests/Reservation/Infrastructure/FakePricingQuoteFetcher.php` | Test double for the new port |
| Delete | `tests/Reservation/Infrastructure/FakePriceCalculator.php` | Replaced by FakePricingQuoteFetcher |
| Create | `tests/Reservation/Infrastructure/Service/PricingQuoteFetcherTest.php` | Unit test for the adapter |
| Modify | `src/Reservation/Domain/Model/Reservation.php` | Add `priceBreakdown: PriceBreakdown` property |
| Modify | `src/Reservation/Domain/Event/ReservationCreated.php` | Add `priceBreakdown: PriceBreakdown` |
| Modify | `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php` | Replace price calculator with quote fetcher |
| Modify | `tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php` | Use FakePricingQuoteFetcher, assert priceBreakdown |
| Modify | `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php` | Encode/decode JSONB column |
| Create | `migrations/Version2026XXXXXX.php` | Add price_breakdown JSONB NOT NULL column |
| Modify | `src/Reservation/UI/Http/Controller/ReservationSerializer.php` | Serialize priceBreakdown |

---

### Task 1: Value objects — `NightPrice`, `PriceBreakdown`, `PricingQuoteSnapshot`

**Files:**
- Create: `src/Reservation/Domain/ValueObject/NightPrice.php`
- Create: `src/Reservation/Domain/ValueObject/PriceBreakdown.php`
- Create: `src/Reservation/Domain/ValueObject/PricingQuoteSnapshot.php`
- Create: `tests/Reservation/Domain/ValueObject/NightPriceTest.php`
- Create: `tests/Reservation/Domain/ValueObject/PriceBreakdownTest.php`

- [ ] **Step 1: Write the failing tests**

```php
// tests/Reservation/Domain/ValueObject/NightPriceTest.php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\ValueObject;

use App\Reservation\Domain\ValueObject\NightPrice;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class NightPriceTest extends TestCase
{
    #[Test]
    public function itStoresAllFields(): void
    {
        $night = new NightPrice('2026-06-01', 10000, 10, 9000);

        self::assertSame('2026-06-01', $night->date);
        self::assertSame(10000, $night->rateAmountCents);
        self::assertSame(10, $night->discountPercent);
        self::assertSame(9000, $night->effectiveAmountCents);
    }

    #[Test]
    public function itAcceptsNullDiscount(): void
    {
        $night = new NightPrice('2026-06-01', 10000, null, 10000);

        self::assertNull($night->discountPercent);
    }
}
```

```php
// tests/Reservation/Domain/ValueObject/PriceBreakdownTest.php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\ValueObject;

use App\Reservation\Domain\ValueObject\NightPrice;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PriceBreakdownTest extends TestCase
{
    #[Test]
    public function itRoundTripsToAndFromArray(): void
    {
        $original = new PriceBreakdown([
            new NightPrice('2026-06-01', 10000, null, 10000),
            new NightPrice('2026-06-02', 10000, 10, 9000),
        ]);

        $restored = PriceBreakdown::fromArray($original->toArray());

        self::assertCount(2, $restored->nights);
        self::assertSame('2026-06-01', $restored->nights[0]->date);
        self::assertSame(10000, $restored->nights[0]->rateAmountCents);
        self::assertNull($restored->nights[0]->discountPercent);
        self::assertSame(10000, $restored->nights[0]->effectiveAmountCents);
        self::assertSame('2026-06-02', $restored->nights[1]->date);
        self::assertSame(10, $restored->nights[1]->discountPercent);
        self::assertSame(9000, $restored->nights[1]->effectiveAmountCents);
    }

    #[Test]
    public function toArrayReturnsExpectedShape(): void
    {
        $breakdown = new PriceBreakdown([
            new NightPrice('2026-06-01', 10000, null, 10000),
        ]);

        $array = $breakdown->toArray();

        self::assertSame([
            [
                'date' => '2026-06-01',
                'rateAmountCents' => 10000,
                'discountPercent' => null,
                'effectiveAmountCents' => 10000,
            ],
        ], $array);
    }

    #[Test]
    public function itHandlesEmptyBreakdown(): void
    {
        $breakdown = PriceBreakdown::fromArray([]);

        self::assertSame([], $breakdown->nights);
        self::assertSame([], $breakdown->toArray());
    }
}
```

- [ ] **Step 2: Run the tests to confirm they fail**

```bash
make unit-test -- --filter "NightPriceTest|PriceBreakdownTest"
```

Expected: class not found errors.

- [ ] **Step 3: Implement the three value objects**

```php
// src/Reservation/Domain/ValueObject/NightPrice.php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\ValueObject;

final readonly class NightPrice
{
    public function __construct(
        public string $date,
        public int $rateAmountCents,
        public ?int $discountPercent,
        public int $effectiveAmountCents,
    ) {
    }
}
```

```php
// src/Reservation/Domain/ValueObject/PriceBreakdown.php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\ValueObject;

final readonly class PriceBreakdown
{
    /** @param list<NightPrice> $nights */
    public function __construct(
        public array $nights,
    ) {
    }

    /**
     * @param list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            array_map(
                static fn (array $n) => new NightPrice(
                    $n['date'],
                    $n['rateAmountCents'],
                    $n['discountPercent'],
                    $n['effectiveAmountCents'],
                ),
                $data,
            ),
        );
    }

    /**
     * @return list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (NightPrice $n) => [
                'date' => $n->date,
                'rateAmountCents' => $n->rateAmountCents,
                'discountPercent' => $n->discountPercent,
                'effectiveAmountCents' => $n->effectiveAmountCents,
            ],
            $this->nights,
        );
    }
}
```

```php
// src/Reservation/Domain/ValueObject/PricingQuoteSnapshot.php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\ValueObject;

final readonly class PricingQuoteSnapshot
{
    public function __construct(
        public int $totalAmountCents,
        public PriceBreakdown $breakdown,
    ) {
    }
}
```

- [ ] **Step 4: Run the tests to confirm they pass**

```bash
make unit-test -- --filter "NightPriceTest|PriceBreakdownTest"
```

Expected: 5 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Reservation/Domain/ValueObject/NightPrice.php \
        src/Reservation/Domain/ValueObject/PriceBreakdown.php \
        src/Reservation/Domain/ValueObject/PricingQuoteSnapshot.php \
        tests/Reservation/Domain/ValueObject/NightPriceTest.php \
        tests/Reservation/Domain/ValueObject/PriceBreakdownTest.php
git commit -m "feat(reservation): add NightPrice, PriceBreakdown, and PricingQuoteSnapshot value objects"
```

---

### Task 2: New port + adapter + test double (replace `PriceCalculatorInterface`)

**Files:**
- Create: `src/Reservation/Domain/Port/PricingQuoteFetcherInterface.php`
- Delete: `src/Reservation/Domain/Port/PriceCalculatorInterface.php`
- Create: `src/Reservation/Infrastructure/Service/PricingQuoteFetcher.php`
- Delete: `src/Reservation/Infrastructure/Service/PricingCalculator.php`
- Create: `tests/Reservation/Infrastructure/FakePricingQuoteFetcher.php`
- Delete: `tests/Reservation/Infrastructure/FakePriceCalculator.php`
- Create: `tests/Reservation/Infrastructure/Service/PricingQuoteFetcherTest.php`

- [ ] **Step 1: Create the new port interface**

```php
// src/Reservation/Domain/Port/PricingQuoteFetcherInterface.php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\ValueObject\PricingQuoteSnapshot;

interface PricingQuoteFetcherInterface
{
    /**
     * @throws RoomNotBookableException when no base rate is configured for the room
     */
    public function fetch(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteSnapshot;
}
```

- [ ] **Step 2: Create the test double**

```php
// tests/Reservation/Infrastructure/FakePricingQuoteFetcher.php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure;

use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Port\PricingQuoteFetcherInterface;
use App\Reservation\Domain\ValueObject\NightPrice;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Reservation\Domain\ValueObject\PricingQuoteSnapshot;

final class FakePricingQuoteFetcher implements PricingQuoteFetcherInterface
{
    private ?PricingQuoteSnapshot $snapshot;

    public function __construct()
    {
        $this->snapshot = new PricingQuoteSnapshot(
            42000,
            new PriceBreakdown([
                new NightPrice('2026-06-01', 10500, null, 10500),
                new NightPrice('2026-06-02', 10500, null, 10500),
                new NightPrice('2026-06-03', 10500, null, 10500),
                new NightPrice('2026-06-04', 10500, null, 10500),
            ]),
        );
    }

    public function setSnapshot(?PricingQuoteSnapshot $snapshot): void
    {
        $this->snapshot = $snapshot;
    }

    public function fetch(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteSnapshot
    {
        if (null === $this->snapshot) {
            throw new RoomNotBookableException($roomId);
        }

        return $this->snapshot;
    }
}
```

- [ ] **Step 3: Write the failing adapter test**

```php
// tests/Reservation/Infrastructure/Service/PricingQuoteFetcherTest.php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Service;

use App\Pricing\Application\UseCase\GetPricingQuote\GetPricingQuoteQuery;
use App\Pricing\Domain\Exception\RoomHasNoBaseRateException;
use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Infrastructure\Service\PricingQuoteFetcher;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PricingQuoteFetcherTest extends TestCase
{
    private const string ROOM_ID = '550e8400-e29b-41d4-a716-446655440001';

    #[Test]
    public function itBuildsSnapshotFromPricingQuote(): void
    {
        $queryBus = $this->createMock(SyncQueryBusInterface::class);
        $queryBus->method('ask')->willReturn([
            'roomId' => self::ROOM_ID,
            'checkIn' => '2026-06-01',
            'checkOut' => '2026-06-03',
            'totalAmountCents' => 19000,
            'nights' => [
                ['date' => '2026-06-01', 'rateAmountCents' => 10000, 'discountPercent' => null, 'effectiveAmountCents' => 10000],
                ['date' => '2026-06-02', 'rateAmountCents' => 10000, 'discountPercent' => 10, 'effectiveAmountCents' => 9000],
            ],
        ]);

        $fetcher = new PricingQuoteFetcher($queryBus);
        $snapshot = $fetcher->fetch(self::ROOM_ID, new \DateTimeImmutable('2026-06-01'), new \DateTimeImmutable('2026-06-03'));

        self::assertSame(19000, $snapshot->totalAmountCents);
        self::assertCount(2, $snapshot->breakdown->nights);
        self::assertSame('2026-06-01', $snapshot->breakdown->nights[0]->date);
        self::assertSame(10000, $snapshot->breakdown->nights[0]->rateAmountCents);
        self::assertNull($snapshot->breakdown->nights[0]->discountPercent);
        self::assertSame(10000, $snapshot->breakdown->nights[0]->effectiveAmountCents);
        self::assertSame('2026-06-02', $snapshot->breakdown->nights[1]->date);
        self::assertSame(10, $snapshot->breakdown->nights[1]->discountPercent);
        self::assertSame(9000, $snapshot->breakdown->nights[1]->effectiveAmountCents);
    }

    #[Test]
    public function itThrowsRoomNotBookableWhenNoBaseRate(): void
    {
        $queryBus = $this->createMock(SyncQueryBusInterface::class);
        $queryBus->method('ask')->willThrowException(
            new RoomHasNoBaseRateException(self::ROOM_ID),
        );

        $fetcher = new PricingQuoteFetcher($queryBus);

        $this->expectException(RoomNotBookableException::class);

        $fetcher->fetch(self::ROOM_ID, new \DateTimeImmutable('2026-06-01'), new \DateTimeImmutable('2026-06-03'));
    }
}
```

- [ ] **Step 4: Run the test to confirm it fails**

```bash
make unit-test -- --filter PricingQuoteFetcherTest
```

Expected: class not found error.

- [ ] **Step 5: Implement the adapter**

```php
// src/Reservation/Infrastructure/Service/PricingQuoteFetcher.php
<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Pricing\Application\UseCase\GetPricingQuote\GetPricingQuoteQuery;
use App\Pricing\Domain\Exception\RoomHasNoBaseRateException;
use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Port\PricingQuoteFetcherInterface;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Reservation\Domain\ValueObject\PricingQuoteSnapshot;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class PricingQuoteFetcher implements PricingQuoteFetcherInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function fetch(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteSnapshot
    {
        try {
            /** @var array{totalAmountCents: int, nights: list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}>} $result */
            $result = $this->queryBus->ask(new GetPricingQuoteQuery($roomId, $checkIn, $checkOut));

            return new PricingQuoteSnapshot(
                $result['totalAmountCents'],
                PriceBreakdown::fromArray($result['nights']),
            );
        } catch (RoomHasNoBaseRateException) {
            throw new RoomNotBookableException($roomId);
        }
    }
}
```

- [ ] **Step 6: Run the test to confirm it passes**

```bash
make unit-test -- --filter PricingQuoteFetcherTest
```

Expected: 2 tests pass.

- [ ] **Step 7: Delete the old files**

```bash
rm src/Reservation/Domain/Port/PriceCalculatorInterface.php
rm src/Reservation/Infrastructure/Service/PricingCalculator.php
rm tests/Reservation/Infrastructure/FakePriceCalculator.php
```

- [ ] **Step 8: Run PHPStan to see all broken references**

```bash
make phpstan
```

Expected: errors pointing to handler and handler test that still reference the old class names. These are fixed in subsequent tasks — that's expected.

- [ ] **Step 9: Commit**

```bash
git add src/Reservation/Domain/Port/PricingQuoteFetcherInterface.php \
        src/Reservation/Infrastructure/Service/PricingQuoteFetcher.php \
        tests/Reservation/Infrastructure/FakePricingQuoteFetcher.php \
        tests/Reservation/Infrastructure/Service/PricingQuoteFetcherTest.php
git rm src/Reservation/Domain/Port/PriceCalculatorInterface.php \
       src/Reservation/Infrastructure/Service/PricingCalculator.php \
       tests/Reservation/Infrastructure/FakePriceCalculator.php
git commit -m "feat(reservation): replace PriceCalculatorInterface with PricingQuoteFetcherInterface returning PricingQuoteSnapshot"
```

---

### Task 3: Update `Reservation` model and `ReservationCreated` event

**Files:**
- Modify: `src/Reservation/Domain/Model/Reservation.php`
- Modify: `src/Reservation/Domain/Event/ReservationCreated.php`

- [ ] **Step 1: Add `priceBreakdown` to the `Reservation` model**

```php
// src/Reservation/Domain/Model/Reservation.php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

use App\Reservation\Domain\Exception\InvalidReservationTransitionException;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
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

- [ ] **Step 2: Add `priceBreakdown` to `ReservationCreated`**

```php
// src/Reservation/Domain/Event/ReservationCreated.php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Event;

use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\PriceBreakdown;

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
        public PriceBreakdown $priceBreakdown,
    ) {
    }
}
```

- [ ] **Step 3: Run PHPStan to confirm the expected breakage**

```bash
make phpstan
```

Expected: errors on the handler, repository, serializer, and tests that miss the new constructor argument. Correct — fixed in subsequent tasks.

- [ ] **Step 4: Commit**

```bash
git add src/Reservation/Domain/Model/Reservation.php \
        src/Reservation/Domain/Event/ReservationCreated.php
git commit -m "feat(reservation): add priceBreakdown field to Reservation model and ReservationCreated event"
```

---

### Task 4: Update `CreateReservationCommandHandler` and its unit test

**Files:**
- Modify: `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php`
- Modify: `tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php`

- [ ] **Step 1: Update the handler**

```php
// src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php
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
use App\Reservation\Domain\Port\PricingQuoteFetcherInterface;
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

        $quote = $this->pricingQuoteFetcher->fetch($command->roomId, $command->checkIn, $command->checkOut);
        $cancellationTerms = $this->cancellationPolicyFetcher->fetch($command->roomId);

        $reservation = new Reservation(
            id: $command->id,
            roomId: $command->roomId,
            bookerId: $command->bookerId,
            period: new DatePeriod($command->checkIn, $command->checkOut),
            totalPrice: $quote->totalAmountCents,
            cancellationTerms: $cancellationTerms,
            priceBreakdown: $quote->breakdown,
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

- [ ] **Step 2: Update the unit test**

```php
// tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php
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
        self::assertSame(ReservationStatus::Pending, $reservation->status);
        self::assertNull($reservation->cancellationTerms->daysThreshold);
        self::assertCount(4, $reservation->priceBreakdown->nights);
        self::assertSame('2026-06-01', $reservation->priceBreakdown->nights[0]->date);

        $event = $this->eventDispatcher->getLastDispatched();
        self::assertInstanceOf(ReservationCreated::class, $event);
        self::assertSame(self::RESERVATION_ID, $event->reservationId);
        self::assertSame(42000, $event->totalPrice);
        self::assertNull($event->cancellationTerms->daysThreshold);
        self::assertCount(4, $event->priceBreakdown->nights);
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
git commit -m "feat(reservation): inject PricingQuoteFetcher into CreateReservationCommandHandler, store priceBreakdown"
```

---

### Task 5: Update `ReservationRepository` + migration

**Files:**
- Modify: `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php`
- Create: new migration file (via `make generate-migration`, then edit)

- [ ] **Step 1: Update the repository**

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
            'cancellation_terms_days_threshold' => $reservation->cancellationTerms->daysThreshold,
            'price_breakdown' => json_encode($reservation->priceBreakdown->toArray()),
            'status' => $reservation->status->value,
            'created_at' => $reservation->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $id): ?Reservation
    {
        /** @var array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, room_id, booker_id, check_in, check_out, total_price,
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
     * @param array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string} $row
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

This creates `migrations/VersionYYYYMMDDHHmmss.php`. Open it and fill in:

```php
public function getDescription(): string
{
    return 'Add price_breakdown JSONB column to reservation table';
}

public function up(Schema $schema): void
{
    $this->addSql("ALTER TABLE reservation ADD COLUMN price_breakdown JSONB NOT NULL DEFAULT '[]'::jsonb");
    $this->addSql('ALTER TABLE reservation ALTER COLUMN price_breakdown DROP DEFAULT');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE reservation DROP COLUMN price_breakdown');
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
git commit -m "feat(reservation): persist price_breakdown as JSONB on reservation table"
```

---

### Task 6: Update `ReservationSerializer` and functional test

**Files:**
- Modify: `src/Reservation/UI/Http/Controller/ReservationSerializer.php`

- [ ] **Step 1: Update the serializer**

```php
// src/Reservation/UI/Http/Controller/ReservationSerializer.php
<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\ValueObject\NightPrice;

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
            'status' => $reservation->status->value,
            'cancellationTerms' => [
                'daysThreshold' => $reservation->cancellationTerms->daysThreshold,
            ],
            'priceBreakdown' => array_map(
                static fn (NightPrice $night) => [
                    'date' => $night->date,
                    'rateAmountCents' => $night->rateAmountCents,
                    'discountPercent' => $night->discountPercent,
                    'effectiveAmountCents' => $night->effectiveAmountCents,
                ],
                $reservation->priceBreakdown->nights,
            ),
            'createdAt' => $reservation->createdAt
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
```

- [ ] **Step 2: Find the CreateReservation functional test and add a priceBreakdown assertion**

Open `tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php` (or wherever the functional test lives). Find the assertion block that checks `cancellationTerms` in the JSON response and add after it:

```php
self::assertArrayHasKey('priceBreakdown', $data);
self::assertIsArray($data['priceBreakdown']);
// At least one night should be present (the real Pricing context is exercised end-to-end)
self::assertNotEmpty($data['priceBreakdown']);
$firstNight = $data['priceBreakdown'][0];
self::assertArrayHasKey('date', $firstNight);
self::assertArrayHasKey('rateAmountCents', $firstNight);
self::assertArrayHasKey('discountPercent', $firstNight);
self::assertArrayHasKey('effectiveAmountCents', $firstNight);
```

- [ ] **Step 3: Run the full test suite**

```bash
make test
```

Expected: all unit + integration + functional tests pass.

- [ ] **Step 4: Regenerate OpenAPI spec**

```bash
make openapi
```

Then open the controller handling `GET /reservations/{id}` or `POST /reservations` and add the `priceBreakdown` property to the `#[OA\Response]` annotation:

```php
new OA\Property(
    property: 'priceBreakdown',
    type: 'array',
    items: new OA\Items(
        properties: [
            new OA\Property(property: 'date', type: 'string', example: '2026-06-01'),
            new OA\Property(property: 'rateAmountCents', type: 'integer', example: 10000),
            new OA\Property(property: 'discountPercent', type: 'integer', nullable: true, example: 10),
            new OA\Property(property: 'effectiveAmountCents', type: 'integer', example: 9000),
        ],
        type: 'object',
    ),
),
```

Run `make openapi` again after editing annotations to regenerate the spec file.

- [ ] **Step 5: Commit**

```bash
git add src/Reservation/UI/Http/Controller/ReservationSerializer.php \
        tests/Reservation/UI/Http/Controller/CreateReservation/CreateReservationControllerTest.php \
        public/openapi.yaml
git commit -m "feat(reservation): expose priceBreakdown in Reservation API response"
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

Expected: no violations. In particular, confirm:
- `PricingQuoteFetcher` is in `Infrastructure\` — it may depend on Pricing
- `PricingQuoteFetcherInterface` is in `Domain\Port\` — it depends on nothing outside Domain
- `PriceBreakdown`, `NightPrice`, `PricingQuoteSnapshot` are in `Domain\ValueObject\` — no framework dependencies

- [ ] **Step 3: Run static analysis**

```bash
make phpstan
```

Expected: no errors.

- [ ] **Step 4: Commit CS Fixer fixes if any**

```bash
git add -p
git commit -m "chore: apply CS Fixer formatting"
```

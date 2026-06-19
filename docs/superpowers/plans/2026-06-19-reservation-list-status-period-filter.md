# Reservation List Status/Period Filter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let `GET /api/v1/reservations` filter a Booker's reservation list by `status` (exact `ReservationStatus` match) and/or `period` (`past` / `current` / `upcoming`, derived from check-in/check-out vs. today), filtered server-side so pagination stays correct.

**Architecture:** Both filters are optional query parameters threaded end-to-end: `ListBookerReservationsRequest` (UI) → `ListBookerReservationsQuery` (Application) → `ReservationRepositoryInterface::listByBooker` (Domain port) → `ReservationRepository` (Doctrine, builds dynamic `WHERE` conditions) and `InMemoryReservationRepository` (test double, filters in PHP). `status` and `period` are independent and combinable (e.g. `status=cancelled&period=past`). A new `ReservationPeriodFilter` enum lives next to `ReservationStatus` in `Domain/Model`.

**Tech Stack:** PHP 8.4, Symfony 8.0 (`MapQueryString`, `Assert\Choice`), Doctrine DBAL (raw SQL, no ORM), PostgreSQL `CURRENT_DATE`.

## Global Constraints

- `declare(strict_types=1)` on every new/modified file.
- Domain layer (`Domain/Model`, `Domain/Port`) must have zero framework dependencies.
- Never put SQL inline in a handler — only in `Infrastructure/Persistence/Doctrine/*Repository.php`.
- Test groups: `TestCase` → `#[Group('unit')]`; `KernelTestCase` hitting the real DB → `#[Group('functional')]`; controller tests → `#[Group('functional')]`.
- Test method names: `itDoesSomething(): void` with `#[Test]`.
- Run `make openapi` after touching the route's request DTO or controller doc.
- Run `make lint` and `make test` before considering the work done.

---

### Task 1: `ReservationPeriodFilter` enum + `ReservationStatus::values()`

**Files:**
- Create: `src/Reservation/Domain/Model/ReservationPeriodFilter.php`
- Modify: `src/Reservation/Domain/Model/ReservationStatus.php`
- Test: `tests/Reservation/Domain/Model/ReservationPeriodFilterTest.php`
- Test: `tests/Reservation/Domain/Model/ReservationStatusTest.php`

**Interfaces:**
- Produces: `ReservationPeriodFilter` (string-backed enum, cases `Past = 'past'`, `Current = 'current'`, `Upcoming = 'upcoming'`, static method `values(): array`).
- Produces: `ReservationStatus::values(): array` (mirrors `HotelAmenity::values()` at `src/Hotel/Domain/ValueObject/HotelAmenity.php:30`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\Model;

use App\Reservation\Domain\Model\ReservationPeriodFilter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationPeriodFilterTest extends TestCase
{
    #[Test]
    public function itListsAllValues(): void
    {
        self::assertSame(['past', 'current', 'upcoming'], ReservationPeriodFilter::values());
    }

    #[Test]
    public function itResolvesFromValue(): void
    {
        self::assertSame(ReservationPeriodFilter::Past, ReservationPeriodFilter::from('past'));
        self::assertSame(ReservationPeriodFilter::Current, ReservationPeriodFilter::from('current'));
        self::assertSame(ReservationPeriodFilter::Upcoming, ReservationPeriodFilter::from('upcoming'));
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\Model;

use App\Reservation\Domain\Model\ReservationStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationStatusTest extends TestCase
{
    #[Test]
    public function itListsAllValues(): void
    {
        self::assertSame(
            ['pending', 'confirmed', 'cancelled', 'expired', 'checked_in', 'checked_out'],
            ReservationStatus::values(),
        );
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `make unit-test`
Expected: FAIL — `Class "App\Reservation\Domain\Model\ReservationPeriodFilter" not found` and `Call to undefined method App\Reservation\Domain\Model\ReservationStatus::values()`.

- [ ] **Step 3: Write minimal implementation**

`src/Reservation/Domain/Model/ReservationPeriodFilter.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

enum ReservationPeriodFilter: string
{
    case Past = 'past';
    case Current = 'current';
    case Upcoming = 'upcoming';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

Modify `src/Reservation/Domain/Model/ReservationStatus.php` — add after the last case:

```php
    case CheckedOut = 'checked_out';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

(replacing the previous closing `}` of the enum body).

- [ ] **Step 4: Run tests to verify they pass**

Run: `make unit-test`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Reservation/Domain/Model/ReservationPeriodFilter.php src/Reservation/Domain/Model/ReservationStatus.php tests/Reservation/Domain/Model/ReservationPeriodFilterTest.php tests/Reservation/Domain/Model/ReservationStatusTest.php
git commit -m "feat(reservation): add ReservationPeriodFilter enum and ReservationStatus::values()"
```

---

### Task 2: Extend `ReservationRepositoryInterface::listByBooker` signature

**Files:**
- Modify: `src/Reservation/Domain/Port/ReservationRepositoryInterface.php`

**Interfaces:**
- Consumes: `ReservationStatus` (`src/Reservation/Domain/Model/ReservationStatus.php`), `ReservationPeriodFilter` (Task 1).
- Produces: `listByBooker(BookerId $bookerId, int $page, int $limit, ?ReservationStatus $status = null, ?ReservationPeriodFilter $period = null): ReservationPage` — the new contract every implementation and caller must match.

This is a pure interface change with no behavior to unit test on its own; correctness is verified by Tasks 3 and 4, which implement it. PHPStan (`make static-code-analysis`) will catch any implementation left out of sync.

- [ ] **Step 1: Modify the interface**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationPage;
use App\Reservation\Domain\Model\ReservationPeriodFilter;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Shared\Domain\ValueObject\BookerId;
use App\Shared\Domain\ValueObject\ReservationId;

interface ReservationRepositoryInterface
{
    public function add(Reservation $reservation): void;

    public function save(Reservation $reservation): void;

    public function get(ReservationId $id): ?Reservation;

    public function listByBooker(
        BookerId $bookerId,
        int $page,
        int $limit,
        ?ReservationStatus $status = null,
        ?ReservationPeriodFilter $period = null,
    ): ReservationPage;
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Reservation/Domain/Port/ReservationRepositoryInterface.php
git commit -m "feat(reservation): add status/period params to ReservationRepositoryInterface::listByBooker"
```

(No standalone test run here — the codebase won't compile/pass static analysis until Tasks 3 and 4 update both implementations. That's expected; the next two tasks fix it.)

---

### Task 3: Update `InMemoryReservationRepository` test double

**Files:**
- Modify: `tests/Reservation/Infrastructure/Persistence/InMemory/InMemoryReservationRepository.php`

**Interfaces:**
- Consumes: `ReservationRepositoryInterface::listByBooker` new signature (Task 2).
- Produces: same filtering semantics as the Doctrine implementation (Task 4) — `status` is exact match; `period` compares `DatePeriod` against `new \DateTimeImmutable('today')`: `upcoming` ⇔ `checkIn > today`; `current` ⇔ `checkIn <= today AND checkOut > today`; `past` ⇔ `checkOut <= today`.

This test double has no dedicated test file of its own (it's exercised indirectly through other use-case integration tests); Task 4's functional tests on the real Doctrine repository are the authoritative behavior check. Updating this double is required simply to keep `ReservationRepositoryInterface` implementations in sync — verified by `make static-code-analysis`.

- [ ] **Step 1: Update the implementation**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Persistence\InMemory;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationPage;
use App\Reservation\Domain\Model\ReservationPeriodFilter;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Domain\ValueObject\BookerId;
use App\Shared\Domain\ValueObject\ReservationId;

final class InMemoryReservationRepository implements ReservationRepositoryInterface
{
    /** @var array<string, Reservation> */
    private array $store = [];

    public function add(Reservation $reservation): void
    {
        $this->store[$reservation->id->value] = $reservation;
    }

    public function save(Reservation $reservation): void
    {
        $this->store[$reservation->id->value] = $reservation;
    }

    public function get(ReservationId $id): ?Reservation
    {
        return $this->store[$id->value] ?? null;
    }

    public function listByBooker(
        BookerId $bookerId,
        int $page,
        int $limit,
        ?ReservationStatus $status = null,
        ?ReservationPeriodFilter $period = null,
    ): ReservationPage {
        $today = new \DateTimeImmutable('today');

        $all = array_values(array_filter(
            $this->store,
            function (Reservation $r) use ($bookerId, $status, $period, $today): bool {
                if ($r->bookerId->value !== $bookerId->value) {
                    return false;
                }

                if (null !== $status && $r->status !== $status) {
                    return false;
                }

                if (null !== $period && !$this->matchesPeriod($r, $period, $today)) {
                    return false;
                }

                return true;
            },
        ));

        usort($all, fn(Reservation $a, Reservation $b) => $b->createdAt <=> $a->createdAt);

        $total = count($all);
        $items = array_slice($all, ($page - 1) * $limit, $limit);

        return new ReservationPage($items, $total);
    }

    private function matchesPeriod(Reservation $r, ReservationPeriodFilter $period, \DateTimeImmutable $today): bool
    {
        return match ($period) {
            ReservationPeriodFilter::Upcoming => $r->period->checkIn > $today,
            ReservationPeriodFilter::Current => $r->period->checkIn <= $today && $r->period->checkOut > $today,
            ReservationPeriodFilter::Past => $r->period->checkOut <= $today,
        };
    }
}
```

- [ ] **Step 2: Run the existing integration tests that depend on this double**

Run: `make unit-test`
Expected: PASS (no behavior change for existing callers — they all call `listByBooker` with 3 args, which still works because `status`/`period` default to `null`).

- [ ] **Step 3: Commit**

```bash
git add tests/Reservation/Infrastructure/Persistence/InMemory/InMemoryReservationRepository.php
git commit -m "feat(reservation): filter InMemoryReservationRepository::listByBooker by status/period"
```

---

### Task 4: Filter `ReservationRepository::listByBooker` (Doctrine) by status/period

**Files:**
- Modify: `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php:111-173`
- Test: `tests/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepositoryListByBookerTest.php`

**Interfaces:**
- Consumes: `ReservationRepositoryInterface::listByBooker` (Task 2), `Reservation` constructor (`src/Reservation/Domain/Model/Reservation.php:31`), `DatePeriod`, `GuestCount`, `CancellationTerms::alwaysRefundable()`, `PriceBreakdown::fromArray([])`.
- Produces: SQL filtering on `status` (exact match) and `period` (`CURRENT_DATE` comparison on `check_in`/`check_out`), following the dynamic-`WHERE`-conditions pattern used in `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php:84-109`.

- [ ] **Step 1: Write the failing integration test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Persistence\Doctrine;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationPeriodFilter;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Reservation\Infrastructure\Persistence\Doctrine\ReservationRepository;
use App\Shared\Domain\ValueObject\BookerId;
use App\Shared\Domain\ValueObject\ReservationId;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class ReservationRepositoryListByBookerTest extends KernelTestCase
{
    private const BOOKER_ID = 'b1000000-0000-4000-8000-000000000001';
    private const ROOM_ID = 'c1000000-0000-4000-8000-000000000001';
    private const PAST_ID = 'd1000000-0000-4000-8000-000000000001';
    private const CURRENT_ID = 'd1000000-0000-4000-8000-000000000002';
    private const UPCOMING_ID = 'd1000000-0000-4000-8000-000000000003';
    private const CANCELLED_UPCOMING_ID = 'd1000000-0000-4000-8000-000000000004';

    private ReservationRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(ReservationRepository::class);

        $this->addReservation(self::PAST_ID, '-10 days', '-8 days', ReservationStatus::CheckedOut);
        $this->addReservation(self::CURRENT_ID, '-1 days', '+2 days', ReservationStatus::CheckedIn);
        $this->addReservation(self::UPCOMING_ID, '+5 days', '+8 days', ReservationStatus::Confirmed);
        $this->addReservation(self::CANCELLED_UPCOMING_ID, '+10 days', '+12 days', ReservationStatus::Cancelled);
    }

    #[Test]
    public function itFiltersByPeriodPast(): void
    {
        $page = $this->repository->listByBooker(new BookerId(self::BOOKER_ID), 1, 100, period: ReservationPeriodFilter::Past);

        self::assertSame([self::PAST_ID], $this->ids($page));
    }

    #[Test]
    public function itFiltersByPeriodCurrent(): void
    {
        $page = $this->repository->listByBooker(new BookerId(self::BOOKER_ID), 1, 100, period: ReservationPeriodFilter::Current);

        self::assertSame([self::CURRENT_ID], $this->ids($page));
    }

    #[Test]
    public function itFiltersByPeriodUpcoming(): void
    {
        $page = $this->repository->listByBooker(new BookerId(self::BOOKER_ID), 1, 100, period: ReservationPeriodFilter::Upcoming);

        self::assertSame([self::UPCOMING_ID, self::CANCELLED_UPCOMING_ID], $this->ids($page));
    }

    #[Test]
    public function itFiltersByStatus(): void
    {
        $page = $this->repository->listByBooker(new BookerId(self::BOOKER_ID), 1, 100, status: ReservationStatus::Cancelled);

        self::assertSame([self::CANCELLED_UPCOMING_ID], $this->ids($page));
    }

    #[Test]
    public function itCombinesStatusAndPeriod(): void
    {
        $page = $this->repository->listByBooker(
            new BookerId(self::BOOKER_ID),
            1,
            100,
            status: ReservationStatus::Confirmed,
            period: ReservationPeriodFilter::Upcoming,
        );

        self::assertSame([self::UPCOMING_ID], $this->ids($page));
    }

    /** @return list<string> */
    private function ids(\App\Reservation\Domain\Model\ReservationPage $page): array
    {
        $ids = array_map(static fn(Reservation $r) => $r->id->value, $page->reservations);
        sort($ids);

        return $ids;
    }

    private function addReservation(string $id, string $checkInOffset, string $checkOutOffset, ReservationStatus $status): void
    {
        $reservation = new Reservation(
            id: new ReservationId($id),
            roomId: new RoomId(self::ROOM_ID),
            bookerId: new BookerId(self::BOOKER_ID),
            period: new DatePeriod(
                new \DateTimeImmutable($checkInOffset),
                new \DateTimeImmutable($checkOutOffset),
            ),
            totalPrice: 10000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: PriceBreakdown::fromArray([]),
            guestCount: new GuestCount(1),
            createdAt: new \DateTimeImmutable(),
        );
        $reservation->status = $status;

        $this->repository->add($reservation);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `make functional-test`
Expected: FAIL — `Too few arguments to function ...listByBooker()` does not occur (defaults exist), but assertions fail because the repository ignores `status`/`period` and returns all 4 rows for every case.

- [ ] **Step 3: Write minimal implementation**

Replace `listByBooker` in `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php` (lines 111-173):

```php
    public function listByBooker(
        BookerId $bookerId,
        int $page,
        int $limit,
        ?ReservationStatus $status = null,
        ?ReservationPeriodFilter $period = null,
    ): ReservationPage {
        $conditions = ['booker_id = :bookerId'];
        $params = ['bookerId' => $bookerId->value];

        if (null !== $status) {
            $conditions[] = 'status = :status';
            $params['status'] = $status->value;
        }

        if (null !== $period) {
            $conditions[] = match ($period) {
                ReservationPeriodFilter::Upcoming => 'check_in > CURRENT_DATE',
                ReservationPeriodFilter::Current => 'check_in <= CURRENT_DATE AND check_out > CURRENT_DATE',
                ReservationPeriodFilter::Past => 'check_out <= CURRENT_DATE',
            };
        }

        $where = implode(' AND ', $conditions);

        $count = $this->reservationConnection->fetchOne(
            "SELECT COUNT(*) FROM reservation WHERE {$where}",
            $params,
        );
        $total = is_numeric($count) ? (int) $count : 0;

        if (0 === $total) {
            return new ReservationPage([], 0);
        }

        $offset = ($page - 1) * $limit;
        $listParams = $params + ['limit' => $limit, 'offset' => $offset];

        /** @var list<array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, guest_count: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string, actual_departure_date: string|null, cancelled_at: string|null, cancelled_by: string|null, g_id: string|null, first_name: string|null, last_name: string|null, date_of_birth: string|null}> $rows */
        $rows = $this->reservationConnection->fetchAllAssociative(
            "SELECT r.id, r.room_id, r.booker_id, r.check_in, r.check_out, r.total_price, r.guest_count,
                    r.cancellation_terms_days_threshold, r.price_breakdown, r.status, r.created_at,
                    r.actual_departure_date, r.cancelled_at, r.cancelled_by,
                    rg.id AS g_id, rg.first_name, rg.last_name, rg.date_of_birth
               FROM reservation r
               LEFT JOIN guest rg ON rg.reservation_id = r.id
              WHERE r.id IN (
                  SELECT id FROM reservation
                   WHERE {$where}
                   ORDER BY created_at DESC
                   LIMIT :limit OFFSET :offset
              )
              ORDER BY r.created_at DESC, r.id, rg.id",
            $listParams,
            ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
        );

        $byId = [];
        $guestsByReservationId = [];
        foreach ($rows as $row) {
            $rid = $row['id'];
            if (!isset($byId[$rid])) {
                $byId[$rid] = $row;
                $guestsByReservationId[$rid] = [];
            }
            if (null !== $row['g_id']) {
                $guestsByReservationId[$rid][] = $row;
            }
        }

        $reservations = [];
        foreach ($byId as $rid => $row) {
            $reservation = $this->hydrate($row);
            $reservation->guests = array_map(
                fn(array $g) => new Guest(
                    id: new GuestId($g['g_id']),
                    firstName: (string) $g['first_name'],
                    lastName: (string) $g['last_name'],
                    dateOfBirth: new \DateTimeImmutable((string) $g['date_of_birth']),
                ),
                $guestsByReservationId[$rid],
            );
            $reservations[] = $reservation;
        }

        return new ReservationPage($reservations, $total);
    }
```

Add the two new imports near the top of the file (alongside the existing `App\Reservation\Domain\Model\*` imports, keeping alphabetical order per CS Fixer's ordered-imports rule):

```php
use App\Reservation\Domain\Model\ReservationPeriodFilter;
use App\Reservation\Domain\Model\ReservationStatus;
```

(`ReservationStatus` is already imported — only add `ReservationPeriodFilter`.)

- [ ] **Step 4: Run test to verify it passes**

Run: `make functional-test`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php tests/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepositoryListByBookerTest.php
git commit -m "feat(reservation): filter ReservationRepository::listByBooker by status and period"
```

---

### Task 5: Thread `status`/`period` through `ListBookerReservationsQuery` and its handler

**Files:**
- Modify: `src/Reservation/Application/UseCase/ListBookerReservations/ListBookerReservationsQuery.php`
- Modify: `src/Reservation/Application/UseCase/ListBookerReservations/ListBookerReservationsQueryHandler.php`

**Interfaces:**
- Consumes: `ReservationStatus`, `ReservationPeriodFilter` (Task 1), `ReservationRepositoryInterface::listByBooker` (Task 2/4).
- Produces: `ListBookerReservationsQuery(BookerId $bookerId, int $page = 1, int $limit = 20, ?ReservationStatus $status = null, ?ReservationPeriodFilter $period = null)` — the exact constructor signature Task 6's controller calls.

No new test here: this is pure pass-through wiring. The existing `ListBookerReservationsControllerTest` (extended in Task 6) and the repository tests (Task 4) cover behavior end-to-end.

- [ ] **Step 1: Update the Query**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\ListBookerReservations;

use App\Reservation\Domain\Model\ReservationPage;
use App\Reservation\Domain\Model\ReservationPeriodFilter;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Shared\Application\Bus\SyncQueryInterface;
use App\Shared\Domain\ValueObject\BookerId;

/** @implements SyncQueryInterface<ReservationPage> */
final readonly class ListBookerReservationsQuery implements SyncQueryInterface
{
    public function __construct(
        public BookerId $bookerId,
        public int $page = 1,
        public int $limit = 20,
        public ?ReservationStatus $status = null,
        public ?ReservationPeriodFilter $period = null,
    ) {
    }
}
```

- [ ] **Step 2: Update the handler**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\ListBookerReservations;

use App\Reservation\Domain\Model\ReservationPage;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class ListBookerReservationsQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private ReservationRepositoryInterface $repository)
    {
    }

    public function __invoke(ListBookerReservationsQuery $query): ReservationPage
    {
        return $this->repository->listByBooker(
            $query->bookerId,
            $query->page,
            $query->limit,
            $query->status,
            $query->period,
        );
    }
}
```

- [ ] **Step 3: Run static analysis to verify wiring compiles**

Run: `make static-code-analysis`
Expected: PASS (no PHPStan errors)

- [ ] **Step 4: Commit**

```bash
git add src/Reservation/Application/UseCase/ListBookerReservations/ListBookerReservationsQuery.php src/Reservation/Application/UseCase/ListBookerReservations/ListBookerReservationsQueryHandler.php
git commit -m "feat(reservation): pass status/period from ListBookerReservationsQuery to repository"
```

---

### Task 6: Expose `status`/`period` query params on the controller + functional tests

**Files:**
- Modify: `src/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsRequest.php`
- Modify: `src/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsController.php`
- Modify: `tests/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsControllerTest.php`

**Interfaces:**
- Consumes: `ListBookerReservationsQuery` (Task 5), `ReservationStatus::values()` / `ReservationPeriodFilter::values()` (Task 1).
- Produces: `GET /api/v1/reservations?bookerId=...&status=cancelled&period=past` — query params validated via `Assert\Choice`, 422 on invalid value, otherwise filtered as in Task 4.

- [ ] **Step 1: Write the failing functional tests**

Add to `tests/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsControllerTest.php`, inside the class, after `itReturns422WhenPageIsZero`:

```php
    #[Test]
    public function itFiltersByStatus(): void
    {
        $client = static::createAuthenticatedClient();
        [$bookerId, $roomTypeId] = $this->setupBookerAndRoom($client);
        $this->createReservation($client, $bookerId, $roomTypeId, '2030-06-01', '2030-06-03');

        $client->request('GET', "/api/v1/reservations?bookerId={$bookerId}&status=pending");

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        /** @var array{data: list<array{status: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['meta']['total']);

        $client->request('GET', "/api/v1/reservations?bookerId={$bookerId}&status=cancelled");

        /** @var array{meta: array{total: int}} $emptyBody */
        $emptyBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(0, $emptyBody['meta']['total']);
    }

    #[Test]
    public function itFiltersByUpcomingPeriod(): void
    {
        $client = static::createAuthenticatedClient();
        [$bookerId, $roomTypeId] = $this->setupBookerAndRoom($client);
        $farFuture = (new \DateTimeImmutable('+30 days'))->format('Y-m-d');
        $farFutureCheckOut = (new \DateTimeImmutable('+32 days'))->format('Y-m-d');
        $this->createReservation($client, $bookerId, $roomTypeId, $farFuture, $farFutureCheckOut);

        $client->request('GET', "/api/v1/reservations?bookerId={$bookerId}&period=upcoming");

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        /** @var array{meta: array{total: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['meta']['total']);

        $client->request('GET', "/api/v1/reservations?bookerId={$bookerId}&period=past");

        /** @var array{meta: array{total: int}} $emptyBody */
        $emptyBody = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(0, $emptyBody['meta']['total']);
    }

    #[Test]
    public function itReturns422WhenStatusIsInvalid(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request('GET', '/api/v1/reservations?bookerId=00000000-0000-4000-8000-000000000001&status=not-a-status');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenPeriodIsInvalid(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request('GET', '/api/v1/reservations?bookerId=00000000-0000-4000-8000-000000000001&period=not-a-period');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `make functional-test`
Expected: FAIL — `status`/`period` query params are currently ignored (no filtering occurs, no validation), so `itFiltersByStatus`, `itFiltersByUpcomingPeriod`, `itReturns422WhenStatusIsInvalid`, and `itReturns422WhenPeriodIsInvalid` all fail.

- [ ] **Step 3: Update the Request DTO**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\ListBookerReservations;

use App\Reservation\Domain\Model\ReservationPeriodFilter;
use App\Reservation\Domain\Model\ReservationStatus;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListBookerReservationsRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        #[OA\Parameter(name: 'bookerId', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
        public ?string $bookerId = null,
        #[Assert\GreaterThanOrEqual(1)]
        #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1))]
        public int $page = 1,
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(100)]
        #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1))]
        public int $limit = 20,
        #[Assert\Choice(callback: [ReservationStatus::class, 'values'])]
        #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', nullable: true, enum: ['pending', 'confirmed', 'cancelled', 'expired', 'checked_in', 'checked_out']))]
        public ?string $status = null,
        #[Assert\Choice(callback: [ReservationPeriodFilter::class, 'values'])]
        #[OA\Parameter(name: 'period', in: 'query', schema: new OA\Schema(type: 'string', nullable: true, enum: ['past', 'current', 'upcoming']))]
        public ?string $period = null,
    ) {
    }
}
```

- [ ] **Step 4: Update the Controller**

In `src/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsController.php`, add imports and convert the strings to enums when building the query:

```php
use App\Reservation\Application\UseCase\ListBookerReservations\ListBookerReservationsQuery;
use App\Reservation\Domain\Model\ReservationPeriodFilter;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\UI\Http\Controller\ReservationSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Shared\Domain\ValueObject\BookerId;
```

Add `status`/`period` OpenAPI doc entries are already covered by the Request DTO's `#[OA\Parameter]` (per CLAUDE.md: "Nelmio reads `#[OA\Parameter]` from DTO properties — don't repeat in `#[OA\Get(parameters: [...])]`") — no change needed to the `#[OA\Get]` attribute itself.

Replace the `__invoke` body:

```php
    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListBookerReservationsRequest $request = new ListBookerReservationsRequest(),
    ): Response {
        $page = $this->queryBus->ask(new ListBookerReservationsQuery(
            new BookerId((string) $request->bookerId),
            $request->page,
            $request->limit,
            null !== $request->status ? ReservationStatus::from($request->status) : null,
            null !== $request->period ? ReservationPeriodFilter::from($request->period) : null,
        ));

        return new JsonResponse([
            'data' => array_map($this->serializer->serialize(...), $page->reservations),
            'meta' => [
                'page' => $request->page,
                'limit' => $request->limit,
                'total' => $page->total,
                'totalPages' => (int) ceil($page->total / $request->limit),
            ],
        ]);
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `make functional-test`
Expected: PASS

- [ ] **Step 6: Regenerate OpenAPI spec**

Run: `make openapi`
Expected: `openapi.yaml` updates with `status` and `period` query parameters on `get_reservation_list_by_booker`. Review the diff.

- [ ] **Step 7: Commit**

```bash
git add src/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsRequest.php src/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsController.php tests/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsControllerTest.php openapi.yaml
git commit -m "feat(reservation): add status/period query filters to GET /reservations"
```

---

### Task 7: Final verification

**Files:** none (verification only)

- [ ] **Step 1: Run full lint**

Run: `make lint`
Expected: PASS (CS Fixer, PHPStan, both Deptrac analyses)

- [ ] **Step 2: Run full test suite**

Run: `make test`
Expected: PASS (all unit + functional tests, including every test added in Tasks 1, 4, and 6)

- [ ] **Step 3: Verify GitNexus-tracked impact**

Run `gitnexus_detect_changes()` to confirm only the expected symbols/flows changed (`ListBookerReservationsQuery`, `ListBookerReservationsQueryHandler`, `ListBookerReservationsRequest`, `ListBookerReservationsController`, `ReservationRepositoryInterface`, `ReservationRepository`, `InMemoryReservationRepository`, `ReservationStatus`, plus the new `ReservationPeriodFilter`).

- [ ] **Step 4: Final commit if any lint auto-fixes were applied**

```bash
git add -A
git commit -m "chore(reservation): apply lint fixes for status/period filter feature"
```

(Skip this step if `make lint` made no changes.)

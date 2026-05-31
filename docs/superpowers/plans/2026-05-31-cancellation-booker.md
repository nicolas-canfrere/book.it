# Cancellation par le Booker — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement `POST /reservations/{id}/cancel` allowing a Booker to voluntarily cancel a confirmed Reservation before the check-in date, with refund amount (full or zero) determined by the snapshotted CancellationTerms.

**Architecture:** `Reservation::cancelByBooker()` enforces status and date guards in the domain; the handler computes `refundAmountCents` from `CancellationTerms` and dispatches `ReservationCancelled`; two listeners react — Availability deletes the Blocked Period, Payment logs the refund amount. `cancelled_at` / `cancelled_by` are persisted via a DBAL migration.

**Tech Stack:** PHP 8.4 / Symfony 8.0 / Doctrine DBAL / Symfony EventDispatcher / PHPUnit 11

---

## File Map

### New files
| Path | Purpose |
|------|---------|
| `src/Reservation/Domain/Exception/CancellationNotAllowedException.php` | Domain exception: cancellation on or after check-in date |
| `src/Shared/Domain/Event/ReservationCancelled.php` | Shared event: reservationId, roomId, bookerId, refundAmountCents, checkIn, checkOut |
| `src/Reservation/Application/UseCase/CancelReservation/CancelReservationCommand.php` | Command: reservationId + today |
| `src/Reservation/Application/UseCase/CancelReservation/CancelReservationCommandHandler.php` | Handler: load, cancelByBooker, save, dispatch ReservationCancelled |
| `src/Reservation/UI/Http/Controller/CancelReservation/CancelReservationController.php` | POST /reservations/{id}/cancel — no body |
| `src/Availability/Infrastructure/EventListener/ReservationCancelledListener.php` | Deletes Blocked Period on ReservationCancelled |
| `src/Payment/Infrastructure/EventListener/ReservationCancelledListener.php` | Logs refund info on ReservationCancelled |
| `migrations/Version20260531XXXXXX.php` | Add cancelled_at / cancelled_by columns to reservation table |
| `tests/Reservation/Domain/Model/ReservationCancelByBookerTest.php` | Unit tests for cancelByBooker() |
| `tests/Reservation/Functional/Controller/CancelReservation/CancelReservationControllerTest.php` | Functional tests for the cancel endpoint |

### Modified files
| Path | Change |
|------|--------|
| `src/Reservation/Domain/Model/Reservation.php` | Add `$cancelledAt`, `$cancelledBy` fields; add `cancelByBooker()` method |
| `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php` | `save()` + `get()` SELECT + `listByBooker()` SELECT + `hydrate()` docblock and body |
| `config/services/exceptions.yaml` | Map `CancellationNotAllowedException` → 409 |

---

## Task 1: CancellationNotAllowedException

**Files:**
- Create: `src/Reservation/Domain/Exception/CancellationNotAllowedException.php`

- [ ] **Step 1: Write the failing unit test**

Create `tests/Reservation/Domain/Model/ReservationCancelByBookerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\Model;

use App\Reservation\Domain\Exception\CancellationNotAllowedException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationCancelByBookerTest extends TestCase
{
    #[Test]
    public function itThrowsCancellationNotAllowedOnCheckInDate(): void
    {
        $this->expectException(CancellationNotAllowedException::class);
        $this->expectExceptionMessage('2026-06-15');

        throw CancellationNotAllowedException::afterCheckIn(
            new \DateTimeImmutable('2026-06-15'),
            new \DateTimeImmutable('2026-06-15'),
        );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
make unit-test
```

Expected: FAIL — `CancellationNotAllowedException` class not found.

- [ ] **Step 3: Create the exception**

Create `src/Reservation/Domain/Exception/CancellationNotAllowedException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

final class CancellationNotAllowedException extends \DomainException
{
    public static function afterCheckIn(\DateTimeImmutable $checkIn, \DateTimeImmutable $today): self
    {
        return new self(sprintf(
            'Cancellation is not allowed on or after the check-in date (%s). Today is %s.',
            $checkIn->format('Y-m-d'),
            $today->format('Y-m-d'),
        ));
    }
}
```

- [ ] **Step 4: Run to verify it passes**

```bash
make unit-test
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Reservation/Domain/Exception/CancellationNotAllowedException.php \
        tests/Reservation/Domain/Model/ReservationCancelByBookerTest.php
git commit -m "feat(reservation): add CancellationNotAllowedException domain exception"
```

---

## Task 2: Reservation::cancelByBooker()

**Files:**
- Modify: `src/Reservation/Domain/Model/Reservation.php`
- Modify: `tests/Reservation/Domain/Model/ReservationCancelByBookerTest.php`

- [ ] **Step 1: Add failing tests**

Add to `tests/Reservation/Domain/Model/ReservationCancelByBookerTest.php` (keep the existing test, add these):

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Domain\Model;

use App\Reservation\Domain\Exception\CancellationNotAllowedException;
use App\Reservation\Domain\Exception\InvalidReservationTransitionException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationCancelByBookerTest extends TestCase
{
    private const string ID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private const string ROOM_ID = '550e8400-e29b-41d4-a716-446655440001';
    private const string BOOKER_ID = '550e8400-e29b-41d4-a716-446655440002';

    #[Test]
    public function itCancelsConfirmedReservationBeforeCheckIn(): void
    {
        $reservation = $this->makeConfirmedReservation(checkIn: new \DateTimeImmutable('2026-06-15'));
        $today = new \DateTimeImmutable('2026-06-10');

        $reservation->cancelByBooker($today);

        self::assertSame(ReservationStatus::Cancelled, $reservation->status);
        self::assertSame('2026-06-10', $reservation->cancelledAt?->format('Y-m-d'));
        self::assertSame('booker', $reservation->cancelledBy);
    }

    #[Test]
    public function itThrowsWhenCheckInDateIsToday(): void
    {
        $checkIn = new \DateTimeImmutable('2026-06-15');
        $reservation = $this->makeConfirmedReservation(checkIn: $checkIn);

        $this->expectException(CancellationNotAllowedException::class);

        $reservation->cancelByBooker($checkIn);
    }

    #[Test]
    public function itThrowsWhenCheckInDateIsInThePast(): void
    {
        $checkIn = new \DateTimeImmutable('2026-06-10');
        $reservation = $this->makeConfirmedReservation(checkIn: $checkIn);

        $this->expectException(CancellationNotAllowedException::class);

        $reservation->cancelByBooker(new \DateTimeImmutable('2026-06-15'));
    }

    #[Test]
    public function itThrowsInvalidTransitionWhenReservationIsPending(): void
    {
        $reservation = $this->makePendingReservation(checkIn: new \DateTimeImmutable('2026-06-15'));

        $this->expectException(InvalidReservationTransitionException::class);

        $reservation->cancelByBooker(new \DateTimeImmutable('2026-06-10'));
    }

    #[Test]
    public function itThrowsInvalidTransitionWhenReservationIsCheckedIn(): void
    {
        $reservation = $this->makeConfirmedReservation(checkIn: new \DateTimeImmutable('2026-06-15'));
        $reservation->status = ReservationStatus::CheckedIn;

        $this->expectException(InvalidReservationTransitionException::class);

        $reservation->cancelByBooker(new \DateTimeImmutable('2026-06-10'));
    }

    #[Test]
    public function itThrowsCancellationNotAllowedExceptionContainingDates(): void
    {
        $checkIn = new \DateTimeImmutable('2026-06-15');
        $reservation = $this->makeConfirmedReservation(checkIn: $checkIn);

        $this->expectException(CancellationNotAllowedException::class);

        throw CancellationNotAllowedException::afterCheckIn($checkIn, new \DateTimeImmutable('2026-06-15'));
    }

    private function makePendingReservation(\DateTimeImmutable $checkIn): Reservation
    {
        return new Reservation(
            id: self::ID,
            roomId: self::ROOM_ID,
            bookerId: self::BOOKER_ID,
            period: new DatePeriod($checkIn, $checkIn->modify('+3 days')),
            totalPrice: 30000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: new PriceBreakdown([]),
            guestCount: new GuestCount(1),
            createdAt: new \DateTimeImmutable(),
        );
    }

    private function makeConfirmedReservation(\DateTimeImmutable $checkIn): Reservation
    {
        $reservation = $this->makePendingReservation($checkIn);
        $reservation->status = ReservationStatus::Confirmed;

        return $reservation;
    }
}
```

- [ ] **Step 2: Run to verify tests fail**

```bash
make unit-test
```

Expected: FAIL — `cancelByBooker` method not found on `Reservation`.

- [ ] **Step 3: Add fields and method to Reservation**

In `src/Reservation/Domain/Model/Reservation.php`, add two public nullable fields after `$actualDepartureDate`:

```php
public ?\DateTimeImmutable $cancelledAt = null;
public ?string $cancelledBy = null;
```

Add the following import at the top (after existing imports):

```php
use App\Reservation\Domain\Exception\CancellationNotAllowedException;
```

Add the `cancelByBooker()` method after `cancelPending()`:

```php
public function cancelByBooker(\DateTimeImmutable $today): void
{
    if (ReservationStatus::Confirmed !== $this->status) {
        throw new InvalidReservationTransitionException($this->status, ReservationStatus::Cancelled);
    }

    if ($today >= $this->period->checkIn) {
        throw CancellationNotAllowedException::afterCheckIn($this->period->checkIn, $today);
    }

    $this->status = ReservationStatus::Cancelled;
    $this->cancelledAt = $today;
    $this->cancelledBy = 'booker';
}
```

- [ ] **Step 4: Run to verify tests pass**

```bash
make unit-test
```

Expected: all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Reservation/Domain/Model/Reservation.php \
        tests/Reservation/Domain/Model/ReservationCancelByBookerTest.php
git commit -m "feat(reservation): add cancelByBooker() to Reservation domain model"
```

---

## Task 3: ReservationCancelled domain event

**Files:**
- Create: `src/Shared/Domain/Event/ReservationCancelled.php`

- [ ] **Step 1: Create the event**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class ReservationCancelled
{
    public function __construct(
        public string $reservationId,
        public string $roomId,
        public string $bookerId,
        public int $refundAmountCents,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Shared/Domain/Event/ReservationCancelled.php
git commit -m "feat(shared): add ReservationCancelled domain event"
```

---

## Task 4: CancelReservation application use case

**Files:**
- Create: `src/Reservation/Application/UseCase/CancelReservation/CancelReservationCommand.php`
- Create: `src/Reservation/Application/UseCase/CancelReservation/CancelReservationCommandHandler.php`

- [ ] **Step 1: Create the Command**

Create `src/Reservation/Application/UseCase/CancelReservation/CancelReservationCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CancelReservation;

final readonly class CancelReservationCommand
{
    public function __construct(
        public string $reservationId,
        public \DateTimeImmutable $today,
    ) {
    }
}
```

- [ ] **Step 2: Create the Handler**

Create `src/Reservation/Application/UseCase/CancelReservation/CancelReservationCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CancelReservation;

use App\Reservation\Domain\Exception\ReservationNotFoundException;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\ReservationCancelled;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class CancelReservationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(CancelReservationCommand $command): void
    {
        $reservation = $this->repository->get($command->reservationId);

        if (null === $reservation) {
            throw new ReservationNotFoundException($command->reservationId);
        }

        $reservation->cancelByBooker($command->today);
        $this->repository->save($reservation);

        $refundAmountCents = $reservation->cancellationTerms->isRefundable(
            $command->today,
            $reservation->period->checkIn,
        ) ? $reservation->totalPrice : 0;

        $this->eventDispatcher->dispatch(new ReservationCancelled(
            reservationId: $reservation->id,
            roomId: $reservation->roomId,
            bookerId: $reservation->bookerId,
            refundAmountCents: $refundAmountCents,
            checkIn: $reservation->period->checkIn,
            checkOut: $reservation->period->checkOut,
        ));
    }
}
```

- [ ] **Step 3: Run static analysis to verify**

```bash
make static-code-analysis
```

Expected: no errors on the two new files.

- [ ] **Step 4: Commit**

```bash
git add src/Reservation/Application/UseCase/CancelReservation/
git commit -m "feat(reservation): add CancelReservation use case"
```

---

## Task 5: Migration — add cancelled_at / cancelled_by columns

**Files:**
- Create: `migrations/Version2026XXXXXXXXXX.php` (generated filename)

- [ ] **Step 1: Generate migration skeleton**

```bash
make generate-migration
```

This creates a new file in `migrations/`. Note its exact filename (e.g., `Version20260531143000.php`).

- [ ] **Step 2: Fill in the migration**

Open the generated file and replace its `up()` / `down()` / `getDescription()` with:

```php
public function getDescription(): string
{
    return 'Add cancelled_at and cancelled_by columns to reservation table';
}

public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE reservation ADD cancelled_at DATE DEFAULT NULL');
    $this->addSql('ALTER TABLE reservation ADD cancelled_by VARCHAR(10) DEFAULT NULL');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE reservation DROP COLUMN cancelled_at');
    $this->addSql('ALTER TABLE reservation DROP COLUMN cancelled_by');
}
```

- [ ] **Step 3: Run the migration**

```bash
make migrate
```

Expected: migration applied without error.

- [ ] **Step 4: Commit**

```bash
git add migrations/
git commit -m "feat(reservation): add cancelled_at and cancelled_by columns to reservation"
```

---

## Task 6: Repository — persist and hydrate cancelled_at / cancelled_by

**Files:**
- Modify: `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php`

- [ ] **Step 1: Update `save()`**

In the `save()` method, locate the `$connection->update('reservation', [...], ...)` call and add the two new columns:

```php
$connection->update('reservation', [
    'status' => $reservation->status->value,
    'actual_departure_date' => $reservation->actualDepartureDate?->format('Y-m-d'),
    'cancelled_at' => $reservation->cancelledAt?->format('Y-m-d'),
    'cancelled_by' => $reservation->cancelledBy,
], ['id' => $reservation->id]);
```

- [ ] **Step 2: Update `get()` SELECT query**

In the `get()` method, add `r.cancelled_at, r.cancelled_by` to the SELECT list:

```php
$rows = $this->bookit->fetchAllAssociative(
    'SELECT r.id, r.room_id, r.booker_id, r.check_in, r.check_out, r.total_price, r.guest_count,
            r.cancellation_terms_days_threshold, r.price_breakdown, r.status, r.created_at,
            r.actual_departure_date, r.cancelled_at, r.cancelled_by,
            rg.id AS g_id, rg.first_name, rg.last_name, rg.date_of_birth
       FROM reservation r
       LEFT JOIN reservation_guest rg ON rg.reservation_id = r.id
      WHERE r.id = :id
      ORDER BY rg.id',
    ['id' => $id],
);
```

- [ ] **Step 3: Update `listByBooker()` SELECT query**

In the `listByBooker()` method, add `r.cancelled_at, r.cancelled_by` to the SELECT list:

```php
$rows = $this->bookit->fetchAllAssociative(
    'SELECT r.id, r.room_id, r.booker_id, r.check_in, r.check_out, r.total_price, r.guest_count,
            r.cancellation_terms_days_threshold, r.price_breakdown, r.status, r.created_at,
            r.actual_departure_date, r.cancelled_at, r.cancelled_by,
            rg.id AS g_id, rg.first_name, rg.last_name, rg.date_of_birth
       FROM reservation r
       LEFT JOIN reservation_guest rg ON rg.reservation_id = r.id
      WHERE r.id IN (
          SELECT id FROM reservation
           WHERE booker_id = :bookerId
           ORDER BY created_at DESC
           LIMIT :limit OFFSET :offset
      )
      ORDER BY r.created_at DESC, r.id, rg.id',
    ['bookerId' => $bookerId, 'limit' => $limit, 'offset' => $offset],
    ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
);
```

- [ ] **Step 4: Update `hydrate()` docblock**

Replace the `@param` docblock on `hydrate()` to add the two new keys:

```php
/**
 * @param array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, guest_count: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string, actual_departure_date: string|null, cancelled_at: string|null, cancelled_by: string|null} $row
 */
```

- [ ] **Step 5: Update `hydrate()` body**

At the end of `hydrate()`, after the `$reservation->actualDepartureDate` assignment, add:

```php
$reservation->cancelledAt = null !== $row['cancelled_at']
    ? new \DateTimeImmutable($row['cancelled_at'])
    : null;
$reservation->cancelledBy = $row['cancelled_by'];
```

- [ ] **Step 6: Run tests to verify nothing broke**

```bash
make functional-test
```

Expected: all existing tests PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php
git commit -m "feat(reservation): persist and hydrate cancelled_at/cancelled_by in ReservationRepository"
```

---

## Task 7: CancelReservationController

**Files:**
- Create: `src/Reservation/UI/Http/Controller/CancelReservation/CancelReservationController.php`

- [ ] **Step 1: Create the controller**

Create `src/Reservation/UI/Http/Controller/CancelReservation/CancelReservationController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\CancelReservation;

use App\Reservation\Application\UseCase\CancelReservation\CancelReservationCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class CancelReservationController
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    #[Route(
        path: '/reservations/{id}/cancel',
        name: 'reservation_cancel',
        requirements: ['id' => Requirement::UUID_V4],
        methods: ['POST'],
    )]
    #[OA\Post(
        summary: 'Cancel a reservation (by Booker)',
        tags: ['Reservation'],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Reservation cancelled'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Reservation not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Cancellation not allowed (wrong status or check-in date reached)', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    public function __invoke(string $id): Response
    {
        $this->commandBus->execute(new CancelReservationCommand(
            reservationId: $id,
            today: new \DateTimeImmutable('today'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 2: Run static analysis**

```bash
make static-code-analysis
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/Reservation/UI/Http/Controller/CancelReservation/
git commit -m "feat(reservation): add CancelReservationController POST /reservations/{id}/cancel"
```

---

## Task 8: Exception mapping

**Files:**
- Modify: `config/services/exceptions.yaml`

- [ ] **Step 1: Add the mapping**

Open `config/services/exceptions.yaml` and add inside the `$map` argument (after the existing Reservation exceptions):

```yaml
App\Reservation\Domain\Exception\CancellationNotAllowedException:
    type: 'https://book.it/problems/cancellation-not-allowed'
    title: 'Cancellation Not Allowed'
    status: 409
```

- [ ] **Step 2: Verify lint passes**

```bash
make lint
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add config/services/exceptions.yaml
git commit -m "feat(reservation): map CancellationNotAllowedException to HTTP 409"
```

---

## Task 9: Availability listener

**Files:**
- Create: `src/Availability/Infrastructure/EventListener/ReservationCancelledListener.php`

- [ ] **Step 1: Create the listener**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\EventListener;

use App\Availability\Application\UseCase\DeleteBlockedPeriodByRoomAndPeriod\DeleteBlockedPeriodByRoomAndPeriodCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Domain\Event\ReservationCancelled;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationCancelled::class)]
final readonly class ReservationCancelledListener
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(ReservationCancelled $event): void
    {
        $this->commandBus->execute(new DeleteBlockedPeriodByRoomAndPeriodCommand(
            roomId: $event->roomId,
            checkIn: $event->checkIn,
            checkOut: $event->checkOut,
        ));
    }
}
```

- [ ] **Step 2: Run static analysis**

```bash
make static-code-analysis
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/Availability/Infrastructure/EventListener/ReservationCancelledListener.php
git commit -m "feat(availability): delete Blocked Period on ReservationCancelled"
```

---

## Task 10: Payment listener

**Files:**
- Create: `src/Payment/Infrastructure/EventListener/ReservationCancelledListener.php`

- [ ] **Step 1: Create the listener**

```php
<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\EventListener;

use App\Shared\Domain\Event\ReservationCancelled;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationCancelled::class)]
final readonly class ReservationCancelledListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ReservationCancelled $event): void
    {
        $this->logger->info('Reservation cancelled — refund to process', [
            'reservationId' => $event->reservationId,
            'bookerId' => $event->bookerId,
            'refundAmountCents' => $event->refundAmountCents,
        ]);
    }
}
```

- [ ] **Step 2: Run static analysis**

```bash
make static-code-analysis
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/Payment/Infrastructure/EventListener/ReservationCancelledListener.php
git commit -m "feat(payment): log refund amount on ReservationCancelled"
```

---

## Task 11: Functional tests + OpenAPI

**Files:**
- Create: `tests/Reservation/Functional/Controller/CancelReservation/CancelReservationControllerTest.php`

- [ ] **Step 1: Write the failing functional test**

Create `tests/Reservation/Functional/Controller/CancelReservation/CancelReservationControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Functional\Controller\CancelReservation;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class CancelReservationControllerTest extends WebTestCase
{
    public function test_cancel_confirmed_reservation_returns_204(): void
    {
        $client = static::createClient();
        $reservationId = $this->createConfirmedReservation($client, '2099-08-01', '2099-08-05');

        $client->request(
            method: 'POST',
            uri: "/api/v1/reservations/{$reservationId}/cancel",
            server: ['CONTENT_TYPE' => 'application/json'],
        );

        self::assertResponseStatusCodeSame(204);
    }

    public function test_returns_404_when_reservation_not_found(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations/550e8400-e29b-41d4-a716-446655440099/cancel',
            server: ['CONTENT_TYPE' => 'application/json'],
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function test_returns_409_when_reservation_is_not_confirmed(): void
    {
        $client = static::createClient();
        $reservationId = $this->createCancelledReservation($client, '2099-08-01', '2099-08-05');

        $client->request(
            method: 'POST',
            uri: "/api/v1/reservations/{$reservationId}/cancel",
            server: ['CONTENT_TYPE' => 'application/json'],
        );

        self::assertResponseStatusCodeSame(409);
    }

    public function test_returns_409_when_check_in_date_is_today(): void
    {
        $client = static::createClient();
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $tomorrow = (new \DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');
        $reservationId = $this->createConfirmedReservation($client, $today, $tomorrow);

        $client->request(
            method: 'POST',
            uri: "/api/v1/reservations/{$reservationId}/cancel",
            server: ['CONTENT_TYPE' => 'application/json'],
        );

        self::assertResponseStatusCodeSame(409);
    }

    private function createConfirmedReservation(KernelBrowser $client, string $checkIn, string $checkOut): string
    {
        [$roomId, $bookerId] = $this->setupRoomAndBooker($client);
        $this->setBaseRate($client, $roomId, 10000);

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomId' => $roomId,
                'bookerId' => $bookerId,
                'checkIn' => $checkIn,
                'checkOut' => $checkOut,
                'guestCount' => 1,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $reservationId = $body['id'];

        $client->request(
            method: 'POST',
            uri: '/api/v1/payment/webhooks/success',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['reservation_id' => $reservationId], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        return $reservationId;
    }

    private function createCancelledReservation(KernelBrowser $client, string $checkIn, string $checkOut): string
    {
        [$roomId, $bookerId] = $this->setupRoomAndBooker($client);
        $this->setBaseRate($client, $roomId, 10000);

        $client->request(
            method: 'POST',
            uri: '/api/v1/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'roomId' => $roomId,
                'bookerId' => $bookerId,
                'checkIn' => $checkIn,
                'checkOut' => $checkOut,
                'guestCount' => 1,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $reservationId = $body['id'];

        $client->request(
            method: 'POST',
            uri: '/api/v1/payment/webhooks/cancel',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['reservation_id' => $reservationId], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        return $reservationId;
    }

    /** @return array{string, string} [roomId, bookerId] */
    private function setupRoomAndBooker(KernelBrowser $client): array
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
                'guestCapacity' => 2,
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
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }
}
```

- [ ] **Step 2: Run to verify tests fail (route not wired or missing migration)**

```bash
make functional-test
```

Expected: tests FAIL — 404 on the cancel endpoint (route not yet found), OR migration not applied.

- [ ] **Step 3: Run all tests to verify full suite passes**

```bash
make test
```

Expected: all tests PASS, including the 4 new functional tests.

- [ ] **Step 4: Regenerate OpenAPI spec**

```bash
make openapi
```

Expected: `openapi.yaml` updated with the new `POST /reservations/{id}/cancel` endpoint.

- [ ] **Step 5: Commit**

```bash
git add tests/Reservation/Functional/Controller/CancelReservation/ openapi.yaml
git commit -m "test(reservation): add functional tests for CancelReservation endpoint"
```

---

## Final check

- [ ] **Run the full test suite one last time**

```bash
make test
```

Expected: all tests PASS.

- [ ] **Run full lint**

```bash
make lint
```

Expected: no errors (CS Fixer, PHPStan, Deptrac all clean).

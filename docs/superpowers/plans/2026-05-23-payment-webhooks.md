# Payment Webhooks Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `Payment` bounded context that receives three webhook notifications from a generic payment provider (success, failure, cancellation) and drives `Reservation` lifecycle transitions accordingly.

**Architecture:** The `Payment` context owns three `POST` webhook endpoints. Each dispatches an internal sync command through domain ports to the `Reservation` context (`ConfirmReservationCommand`, `CancelPendingReservationCommand`). `Reservation` emits domain events (`ReservationConfirmed`, `ReservationPaymentCancelled`) that `Availability` listens to in order to convert or delete the `AvailabilityHold`. Payment failure is a **no-op** — the Reservation stays `pending` until natural expiration (15-minute hold). Only the reservation ID is provided by the payment provider in all three cases.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine ORM, Symfony Messenger (sync command bus), Symfony EventDispatcher, PHPUnit

**Branch:** `feat/payment-webhooks`

---

## File Map

### New — `Payment` context

| Path | Purpose |
|------|---------|
| `src/Payment/Domain/Port/ReservationPaymentConfirmerInterface.php` | Port: confirm a Reservation |
| `src/Payment/Domain/Port/ReservationPaymentCancellerInterface.php` | Port: cancel a pending Reservation |
| `src/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommand.php` | Command for payment success webhook |
| `src/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandler.php` | Calls `ReservationPaymentConfirmerInterface` |
| `src/Payment/Application/UseCase/HandlePaymentFailure/HandlePaymentFailureCommand.php` | Command for payment failure webhook |
| `src/Payment/Application/UseCase/HandlePaymentFailure/HandlePaymentFailureCommandHandler.php` | No-op |
| `src/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommand.php` | Command for payment cancellation webhook |
| `src/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandler.php` | Calls `ReservationPaymentCancellerInterface` |
| `src/Payment/Infrastructure/Service/ReservationPaymentConfirmer.php` | Dispatches `ConfirmReservationCommand` via sync bus |
| `src/Payment/Infrastructure/Service/ReservationPaymentCanceller.php` | Dispatches `CancelPendingReservationCommand` via sync bus |
| `src/Payment/UI/Http/Controller/HandlePaymentSuccess/HandlePaymentSuccessController.php` | `POST /payment/webhooks/success` |
| `src/Payment/UI/Http/Controller/HandlePaymentSuccess/HandlePaymentSuccessRequest.php` | Request DTO |
| `src/Payment/UI/Http/Controller/HandlePaymentFailure/HandlePaymentFailureController.php` | `POST /payment/webhooks/failed` |
| `src/Payment/UI/Http/Controller/HandlePaymentFailure/HandlePaymentFailureRequest.php` | Request DTO |
| `src/Payment/UI/Http/Controller/HandlePaymentCancellation/HandlePaymentCancellationController.php` | `POST /payment/webhooks/cancel` |
| `src/Payment/UI/Http/Controller/HandlePaymentCancellation/HandlePaymentCancellationRequest.php` | Request DTO |
| `config/services/payment.yaml` | DI config for Payment context |

### New — `Reservation` context

| Path | Purpose |
|------|---------|
| `src/Reservation/Domain/Event/ReservationConfirmed.php` | Emitted when a Reservation is confirmed |
| `src/Reservation/Domain/Event/ReservationPaymentCancelled.php` | Emitted when a pending Reservation is cancelled via payment abandonment |
| `src/Reservation/Application/UseCase/ConfirmReservation/ConfirmReservationCommand.php` | Command: `pending → confirmed` |
| `src/Reservation/Application/UseCase/ConfirmReservation/ConfirmReservationCommandHandler.php` | Transitions + emits `ReservationConfirmed` |
| `src/Reservation/Application/UseCase/CancelPendingReservation/CancelPendingReservationCommand.php` | Command: `pending → cancelled` |
| `src/Reservation/Application/UseCase/CancelPendingReservation/CancelPendingReservationCommandHandler.php` | Transitions + emits `ReservationPaymentCancelled` |

### Modified — `Reservation` context

| Path | Change |
|------|--------|
| `src/Reservation/Domain/Model/Reservation.php` | Add `confirm()` and `cancelPending()` methods |

### New — `Availability` context

| Path | Purpose |
|------|---------|
| `src/Availability/Infrastructure/EventListener/ReservationConfirmedListener.php` | Deletes Hold + creates Blocked Period |
| `src/Availability/Infrastructure/EventListener/ReservationPaymentCancelledListener.php` | Deletes Hold (symmetric to `ReservationExpiredListener`) |

### Modified — Config

| Path | Change |
|------|--------|
| `config/services.yaml` | Add `payment.yaml` import |

---

## Task 1 — Reservation model: `confirm()` and `cancelPending()`

**Files:**
- Modify: `src/Reservation/Domain/Model/Reservation.php`
- Test: `tests/Reservation/Unit/Domain/Model/ReservationTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Unit\Domain\Model;

use App\Reservation\Domain\Exception\InvalidReservationTransitionException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationTest extends TestCase
{
    private function makeReservation(): Reservation
    {
        return new Reservation(
            id: '00000000-0000-0000-0000-000000000001',
            roomId: '00000000-0000-0000-0000-000000000002',
            bookerId: '00000000-0000-0000-0000-000000000003',
            period: new DatePeriod(
                new \DateTimeImmutable('2026-07-01'),
                new \DateTimeImmutable('2026-07-05'),
            ),
            totalPrice: 40000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: new PriceBreakdown([]),
            createdAt: new \DateTimeImmutable('2026-05-23T10:00:00Z'),
        );
    }

    public function test_confirm_transitions_pending_to_confirmed(): void
    {
        $reservation = $this->makeReservation();
        $reservation->confirm();
        self::assertSame(ReservationStatus::Confirmed, $reservation->status);
    }

    public function test_confirm_throws_if_already_expired(): void
    {
        $reservation = $this->makeReservation();
        $reservation->expire();
        $this->expectException(InvalidReservationTransitionException::class);
        $reservation->confirm();
    }

    public function test_cancel_pending_transitions_pending_to_cancelled(): void
    {
        $reservation = $this->makeReservation();
        $reservation->cancelPending();
        self::assertSame(ReservationStatus::Cancelled, $reservation->status);
    }

    public function test_cancel_pending_throws_if_already_expired(): void
    {
        $reservation = $this->makeReservation();
        $reservation->expire();
        $this->expectException(InvalidReservationTransitionException::class);
        $reservation->cancelPending();
    }
}
```

> **Note:** Check `CancellationTerms::alwaysRefundable()` and `PriceBreakdown` constructor in their respective files if the calls above fail — adjust to the actual factory/constructor signatures.

- [ ] **Step 2: Run tests to verify they fail**

```bash
make unit-test
```

Expected: FAIL — `confirm()` and `cancelPending()` not defined.

- [ ] **Step 3: Add `confirm()` and `cancelPending()` to `Reservation`**

```php
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
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
make unit-test
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Reservation/Domain/Model/Reservation.php tests/Reservation/Unit/Domain/Model/ReservationTest.php
git commit -m "feat(reservation): add confirm() and cancelPending() to Reservation model"
```

---

## Task 2 — Reservation domain events: `ReservationConfirmed` + `ReservationPaymentCancelled`

**Files:**
- Create: `src/Reservation/Domain/Event/ReservationConfirmed.php`
- Create: `src/Reservation/Domain/Event/ReservationPaymentCancelled.php`

These are plain data classes — no tests needed (no logic).

- [ ] **Step 1: Create `ReservationConfirmed`**

`ReservationConfirmed` must carry `roomId`, `checkIn`, `checkOut` so the Availability listener can create a `BlockPeriod` without querying the Reservation context.

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Event;

final readonly class ReservationConfirmed
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

- [ ] **Step 2: Create `ReservationPaymentCancelled`**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Event;

final readonly class ReservationPaymentCancelled
{
    public function __construct(
        public string $reservationId,
    ) {
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Reservation/Domain/Event/ReservationConfirmed.php src/Reservation/Domain/Event/ReservationPaymentCancelled.php
git commit -m "feat(reservation): add ReservationConfirmed and ReservationPaymentCancelled domain events"
```

---

## Task 3 — `ConfirmReservationCommand` + handler

**Files:**
- Create: `src/Reservation/Application/UseCase/ConfirmReservation/ConfirmReservationCommand.php`
- Create: `src/Reservation/Application/UseCase/ConfirmReservation/ConfirmReservationCommandHandler.php`
- Test: `tests/Reservation/Integration/UseCase/ConfirmReservation/ConfirmReservationCommandHandlerTest.php`

- [ ] **Step 1: Write the failing integration test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Integration\UseCase\ConfirmReservation;

use App\Reservation\Application\UseCase\ConfirmReservation\ConfirmReservationCommand;
use App\Reservation\Application\UseCase\ConfirmReservation\ConfirmReservationCommandHandler;
use App\Reservation\Domain\Event\ReservationConfirmed;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[Group('integration')]
final class ConfirmReservationCommandHandlerTest extends KernelTestCase
{
    public function test_confirms_pending_reservation_and_dispatches_event(): void
    {
        $reservation = new Reservation(
            id: 'res-001',
            roomId: 'room-001',
            bookerId: 'booker-001',
            period: new DatePeriod(
                new \DateTimeImmutable('2026-07-01'),
                new \DateTimeImmutable('2026-07-05'),
            ),
            totalPrice: 40000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: new PriceBreakdown([]),
            createdAt: new \DateTimeImmutable(),
        );

        $repository = new InMemoryReservationRepository($reservation);
        $dispatcher = new EventDispatcher();
        $dispatchedEvents = [];
        $dispatcher->addListener(ReservationConfirmed::class, function (ReservationConfirmed $e) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $e;
        });

        $handler = new ConfirmReservationCommandHandler($repository, $dispatcher);
        ($handler)(new ConfirmReservationCommand('res-001'));

        self::assertSame(ReservationStatus::Confirmed, $reservation->status);
        self::assertCount(1, $dispatchedEvents);
        self::assertSame('res-001', $dispatchedEvents[0]->reservationId);
        self::assertSame('room-001', $dispatchedEvents[0]->roomId);
    }

    public function test_is_idempotent_if_reservation_not_pending(): void
    {
        $repository = new InMemoryReservationRepository(null);
        $dispatcher = new EventDispatcher();

        $handler = new ConfirmReservationCommandHandler($repository, $dispatcher);
        ($handler)(new ConfirmReservationCommand('unknown'));

        // No exception — no-op
        $this->addToAssertionCount(1);
    }
}
```

Add the in-memory double in the same test file (or a separate file in the same namespace):

```php
// At the bottom of the test file, outside the test class

use App\Reservation\Domain\Port\ReservationRepositoryInterface;

final class InMemoryReservationRepository implements ReservationRepositoryInterface
{
    public function __construct(private ?Reservation $reservation) {}

    public function get(string $id): ?Reservation
    {
        return $this->reservation?->id === $id ? $this->reservation : null;
    }

    public function add(Reservation $reservation): void {}

    public function save(Reservation $reservation): void {}
}
```

> **Note:** Check `ReservationRepositoryInterface` for the exact method signatures before writing the double.

- [ ] **Step 2: Run tests to verify they fail**

```bash
make integration-test
```

Expected: FAIL — `ConfirmReservationCommand` and handler do not exist.

- [ ] **Step 3: Create `ConfirmReservationCommand`**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\ConfirmReservation;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class ConfirmReservationCommand implements SyncCommandInterface
{
    public function __construct(public string $reservationId)
    {
    }
}
```

- [ ] **Step 4: Create `ConfirmReservationCommandHandler`**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\ConfirmReservation;

use App\Reservation\Domain\Event\ReservationConfirmed;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class ConfirmReservationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ConfirmReservationCommand $command): void
    {
        $reservation = $this->repository->get($command->reservationId);

        if (null === $reservation || ReservationStatus::Pending !== $reservation->status) {
            return;
        }

        $reservation->confirm();
        $this->repository->save($reservation);

        $this->eventDispatcher->dispatch(new ReservationConfirmed(
            reservationId: $reservation->id,
            roomId: $reservation->roomId,
            checkIn: $reservation->period->checkIn,
            checkOut: $reservation->period->checkOut,
        ));
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
make integration-test
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Reservation/Application/UseCase/ConfirmReservation/ tests/Reservation/Integration/UseCase/ConfirmReservation/
git commit -m "feat(reservation): add ConfirmReservation use case"
```

---

## Task 4 — `CancelPendingReservationCommand` + handler

**Files:**
- Create: `src/Reservation/Application/UseCase/CancelPendingReservation/CancelPendingReservationCommand.php`
- Create: `src/Reservation/Application/UseCase/CancelPendingReservation/CancelPendingReservationCommandHandler.php`
- Test: `tests/Reservation/Integration/UseCase/CancelPendingReservation/CancelPendingReservationCommandHandlerTest.php`

- [ ] **Step 1: Write the failing integration test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Integration\UseCase\CancelPendingReservation;

use App\Reservation\Application\UseCase\CancelPendingReservation\CancelPendingReservationCommand;
use App\Reservation\Application\UseCase\CancelPendingReservation\CancelPendingReservationCommandHandler;
use App\Reservation\Domain\Event\ReservationPaymentCancelled;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[Group('integration')]
final class CancelPendingReservationCommandHandlerTest extends KernelTestCase
{
    public function test_cancels_pending_reservation_and_dispatches_event(): void
    {
        $reservation = new Reservation(
            id: 'res-001',
            roomId: 'room-001',
            bookerId: 'booker-001',
            period: new DatePeriod(
                new \DateTimeImmutable('2026-07-01'),
                new \DateTimeImmutable('2026-07-05'),
            ),
            totalPrice: 40000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: new PriceBreakdown([]),
            createdAt: new \DateTimeImmutable(),
        );

        $repository = new InMemoryReservationRepository($reservation);
        $dispatcher = new EventDispatcher();
        $dispatchedEvents = [];
        $dispatcher->addListener(ReservationPaymentCancelled::class, function (ReservationPaymentCancelled $e) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $e;
        });

        $handler = new CancelPendingReservationCommandHandler($repository, $dispatcher);
        ($handler)(new CancelPendingReservationCommand('res-001'));

        self::assertSame(ReservationStatus::Cancelled, $reservation->status);
        self::assertCount(1, $dispatchedEvents);
        self::assertSame('res-001', $dispatchedEvents[0]->reservationId);
    }

    public function test_is_idempotent_if_reservation_not_pending(): void
    {
        $repository = new InMemoryReservationRepository(null);
        $dispatcher = new EventDispatcher();

        $handler = new CancelPendingReservationCommandHandler($repository, $dispatcher);
        ($handler)(new CancelPendingReservationCommand('unknown'));

        $this->addToAssertionCount(1);
    }
}

final class InMemoryReservationRepository implements ReservationRepositoryInterface
{
    public function __construct(private ?Reservation $reservation) {}

    public function get(string $id): ?Reservation
    {
        return $this->reservation?->id === $id ? $this->reservation : null;
    }

    public function add(Reservation $reservation): void {}

    public function save(Reservation $reservation): void {}
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
make integration-test
```

Expected: FAIL — handler does not exist.

- [ ] **Step 3: Create `CancelPendingReservationCommand`**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CancelPendingReservation;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class CancelPendingReservationCommand implements SyncCommandInterface
{
    public function __construct(public string $reservationId)
    {
    }
}
```

- [ ] **Step 4: Create `CancelPendingReservationCommandHandler`**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CancelPendingReservation;

use App\Reservation\Domain\Event\ReservationPaymentCancelled;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class CancelPendingReservationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(CancelPendingReservationCommand $command): void
    {
        $reservation = $this->repository->get($command->reservationId);

        if (null === $reservation || ReservationStatus::Pending !== $reservation->status) {
            return;
        }

        $reservation->cancelPending();
        $this->repository->save($reservation);

        $this->eventDispatcher->dispatch(new ReservationPaymentCancelled(
            reservationId: $reservation->id,
        ));
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
make integration-test
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Reservation/Application/UseCase/CancelPendingReservation/ tests/Reservation/Integration/UseCase/CancelPendingReservation/
git commit -m "feat(reservation): add CancelPendingReservation use case"
```

---

## Task 5 — Availability: `ReservationConfirmedListener`

Converts the `AvailabilityHold` into a `BlockedPeriod` when payment succeeds.

**Files:**
- Create: `src/Availability/Infrastructure/EventListener/ReservationConfirmedListener.php`

No dedicated test needed — the existing functional and integration test suite for Availability covers the commands this listener dispatches. The listener pattern is identical to `ReservationExpiredListener` (already tested).

- [ ] **Step 1: Create the listener**

`BlockPeriodCommand` requires `id`, `roomId`, `checkIn`, `checkOut`, `createdAt`. The listener generates the `BlockedPeriod` id via `BlockedPeriodIdGeneratorInterface`.

```php
<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\EventListener;

use App\Availability\Application\UseCase\BlockPeriod\BlockPeriodCommand;
use App\Availability\Application\UseCase\DeleteAvailabilityHold\DeleteAvailabilityHoldCommand;
use App\Availability\Domain\Port\BlockedPeriodIdGeneratorInterface;
use App\Reservation\Domain\Event\ReservationConfirmed;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationConfirmed::class)]
final readonly class ReservationConfirmedListener
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
        private BlockedPeriodIdGeneratorInterface $idGenerator,
    ) {
    }

    public function __invoke(ReservationConfirmed $event): void
    {
        $this->commandBus->execute(new DeleteAvailabilityHoldCommand(
            reservationId: $event->reservationId,
        ));

        $this->commandBus->execute(new BlockPeriodCommand(
            id: $this->idGenerator->generate(),
            roomId: $event->roomId,
            checkIn: $event->checkIn,
            checkOut: $event->checkOut,
            createdAt: new \DateTimeImmutable(),
        ));
    }
}
```

- [ ] **Step 2: Run linting and tests**

```bash
make lint
make integration-test
```

Expected: PASS. The listener is auto-registered via `#[AsEventListener]` and Symfony autoconfigure — no YAML change needed.

- [ ] **Step 3: Commit**

```bash
git add src/Availability/Infrastructure/EventListener/ReservationConfirmedListener.php
git commit -m "feat(availability): add ReservationConfirmedListener to convert Hold to BlockedPeriod on payment success"
```

---

## Task 6 — Availability: `ReservationPaymentCancelledListener`

Deletes the `AvailabilityHold` when the Booker abandons payment. Symmetric to `ReservationExpiredListener`.

**Files:**
- Create: `src/Availability/Infrastructure/EventListener/ReservationPaymentCancelledListener.php`

- [ ] **Step 1: Create the listener**

```php
<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\EventListener;

use App\Availability\Application\UseCase\DeleteAvailabilityHold\DeleteAvailabilityHoldCommand;
use App\Reservation\Domain\Event\ReservationPaymentCancelled;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationPaymentCancelled::class)]
final readonly class ReservationPaymentCancelledListener
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    public function __invoke(ReservationPaymentCancelled $event): void
    {
        $this->commandBus->execute(new DeleteAvailabilityHoldCommand(
            reservationId: $event->reservationId,
        ));
    }
}
```

- [ ] **Step 2: Run linting and tests**

```bash
make lint
make integration-test
```

Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add src/Availability/Infrastructure/EventListener/ReservationPaymentCancelledListener.php
git commit -m "feat(availability): add ReservationPaymentCancelledListener to delete Hold on payment abandonment"
```

---

## Task 7 — Payment context: domain ports

**Files:**
- Create: `src/Payment/Domain/Port/ReservationPaymentConfirmerInterface.php`
- Create: `src/Payment/Domain/Port/ReservationPaymentCancellerInterface.php`

- [ ] **Step 1: Create the port interfaces**

```php
<?php

declare(strict_types=1);

namespace App\Payment\Domain\Port;

interface ReservationPaymentConfirmerInterface
{
    public function confirm(string $reservationId): void;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Payment\Domain\Port;

interface ReservationPaymentCancellerInterface
{
    public function cancel(string $reservationId): void;
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Payment/Domain/Port/
git commit -m "feat(payment): add ReservationPaymentConfirmerInterface and ReservationPaymentCancellerInterface ports"
```

---

## Task 8 — Payment context: infrastructure implementations

**Files:**
- Create: `src/Payment/Infrastructure/Service/ReservationPaymentConfirmer.php`
- Create: `src/Payment/Infrastructure/Service/ReservationPaymentCanceller.php`

- [ ] **Step 1: Create `ReservationPaymentConfirmer`**

```php
<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Service;

use App\Payment\Domain\Port\ReservationPaymentConfirmerInterface;
use App\Reservation\Application\UseCase\ConfirmReservation\ConfirmReservationCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;

final readonly class ReservationPaymentConfirmer implements ReservationPaymentConfirmerInterface
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    public function confirm(string $reservationId): void
    {
        $this->commandBus->execute(new ConfirmReservationCommand($reservationId));
    }
}
```

- [ ] **Step 2: Create `ReservationPaymentCanceller`**

```php
<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Service;

use App\Payment\Domain\Port\ReservationPaymentCancellerInterface;
use App\Reservation\Application\UseCase\CancelPendingReservation\CancelPendingReservationCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;

final readonly class ReservationPaymentCanceller implements ReservationPaymentCancellerInterface
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    public function cancel(string $reservationId): void
    {
        $this->commandBus->execute(new CancelPendingReservationCommand($reservationId));
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Payment/Infrastructure/Service/
git commit -m "feat(payment): add infrastructure implementations for payment confirmer and canceller ports"
```

---

## Task 9 — Payment application: `HandlePaymentSuccess` use case

**Files:**
- Create: `src/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommand.php`
- Create: `src/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandler.php`
- Test: `tests/Payment/Integration/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandlerTest.php`

- [ ] **Step 1: Write the failing integration test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Payment\Integration\UseCase\HandlePaymentSuccess;

use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommand;
use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommandHandler;
use App\Payment\Domain\Port\ReservationPaymentConfirmerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class HandlePaymentSuccessCommandHandlerTest extends KernelTestCase
{
    public function test_calls_confirmer_with_reservation_id(): void
    {
        $confirmer = new class implements ReservationPaymentConfirmerInterface {
            public ?string $calledWith = null;

            public function confirm(string $reservationId): void
            {
                $this->calledWith = $reservationId;
            }
        };

        $handler = new HandlePaymentSuccessCommandHandler($confirmer);
        ($handler)(new HandlePaymentSuccessCommand('res-001'));

        self::assertSame('res-001', $confirmer->calledWith);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
make integration-test
```

Expected: FAIL — command and handler do not exist.

- [ ] **Step 3: Create `HandlePaymentSuccessCommand`**

```php
<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentSuccess;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class HandlePaymentSuccessCommand implements SyncCommandInterface
{
    public function __construct(public string $reservationId)
    {
    }
}
```

- [ ] **Step 4: Create `HandlePaymentSuccessCommandHandler`**

```php
<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentSuccess;

use App\Payment\Domain\Port\ReservationPaymentConfirmerInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class HandlePaymentSuccessCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private ReservationPaymentConfirmerInterface $confirmer)
    {
    }

    public function __invoke(HandlePaymentSuccessCommand $command): void
    {
        $this->confirmer->confirm($command->reservationId);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
make integration-test
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Payment/Application/UseCase/HandlePaymentSuccess/ tests/Payment/Integration/UseCase/HandlePaymentSuccess/
git commit -m "feat(payment): add HandlePaymentSuccess use case"
```

---

## Task 10 — Payment application: `HandlePaymentFailure` use case

**Files:**
- Create: `src/Payment/Application/UseCase/HandlePaymentFailure/HandlePaymentFailureCommand.php`
- Create: `src/Payment/Application/UseCase/HandlePaymentFailure/HandlePaymentFailureCommandHandler.php`
- Test: `tests/Payment/Integration/UseCase/HandlePaymentFailure/HandlePaymentFailureCommandHandlerTest.php`

- [ ] **Step 1: Write the failing integration test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Payment\Integration\UseCase\HandlePaymentFailure;

use App\Payment\Application\UseCase\HandlePaymentFailure\HandlePaymentFailureCommand;
use App\Payment\Application\UseCase\HandlePaymentFailure\HandlePaymentFailureCommandHandler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class HandlePaymentFailureCommandHandlerTest extends KernelTestCase
{
    public function test_does_nothing_and_does_not_throw(): void
    {
        $handler = new HandlePaymentFailureCommandHandler();
        ($handler)(new HandlePaymentFailureCommand('res-001'));

        $this->addToAssertionCount(1);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
make integration-test
```

Expected: FAIL — command and handler do not exist.

- [ ] **Step 3: Create `HandlePaymentFailureCommand`**

```php
<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentFailure;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class HandlePaymentFailureCommand implements SyncCommandInterface
{
    public function __construct(public string $reservationId)
    {
    }
}
```

- [ ] **Step 4: Create `HandlePaymentFailureCommandHandler`**

```php
<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentFailure;

use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class HandlePaymentFailureCommandHandler implements SyncCommandHandlerInterface
{
    public function __invoke(HandlePaymentFailureCommand $command): void
    {
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
make integration-test
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Payment/Application/UseCase/HandlePaymentFailure/ tests/Payment/Integration/UseCase/HandlePaymentFailure/
git commit -m "feat(payment): add HandlePaymentFailure use case (no-op)"
```

---

## Task 11 — Payment application: `HandlePaymentCancellation` use case

**Files:**
- Create: `src/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommand.php`
- Create: `src/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandler.php`
- Test: `tests/Payment/Integration/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandlerTest.php`

- [ ] **Step 1: Write the failing integration test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Payment\Integration\UseCase\HandlePaymentCancellation;

use App\Payment\Application\UseCase\HandlePaymentCancellation\HandlePaymentCancellationCommand;
use App\Payment\Application\UseCase\HandlePaymentCancellation\HandlePaymentCancellationCommandHandler;
use App\Payment\Domain\Port\ReservationPaymentCancellerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class HandlePaymentCancellationCommandHandlerTest extends KernelTestCase
{
    public function test_calls_canceller_with_reservation_id(): void
    {
        $canceller = new class implements ReservationPaymentCancellerInterface {
            public ?string $calledWith = null;

            public function cancel(string $reservationId): void
            {
                $this->calledWith = $reservationId;
            }
        };

        $handler = new HandlePaymentCancellationCommandHandler($canceller);
        ($handler)(new HandlePaymentCancellationCommand('res-001'));

        self::assertSame('res-001', $canceller->calledWith);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
make integration-test
```

Expected: FAIL.

- [ ] **Step 3: Create `HandlePaymentCancellationCommand`**

```php
<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentCancellation;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class HandlePaymentCancellationCommand implements SyncCommandInterface
{
    public function __construct(public string $reservationId)
    {
    }
}
```

- [ ] **Step 4: Create `HandlePaymentCancellationCommandHandler`**

```php
<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentCancellation;

use App\Payment\Domain\Port\ReservationPaymentCancellerInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class HandlePaymentCancellationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private ReservationPaymentCancellerInterface $canceller)
    {
    }

    public function __invoke(HandlePaymentCancellationCommand $command): void
    {
        $this->canceller->cancel($command->reservationId);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
make integration-test
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Payment/Application/UseCase/HandlePaymentCancellation/ tests/Payment/Integration/UseCase/HandlePaymentCancellation/
git commit -m "feat(payment): add HandlePaymentCancellation use case"
```

---

## Task 12 — Payment UI: webhook controllers + DI config

All three controllers follow the same pattern: map `reservationId` from the request body, dispatch the command via the sync bus, return `204 No Content`. The `HandlePaymentFailure` controller is identical in shape even though the handler is a no-op.

**Files:**
- Create: `src/Payment/UI/Http/Controller/HandlePaymentSuccess/HandlePaymentSuccessRequest.php`
- Create: `src/Payment/UI/Http/Controller/HandlePaymentSuccess/HandlePaymentSuccessController.php`
- Create: `src/Payment/UI/Http/Controller/HandlePaymentFailure/HandlePaymentFailureRequest.php`
- Create: `src/Payment/UI/Http/Controller/HandlePaymentFailure/HandlePaymentFailureController.php`
- Create: `src/Payment/UI/Http/Controller/HandlePaymentCancellation/HandlePaymentCancellationRequest.php`
- Create: `src/Payment/UI/Http/Controller/HandlePaymentCancellation/HandlePaymentCancellationController.php`
- Create: `config/services/payment.yaml`
- Modify: `config/services.yaml`
- Test: `tests/Payment/Functional/Controller/PaymentWebhookControllerTest.php`

- [ ] **Step 1: Write the failing functional tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Payment\Functional\Controller;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class PaymentWebhookControllerTest extends WebTestCase
{
    private function post(KernelBrowser $client, string $url, array $body): void
    {
        $client->request(
            method: 'POST',
            uri: $url,
            content: json_encode($body, \JSON_THROW_ON_ERROR),
            server: ['CONTENT_TYPE' => 'application/json'],
        );
    }

    public function test_success_webhook_returns_204(): void
    {
        $client = static::createClient();
        $this->post($client, '/payment/webhooks/success', ['reservation_id' => '00000000-0000-0000-0000-000000000001']);
        self::assertResponseStatusCodeSame(204);
    }

    public function test_failed_webhook_returns_204(): void
    {
        $client = static::createClient();
        $this->post($client, '/payment/webhooks/failed', ['reservation_id' => '00000000-0000-0000-0000-000000000001']);
        self::assertResponseStatusCodeSame(204);
    }

    public function test_cancel_webhook_returns_204(): void
    {
        $client = static::createClient();
        $this->post($client, '/payment/webhooks/cancel', ['reservation_id' => '00000000-0000-0000-0000-000000000001']);
        self::assertResponseStatusCodeSame(204);
    }

    public function test_success_webhook_returns_422_if_reservation_id_missing(): void
    {
        $client = static::createClient();
        $this->post($client, '/payment/webhooks/success', []);
        self::assertResponseStatusCodeSame(422);
    }
}
```

- [ ] **Step 2: Create request DTOs (all three follow the same shape)**

```php
<?php

declare(strict_types=1);

namespace App\Payment\UI\Http\Controller\HandlePaymentSuccess;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class HandlePaymentSuccessRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        public string $reservationId = '',
    ) {
    }
}
```

Create `HandlePaymentFailureRequest` and `HandlePaymentCancellationRequest` with the same content, adjusting the namespace to `HandlePaymentFailure` and `HandlePaymentCancellation` respectively.

- [ ] **Step 3: Create controllers**

```php
<?php

declare(strict_types=1);

namespace App\Payment\UI\Http\Controller\HandlePaymentSuccess;

use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HandlePaymentSuccessController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route('/payment/webhooks/success', name: 'payment_webhook_success', methods: ['POST'])]
    #[OA\Post(
        path: '/payment/webhooks/success',
        summary: 'Payment success webhook',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reservation_id'],
                properties: [new OA\Property(property: 'reservation_id', type: 'string', format: 'uuid')],
            ),
        ),
        tags: ['Payment'],
        responses: [new OA\Response(response: 204, description: 'Acknowledged')],
    )]
    public function __invoke(
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        HandlePaymentSuccessRequest $request,
    ): Response {
        $this->commandBus->execute(new HandlePaymentSuccessCommand($request->reservationId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
```

Create `HandlePaymentFailureController` and `HandlePaymentCancellationController` with the same structure:
- Route: `/payment/webhooks/failed` / `/payment/webhooks/cancel`
- Name: `payment_webhook_failed` / `payment_webhook_cancel`
- Command: `HandlePaymentFailureCommand` / `HandlePaymentCancellationCommand`
- Request: `HandlePaymentFailureRequest` / `HandlePaymentCancellationRequest`
- Summary: `Payment failure webhook` / `Payment cancellation webhook`

- [ ] **Step 4: Create `config/services/payment.yaml`**

```yaml
parameters: {}

services:
    _defaults:
        autowire: true
        autoconfigure: true
    _instanceof:
        App\Shared\Application\Bus\SyncCommandHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: sync.command.bus}

    App\Payment\Domain\:
        resource: '../../src/Payment/Domain/'

    App\Payment\Application\:
        resource: '../../src/Payment/Application/'
        exclude:
            - '../../src/Payment/Application/**/*Command.php'

    App\Payment\Infrastructure\:
        resource: '../../src/Payment/Infrastructure/'

    App\Payment\UI\:
        resource: '../../src/Payment/UI/'
        exclude:
            - '../../src/Payment/UI/**/*Request.php'
```

- [ ] **Step 5: Add the import to `config/services.yaml`**

Add at the end of the `imports:` section:

```yaml
    - { resource: './services/payment.yaml' }
```

- [ ] **Step 6: Run functional tests to verify they pass**

```bash
make functional-test
```

Expected: PASS (the success and cancel webhooks trigger command chains that reach in-memory state; no database needed for the 204 assertion).

> **Note:** If the functional tests fail because `ConfirmReservationCommandHandler` or `CancelPendingReservationCommandHandler` try to reach a real database (via the Doctrine repository), override the `ReservationRepositoryInterface` binding in the test environment to an in-memory double, or mock it via `static::getContainer()->set()`.

- [ ] **Step 7: Run full lint suite**

```bash
make lint
```

Expected: PASS (deptrac, CS Fixer, PHPStan).

- [ ] **Step 8: Commit**

```bash
git add src/Payment/UI/ config/services/payment.yaml config/services.yaml tests/Payment/Functional/
git commit -m "feat(payment): add webhook controllers and DI config"
```

---

## Task 13 — OpenAPI

- [ ] **Step 1: Regenerate the OpenAPI spec**

```bash
make openapi
```

- [ ] **Step 2: Verify the three new routes appear**

Open the generated spec and confirm:
- `POST /payment/webhooks/success` — 204 + 422 responses, `reservation_id` body
- `POST /payment/webhooks/failed` — same shape
- `POST /payment/webhooks/cancel` — same shape

- [ ] **Step 3: Commit**

```bash
git add public/api/openapi.yaml  # adjust path to match your project
git commit -m "docs(openapi): add payment webhook endpoints"
```

---

## Self-Review

**Spec coverage:**
- ✅ 3 webhooks (success, failed, cancel) with dedicated commands and handlers
- ✅ `success` → `pending → confirmed` + `ReservationConfirmed` event + Availability converts Hold to Blocked Period
- ✅ `failed` → no-op, Reservation stays `pending`, natural expiration handles cleanup
- ✅ `cancel` → `pending → cancelled` + `ReservationPaymentCancelled` event + Availability deletes Hold
- ✅ Only `reservationId` in webhook payload
- ✅ New `Payment` bounded context (Option A)
- ✅ Domain ports (`ReservationPaymentConfirmerInterface`, `ReservationPaymentCancellerInterface`) in Payment domain
- ✅ Infrastructure implementations dispatch sync commands to Reservation context
- ✅ `ReservationPaymentCancelled` distinct from `ReservationCancelled` (ADR-0013)
- ✅ All handlers idempotent (early return if not pending / not found)
- ✅ DI config with `_instanceof`, resource exclusions for Commands and Requests

**Type consistency check:**
- `HandlePaymentSuccessCommand.reservationId` → `HandlePaymentSuccessCommandHandler` → `ReservationPaymentConfirmerInterface::confirm(string)` → `ReservationPaymentConfirmer` → `ConfirmReservationCommand(string)` → `ConfirmReservationCommandHandler` → `Reservation::confirm()` ✅
- `ReservationConfirmed` carries `reservationId`, `roomId`, `checkIn`, `checkOut` — all used by `ReservationConfirmedListener` ✅
- `ReservationPaymentCancelled` carries `reservationId` — used by `ReservationPaymentCancelledListener` and `DeleteAvailabilityHoldCommand` ✅

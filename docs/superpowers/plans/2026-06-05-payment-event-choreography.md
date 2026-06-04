# Payment/Reservation Decoupling — Event Choreography Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate Payment's build-time dependency on Reservation internals by replacing direct command dispatch with domain event choreography — Payment emits `PaymentConfirmed`/`PaymentCancelled`, Reservation listens and reacts internally.

**Architecture:** Two new readonly event classes land in `Shared/Domain/Event/`. Payment's two handlers inject `EventDispatcherInterface` (Psr, allowed in Application layer) and dispatch those events. Two new listeners in `Reservation/Infrastructure/EventListener/` receive the events and dispatch the internal commands via `SyncCommandBusInterface`. The four orphaned Payment files (two port interfaces + two infrastructure services) are deleted. Symfony's EventDispatcher is synchronous — no eventual consistency is introduced.

**Tech Stack:** PHP 8.4, Symfony 8.0, `Psr\EventDispatcher\EventDispatcherInterface`, `Symfony\Component\EventDispatcher\Attribute\AsEventListener`, `App\Shared\Application\Bus\SyncCommandBusInterface`, PHPUnit 11 (unit tests — `#[Group('unit')]`), `make unit-test`, `make lint`

---

### Task 1: Create PaymentConfirmed and PaymentCancelled domain events

**Files:**
- Create: `src/Shared/Domain/Event/PaymentConfirmed.php`
- Create: `src/Shared/Domain/Event/PaymentCancelled.php`

- [ ] **Step 1: Create PaymentConfirmed**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class PaymentConfirmed
{
    public function __construct(
        public string $reservationId,
    ) {
    }
}
```

- [ ] **Step 2: Create PaymentCancelled**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class PaymentCancelled
{
    public function __construct(
        public string $reservationId,
    ) {
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Shared/Domain/Event/PaymentConfirmed.php src/Shared/Domain/Event/PaymentCancelled.php
git commit -m "feat(payment): add PaymentConfirmed and PaymentCancelled domain events"
```

---

### Task 2: Update HandlePaymentSuccessCommandHandler (TDD)

**Files:**
- Modify: `src/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandler.php`
- Modify: `tests/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandlerTest.php`

- [ ] **Step 1: Update the test to assert the new behaviour (it will fail)**

Replace the full content of `tests/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Payment\Application\UseCase\HandlePaymentSuccess;

use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommand;
use App\Payment\Application\UseCase\HandlePaymentSuccess\HandlePaymentSuccessCommandHandler;
use App\Shared\Domain\Event\PaymentConfirmed;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class HandlePaymentSuccessCommandHandlerTest extends TestCase
{
    public function test_it_dispatches_payment_confirmed_event(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(new PaymentConfirmed('reservation-id-123'));

        $handler = new HandlePaymentSuccessCommandHandler($dispatcher);
        $handler(new HandlePaymentSuccessCommand('reservation-id-123'));
    }
}
```

- [ ] **Step 2: Run the test — expect FAIL**

```bash
make unit-test
```

Expected: test fails (TypeError — handler still expects `ReservationPaymentConfirmerInterface`).

- [ ] **Step 3: Update the handler**

Replace the full content of `src/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentSuccess;

use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\PaymentConfirmed;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class HandlePaymentSuccessCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function __invoke(HandlePaymentSuccessCommand $command): void
    {
        $this->eventDispatcher->dispatch(new PaymentConfirmed($command->reservationId));
    }
}
```

- [ ] **Step 4: Run the test — expect PASS**

```bash
make unit-test
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
git add src/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandler.php \
        tests/Payment/Application/UseCase/HandlePaymentSuccess/HandlePaymentSuccessCommandHandlerTest.php
git commit -m "refactor(payment): replace ReservationPaymentConfirmer with PaymentConfirmed event dispatch"
```

---

### Task 3: Update HandlePaymentCancellationCommandHandler (TDD)

**Files:**
- Modify: `src/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandler.php`
- Modify: `tests/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandlerTest.php`

- [ ] **Step 1: Update the test to assert the new behaviour (it will fail)**

Replace the full content of `tests/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Payment\Application\UseCase\HandlePaymentCancellation;

use App\Payment\Application\UseCase\HandlePaymentCancellation\HandlePaymentCancellationCommand;
use App\Payment\Application\UseCase\HandlePaymentCancellation\HandlePaymentCancellationCommandHandler;
use App\Shared\Domain\Event\PaymentCancelled;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class HandlePaymentCancellationCommandHandlerTest extends TestCase
{
    public function test_it_dispatches_payment_cancelled_event(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(new PaymentCancelled('reservation-id-456'));

        $handler = new HandlePaymentCancellationCommandHandler($dispatcher);
        $handler(new HandlePaymentCancellationCommand('reservation-id-456'));
    }
}
```

- [ ] **Step 2: Run the test — expect FAIL**

```bash
make unit-test
```

Expected: test fails (TypeError — handler still expects `ReservationPaymentCancellerInterface`).

- [ ] **Step 3: Update the handler**

Replace the full content of `src/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase\HandlePaymentCancellation;

use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\PaymentCancelled;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class HandlePaymentCancellationCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function __invoke(HandlePaymentCancellationCommand $command): void
    {
        $this->eventDispatcher->dispatch(new PaymentCancelled($command->reservationId));
    }
}
```

- [ ] **Step 4: Run the test — expect PASS**

```bash
make unit-test
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
git add src/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandler.php \
        tests/Payment/Application/UseCase/HandlePaymentCancellation/HandlePaymentCancellationCommandHandlerTest.php
git commit -m "refactor(payment): replace ReservationPaymentCanceller with PaymentCancelled event dispatch"
```

---

### Task 4: Create PaymentConfirmedListener in Reservation (TDD)

**Files:**
- Create: `tests/Reservation/Infrastructure/EventListener/PaymentConfirmedListenerTest.php`
- Create: `src/Reservation/Infrastructure/EventListener/PaymentConfirmedListener.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Reservation/Infrastructure/EventListener/PaymentConfirmedListenerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\EventListener;

use App\Reservation\Application\UseCase\ConfirmReservation\ConfirmReservationCommand;
use App\Reservation\Infrastructure\EventListener\PaymentConfirmedListener;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Domain\Event\PaymentConfirmed;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PaymentConfirmedListenerTest extends TestCase
{
    public function test_it_dispatches_confirm_reservation_command(): void
    {
        $bus = $this->createMock(SyncCommandBusInterface::class);
        $bus
            ->expects($this->once())
            ->method('execute')
            ->with(new ConfirmReservationCommand('reservation-id-123'));

        $listener = new PaymentConfirmedListener($bus);
        $listener(new PaymentConfirmed('reservation-id-123'));
    }
}
```

- [ ] **Step 2: Run the test — expect FAIL**

```bash
make unit-test
```

Expected: FAIL — `PaymentConfirmedListener` does not exist yet.

- [ ] **Step 3: Create the listener**

Create `src/Reservation/Infrastructure/EventListener/PaymentConfirmedListener.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\EventListener;

use App\Reservation\Application\UseCase\ConfirmReservation\ConfirmReservationCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Domain\Event\PaymentConfirmed;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PaymentConfirmed::class)]
final readonly class PaymentConfirmedListener
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    public function __invoke(PaymentConfirmed $event): void
    {
        $this->commandBus->execute(new ConfirmReservationCommand($event->reservationId));
    }
}
```

- [ ] **Step 4: Run the test — expect PASS**

```bash
make unit-test
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
git add src/Reservation/Infrastructure/EventListener/PaymentConfirmedListener.php \
        tests/Reservation/Infrastructure/EventListener/PaymentConfirmedListenerTest.php
git commit -m "feat(reservation): add PaymentConfirmedListener to confirm reservation on payment"
```

---

### Task 5: Create PaymentCancelledListener in Reservation (TDD)

**Files:**
- Create: `tests/Reservation/Infrastructure/EventListener/PaymentCancelledListenerTest.php`
- Create: `src/Reservation/Infrastructure/EventListener/PaymentCancelledListener.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Reservation/Infrastructure/EventListener/PaymentCancelledListenerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\EventListener;

use App\Reservation\Application\UseCase\CancelPendingReservation\CancelPendingReservationCommand;
use App\Reservation\Infrastructure\EventListener\PaymentCancelledListener;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Domain\Event\PaymentCancelled;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PaymentCancelledListenerTest extends TestCase
{
    public function test_it_dispatches_cancel_pending_reservation_command(): void
    {
        $bus = $this->createMock(SyncCommandBusInterface::class);
        $bus
            ->expects($this->once())
            ->method('execute')
            ->with(new CancelPendingReservationCommand('reservation-id-456'));

        $listener = new PaymentCancelledListener($bus);
        $listener(new PaymentCancelled('reservation-id-456'));
    }
}
```

- [ ] **Step 2: Run the test — expect FAIL**

```bash
make unit-test
```

Expected: FAIL — `PaymentCancelledListener` does not exist yet.

- [ ] **Step 3: Create the listener**

Create `src/Reservation/Infrastructure/EventListener/PaymentCancelledListener.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\EventListener;

use App\Reservation\Application\UseCase\CancelPendingReservation\CancelPendingReservationCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Domain\Event\PaymentCancelled;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PaymentCancelled::class)]
final readonly class PaymentCancelledListener
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    public function __invoke(PaymentCancelled $event): void
    {
        $this->commandBus->execute(new CancelPendingReservationCommand($event->reservationId));
    }
}
```

- [ ] **Step 4: Run the test — expect PASS**

```bash
make unit-test
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
git add src/Reservation/Infrastructure/EventListener/PaymentCancelledListener.php \
        tests/Reservation/Infrastructure/EventListener/PaymentCancelledListenerTest.php
git commit -m "feat(reservation): add PaymentCancelledListener to cancel pending reservation on payment cancellation"
```

---

### Task 6: Delete the four orphaned Payment files

**Files:**
- Delete: `src/Payment/Domain/Port/ReservationPaymentConfirmerInterface.php`
- Delete: `src/Payment/Domain/Port/ReservationPaymentCancellerInterface.php`
- Delete: `src/Payment/Infrastructure/Service/ReservationPaymentConfirmer.php`
- Delete: `src/Payment/Infrastructure/Service/ReservationPaymentCanceller.php`

- [ ] **Step 1: Delete the files**

```bash
git rm src/Payment/Domain/Port/ReservationPaymentConfirmerInterface.php \
       src/Payment/Domain/Port/ReservationPaymentCancellerInterface.php \
       src/Payment/Infrastructure/Service/ReservationPaymentConfirmer.php \
       src/Payment/Infrastructure/Service/ReservationPaymentCanceller.php
```

- [ ] **Step 2: Run full test suite**

```bash
make unit-test
```

Expected: green — nothing references these files anymore.

- [ ] **Step 3: Commit**

```bash
git commit -m "refactor(payment): delete orphaned ReservationPaymentConfirmer/Canceller ports and services"
```

---

### Task 7: Update domainevents.yaml and run full lint

**Files:**
- Modify: `domainevents.yaml`

- [ ] **Step 1: Add PaymentConfirmed and PaymentCancelled to domainevents.yaml**

In `domainevents.yaml`, add after the existing `ReservationPaymentCancelled` entry:

```yaml
  PaymentConfirmed:
    class: App\Shared\Domain\Event\PaymentConfirmed
    properties:
      reservationId: string
    listeners:
      - { context: Reservation, class: App\Reservation\Infrastructure\EventListener\PaymentConfirmedListener }

  PaymentCancelled:
    class: App\Shared\Domain\Event\PaymentCancelled
    properties:
      reservationId: string
    listeners:
      - { context: Reservation, class: App\Reservation\Infrastructure\EventListener\PaymentCancelledListener }
```

- [ ] **Step 2: Run full lint (CS Fixer + PHPStan + Deptrac)**

```bash
make lint
```

Expected: green. PHPStan must find no references to the deleted interfaces. Deptrac must not flag any cross-context layer violations.

- [ ] **Step 3: Run full test suite**

```bash
make test
```

Expected: all tests green (unit + functional).

- [ ] **Step 4: Commit**

```bash
git add domainevents.yaml
git commit -m "docs(events): register PaymentConfirmed and PaymentCancelled in domain events catalogue"
```

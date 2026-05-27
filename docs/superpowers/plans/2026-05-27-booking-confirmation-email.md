# Booking Confirmation Email — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Send an async plain-text email to the Booker when their Reservation is confirmed.

**Architecture:** `ConfirmReservationCommandHandler` dispatches `ReservationConfirmed` (enriched with `bookerId`). A new `Notification` context listens synchronously and dispatches `SendBookingConfirmationEmailCommand` over AMQP. The async handler fetches Booker contact and Reservation details via domain ports backed by existing query handlers, then sends a plain-text email via Symfony Mailer. No persistence — outcomes are logged.

**Tech Stack:** PHP 8.4 / Symfony 8.0, Symfony Mailer (already installed), Symfony Messenger (AMQP transport, already configured), PHPUnit.

---

## File Map

**Modified:**
- `src/Reservation/Domain/Event/ReservationConfirmed.php` — add `bookerId` field
- `src/Reservation/Application/UseCase/ConfirmReservation/ConfirmReservationCommandHandler.php` — pass `bookerId` to event constructor
- `tests/Reservation/Integration/UseCase/ConfirmReservation/ConfirmReservationCommandHandlerTest.php` — assert `bookerId` in dispatched event
- `config/services.yaml` — import `notification.yaml`
- `.env` — add `MAILER_FROM`

**Created:**
- `src/Notification/Domain/ReadModel/BookerContact.php`
- `src/Notification/Domain/ReadModel/ReservationDetails.php`
- `src/Notification/Domain/Port/BookerContactFetcherInterface.php`
- `src/Notification/Domain/Port/ReservationDetailsFetcherInterface.php`
- `src/Notification/Application/UseCase/SendBookingConfirmationEmail/SendBookingConfirmationEmailCommand.php`
- `src/Notification/Application/UseCase/SendBookingConfirmationEmail/SendBookingConfirmationEmailCommandHandler.php`
- `src/Notification/Infrastructure/EventListener/ReservationConfirmedListener.php`
- `src/Notification/Infrastructure/Service/BookerContactFetcher.php`
- `src/Notification/Infrastructure/Service/ReservationDetailsFetcher.php`
- `config/services/notification.yaml`
- `tests/Notification/Application/UseCase/SendBookingConfirmationEmail/SendBookingConfirmationEmailCommandHandlerTest.php`
- `tests/Notification/Infrastructure/EventListener/ReservationConfirmedListenerTest.php`
- `tests/Notification/Infrastructure/Service/BookerContactFetcherTest.php`
- `tests/Notification/Infrastructure/Service/ReservationDetailsFetcherTest.php`

---

## Task 1: Create feature branch

- [ ] **Step 1: Create and switch to feature branch**

```bash
git checkout -b feat/booking-confirmation-email
```

---

## Task 2: Enrich `ReservationConfirmed` with `bookerId` (TDD)

**Files:**
- Modify: `tests/Reservation/Integration/UseCase/ConfirmReservation/ConfirmReservationCommandHandlerTest.php`
- Modify: `src/Reservation/Domain/Event/ReservationConfirmed.php`
- Modify: `src/Reservation/Application/UseCase/ConfirmReservation/ConfirmReservationCommandHandler.php`

- [ ] **Step 1: Add `bookerId` assertion to the existing test**

In `tests/Reservation/Integration/UseCase/ConfirmReservation/ConfirmReservationCommandHandlerTest.php`, extend the assertion in `test_confirms_pending_reservation_and_dispatches_event`:

```php
self::assertSame('booker-001', $dispatchedEvents[0]->bookerId);
```

The full updated test method:

```php
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
    $dispatcher->addListener(ReservationConfirmed::class, function (ReservationConfirmed $e) use (&$dispatchedEvents): void {
        $dispatchedEvents[] = $e;
    });

    $handler = new ConfirmReservationCommandHandler($repository, $dispatcher);
    ($handler)(new ConfirmReservationCommand('res-001'));

    self::assertSame(ReservationStatus::Confirmed, $reservation->status);
    self::assertCount(1, $dispatchedEvents);
    self::assertSame('res-001', $dispatchedEvents[0]->reservationId);
    self::assertSame('room-001', $dispatchedEvents[0]->roomId);
    self::assertSame('booker-001', $dispatchedEvents[0]->bookerId);
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
make unit-test ARGS="--filter=ConfirmReservationCommandHandlerTest"
```

Expected: FAIL — `bookerId` property does not exist on `ReservationConfirmed`.

- [ ] **Step 3: Add `bookerId` to `ReservationConfirmed`**

Full file content of `src/Reservation/Domain/Event/ReservationConfirmed.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Event;

final readonly class ReservationConfirmed
{
    public function __construct(
        public string $reservationId,
        public string $roomId,
        public string $bookerId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}
```

- [ ] **Step 4: Pass `bookerId` in `ConfirmReservationCommandHandler`**

Full file content of `src/Reservation/Application/UseCase/ConfirmReservation/ConfirmReservationCommandHandler.php`:

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
            bookerId: $reservation->bookerId,
            checkIn: $reservation->period->checkIn,
            checkOut: $reservation->period->checkOut,
        ));
    }
}
```

- [ ] **Step 5: Run tests to confirm they pass**

```bash
make unit-test ARGS="--filter=ConfirmReservationCommandHandlerTest"
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Reservation/Domain/Event/ReservationConfirmed.php \
        src/Reservation/Application/UseCase/ConfirmReservation/ConfirmReservationCommandHandler.php \
        tests/Reservation/Integration/UseCase/ConfirmReservation/ConfirmReservationCommandHandlerTest.php
git commit -m "feat(reservation): add bookerId to ReservationConfirmed event"
```

---

## Task 3: Notification context skeleton — read models, ports, command

These are pure data classes with no behaviour. No tests needed here — they will be exercised in Tasks 4, 5, and 6.

**Files:**
- Create: `src/Notification/Domain/ReadModel/BookerContact.php`
- Create: `src/Notification/Domain/ReadModel/ReservationDetails.php`
- Create: `src/Notification/Domain/Port/BookerContactFetcherInterface.php`
- Create: `src/Notification/Domain/Port/ReservationDetailsFetcherInterface.php`
- Create: `src/Notification/Application/UseCase/SendBookingConfirmationEmail/SendBookingConfirmationEmailCommand.php`

- [ ] **Step 1: Create `BookerContact` read model**

`src/Notification/Domain/ReadModel/BookerContact.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notification\Domain\ReadModel;

final readonly class BookerContact
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
    ) {
    }
}
```

- [ ] **Step 2: Create `ReservationDetails` read model**

`src/Notification/Domain/ReadModel/ReservationDetails.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notification\Domain\ReadModel;

final readonly class ReservationDetails
{
    public function __construct(
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $totalPriceCents,
    ) {
    }
}
```

- [ ] **Step 3: Create `BookerContactFetcherInterface` port**

`src/Notification/Domain/Port/BookerContactFetcherInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notification\Domain\Port;

use App\Notification\Domain\ReadModel\BookerContact;

interface BookerContactFetcherInterface
{
    public function fetch(string $bookerId): ?BookerContact;
}
```

- [ ] **Step 4: Create `ReservationDetailsFetcherInterface` port**

`src/Notification/Domain/Port/ReservationDetailsFetcherInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notification\Domain\Port;

use App\Notification\Domain\ReadModel\ReservationDetails;

interface ReservationDetailsFetcherInterface
{
    public function fetch(string $reservationId): ?ReservationDetails;
}
```

- [ ] **Step 5: Create `SendBookingConfirmationEmailCommand`**

`src/Notification/Application/UseCase/SendBookingConfirmationEmail/SendBookingConfirmationEmailCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\UseCase\SendBookingConfirmationEmail;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class SendBookingConfirmationEmailCommand implements AsyncCommandInterface
{
    public function __construct(
        public string $reservationId,
        public string $bookerId,
    ) {
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add src/Notification/
git commit -m "feat(notification): add domain skeleton (read models, ports, command)"
```

---

## Task 4: `SendBookingConfirmationEmailCommandHandler` (TDD)

**Files:**
- Create: `tests/Notification/Application/UseCase/SendBookingConfirmationEmail/SendBookingConfirmationEmailCommandHandlerTest.php`
- Create: `src/Notification/Application/UseCase/SendBookingConfirmationEmail/SendBookingConfirmationEmailCommandHandler.php`

- [ ] **Step 1: Write the failing tests**

`tests/Notification/Application/UseCase/SendBookingConfirmationEmail/SendBookingConfirmationEmailCommandHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application\UseCase\SendBookingConfirmationEmail;

use App\Notification\Application\UseCase\SendBookingConfirmationEmail\SendBookingConfirmationEmailCommand;
use App\Notification\Application\UseCase\SendBookingConfirmationEmail\SendBookingConfirmationEmailCommandHandler;
use App\Notification\Domain\Port\BookerContactFetcherInterface;
use App\Notification\Domain\Port\ReservationDetailsFetcherInterface;
use App\Notification\Domain\ReadModel\BookerContact;
use App\Notification\Domain\ReadModel\ReservationDetails;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

#[Group('unit')]
final class SendBookingConfirmationEmailCommandHandlerTest extends TestCase
{
    private BookerContactFetcherInterface $bookerContactFetcher;
    private ReservationDetailsFetcherInterface $reservationDetailsFetcher;
    /** @var MailerInterface&MockObject */
    private MailerInterface $mailer;
    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;
    private SendBookingConfirmationEmailCommandHandler $handler;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function test_sends_email_when_booker_and_reservation_exist(): void
    {
        $this->bookerContactFetcher = new class implements BookerContactFetcherInterface {
            public function fetch(string $bookerId): ?BookerContact
            {
                return new BookerContact('Jean', 'Dupont', 'jean.dupont@example.com');
            }
        };

        $this->reservationDetailsFetcher = new class implements ReservationDetailsFetcherInterface {
            public function fetch(string $reservationId): ?ReservationDetails
            {
                return new ReservationDetails(
                    new \DateTimeImmutable('2026-07-01'),
                    new \DateTimeImmutable('2026-07-05'),
                    40000,
                );
            }
        };

        $this->handler = new SendBookingConfirmationEmailCommandHandler(
            $this->bookerContactFetcher,
            $this->reservationDetailsFetcher,
            $this->mailer,
            $this->logger,
            'noreply@book.it',
        );

        /** @var Email|null $sentEmail */
        $sentEmail = null;
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willReturnCallback(static function (RawMessage $message) use (&$sentEmail): void {
                $sentEmail = $message;
            });

        $this->logger->expects($this->once())->method('info');

        ($this->handler)(new SendBookingConfirmationEmailCommand('res-001', 'booker-001'));

        self::assertNotNull($sentEmail);
        self::assertInstanceOf(Email::class, $sentEmail);
        self::assertSame('Votre réservation est confirmée', $sentEmail->getSubject());
        self::assertStringContainsString('jean.dupont@example.com', $sentEmail->getTo()[0]->getAddress());
        self::assertStringContainsString('noreply@book.it', $sentEmail->getFrom()[0]->getAddress());
        self::assertStringContainsString('Jean', $sentEmail->getTextBody());
        self::assertStringContainsString('01/07/2026', $sentEmail->getTextBody());
        self::assertStringContainsString('05/07/2026', $sentEmail->getTextBody());
        self::assertStringContainsString('400.00', $sentEmail->getTextBody());
    }

    public function test_logs_warning_and_does_not_send_when_booker_not_found(): void
    {
        $this->bookerContactFetcher = new class implements BookerContactFetcherInterface {
            public function fetch(string $bookerId): ?BookerContact
            {
                return null;
            }
        };

        $this->reservationDetailsFetcher = new class implements ReservationDetailsFetcherInterface {
            public function fetch(string $reservationId): ?ReservationDetails
            {
                return new ReservationDetails(
                    new \DateTimeImmutable('2026-07-01'),
                    new \DateTimeImmutable('2026-07-05'),
                    40000,
                );
            }
        };

        $this->handler = new SendBookingConfirmationEmailCommandHandler(
            $this->bookerContactFetcher,
            $this->reservationDetailsFetcher,
            $this->mailer,
            $this->logger,
            'noreply@book.it',
        );

        $this->mailer->expects($this->never())->method('send');
        $this->logger->expects($this->once())->method('warning');

        ($this->handler)(new SendBookingConfirmationEmailCommand('res-001', 'booker-unknown'));
    }

    public function test_logs_warning_and_does_not_send_when_reservation_not_found(): void
    {
        $this->bookerContactFetcher = new class implements BookerContactFetcherInterface {
            public function fetch(string $bookerId): ?BookerContact
            {
                return new BookerContact('Jean', 'Dupont', 'jean.dupont@example.com');
            }
        };

        $this->reservationDetailsFetcher = new class implements ReservationDetailsFetcherInterface {
            public function fetch(string $reservationId): ?ReservationDetails
            {
                return null;
            }
        };

        $this->handler = new SendBookingConfirmationEmailCommandHandler(
            $this->bookerContactFetcher,
            $this->reservationDetailsFetcher,
            $this->mailer,
            $this->logger,
            'noreply@book.it',
        );

        $this->mailer->expects($this->never())->method('send');
        $this->logger->expects($this->once())->method('warning');

        ($this->handler)(new SendBookingConfirmationEmailCommand('res-unknown', 'booker-001'));
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
make unit-test ARGS="--filter=SendBookingConfirmationEmailCommandHandlerTest"
```

Expected: FAIL — class `SendBookingConfirmationEmailCommandHandler` does not exist.

- [ ] **Step 3: Implement the handler**

`src/Notification/Application/UseCase/SendBookingConfirmationEmail/SendBookingConfirmationEmailCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\UseCase\SendBookingConfirmationEmail;

use App\Notification\Domain\Port\BookerContactFetcherInterface;
use App\Notification\Domain\Port\ReservationDetailsFetcherInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final readonly class SendBookingConfirmationEmailCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(
        private BookerContactFetcherInterface $bookerContactFetcher,
        private ReservationDetailsFetcherInterface $reservationDetailsFetcher,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailerFrom,
    ) {
    }

    public function __invoke(SendBookingConfirmationEmailCommand $command): void
    {
        $bookerContact = $this->bookerContactFetcher->fetch($command->bookerId);

        if (null === $bookerContact) {
            $this->logger->warning('Booking confirmation email skipped: booker not found', [
                'bookerId' => $command->bookerId,
                'reservationId' => $command->reservationId,
            ]);

            return;
        }

        $reservationDetails = $this->reservationDetailsFetcher->fetch($command->reservationId);

        if (null === $reservationDetails) {
            $this->logger->warning('Booking confirmation email skipped: reservation not found', [
                'reservationId' => $command->reservationId,
                'bookerId' => $command->bookerId,
            ]);

            return;
        }

        $email = (new Email())
            ->from(new Address($this->mailerFrom, 'book.it'))
            ->to(new Address($bookerContact->email, $bookerContact->firstName.' '.$bookerContact->lastName))
            ->subject('Votre réservation est confirmée')
            ->text(sprintf(
                "Bonjour %s,\n\nVotre séjour du %s au %s est bien enregistré.\nMontant total : %.2f €\n\nÀ bientôt,\nL'équipe book.it",
                $bookerContact->firstName,
                $reservationDetails->checkIn->format('d/m/Y'),
                $reservationDetails->checkOut->format('d/m/Y'),
                $reservationDetails->totalPriceCents / 100,
            ));

        $this->mailer->send($email);

        $this->logger->info('Booking confirmation email sent', [
            'reservationId' => $command->reservationId,
            'bookerId' => $command->bookerId,
        ]);
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
make unit-test ARGS="--filter=SendBookingConfirmationEmailCommandHandlerTest"
```

Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Notification/Application/UseCase/SendBookingConfirmationEmail/SendBookingConfirmationEmailCommandHandler.php \
        tests/Notification/Application/UseCase/
git commit -m "feat(notification): add SendBookingConfirmationEmailCommandHandler"
```

---

## Task 5: `ReservationConfirmedListener` (TDD)

**Files:**
- Create: `tests/Notification/Infrastructure/EventListener/ReservationConfirmedListenerTest.php`
- Create: `src/Notification/Infrastructure/EventListener/ReservationConfirmedListener.php`

- [ ] **Step 1: Write the failing test**

`tests/Notification/Infrastructure/EventListener/ReservationConfirmedListenerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure\EventListener;

use App\Notification\Application\UseCase\SendBookingConfirmationEmail\SendBookingConfirmationEmailCommand;
use App\Notification\Infrastructure\EventListener\ReservationConfirmedListener;
use App\Reservation\Domain\Event\ReservationConfirmed;
use App\Tests\Fake\FakeAsyncCommandDispatcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationConfirmedListenerTest extends TestCase
{
    public function test_dispatches_send_booking_confirmation_email_command(): void
    {
        $dispatcher = new FakeAsyncCommandDispatcher();
        $listener = new ReservationConfirmedListener($dispatcher);

        $listener(new ReservationConfirmed(
            reservationId: 'res-001',
            roomId: 'room-001',
            bookerId: 'booker-001',
            checkIn: new \DateTimeImmutable('2026-07-01'),
            checkOut: new \DateTimeImmutable('2026-07-05'),
        ));

        $command = $dispatcher->getLastDispatched();
        self::assertInstanceOf(SendBookingConfirmationEmailCommand::class, $command);
        self::assertSame('res-001', $command->reservationId);
        self::assertSame('booker-001', $command->bookerId);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
make unit-test ARGS="--filter=ReservationConfirmedListenerTest"
```

Expected: FAIL — class `ReservationConfirmedListener` does not exist.

- [ ] **Step 3: Implement the listener**

`src/Notification/Infrastructure/EventListener/ReservationConfirmedListener.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\EventListener;

use App\Notification\Application\UseCase\SendBookingConfirmationEmail\SendBookingConfirmationEmailCommand;
use App\Reservation\Domain\Event\ReservationConfirmed;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationConfirmed::class)]
final readonly class ReservationConfirmedListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(ReservationConfirmed $event): void
    {
        $this->commandDispatcher->dispatch(new SendBookingConfirmationEmailCommand(
            reservationId: $event->reservationId,
            bookerId: $event->bookerId,
        ));
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
make unit-test ARGS="--filter=ReservationConfirmedListenerTest"
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Notification/Infrastructure/EventListener/ReservationConfirmedListener.php \
        tests/Notification/Infrastructure/EventListener/ReservationConfirmedListenerTest.php
git commit -m "feat(notification): add ReservationConfirmedListener"
```

---

## Task 6: Infrastructure adapters — `BookerContactFetcher` and `ReservationDetailsFetcher` (TDD)

**Files:**
- Create: `tests/Notification/Infrastructure/Service/BookerContactFetcherTest.php`
- Create: `src/Notification/Infrastructure/Service/BookerContactFetcher.php`
- Create: `tests/Notification/Infrastructure/Service/ReservationDetailsFetcherTest.php`
- Create: `src/Notification/Infrastructure/Service/ReservationDetailsFetcher.php`

- [ ] **Step 1: Write `BookerContactFetcherTest`**

`tests/Notification/Infrastructure/Service/BookerContactFetcherTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure\Service;

use App\Booker\Application\UseCase\GetBooker\GetBookerQuery;
use App\Booker\Domain\Model\Booker;
use App\Notification\Infrastructure\Service\BookerContactFetcher;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BookerContactFetcherTest extends TestCase
{
    public function test_returns_contact_when_booker_found(): void
    {
        $booker = new Booker(
            id: 'booker-001',
            firstName: 'Jean',
            lastName: 'Dupont',
            email: 'jean.dupont@example.com',
            phone: '+33600000000',
            dateOfBirth: new \DateTimeImmutable('1980-01-01'),
            registeredAt: new \DateTimeImmutable(),
        );

        $queryBus = new class($booker) implements SyncQueryBusInterface {
            public function __construct(private readonly Booker $booker)
            {
            }

            public function ask(object $query): mixed
            {
                return $this->booker;
            }
        };

        $fetcher = new BookerContactFetcher($queryBus);
        $contact = $fetcher->fetch('booker-001');

        self::assertNotNull($contact);
        self::assertSame('Jean', $contact->firstName);
        self::assertSame('Dupont', $contact->lastName);
        self::assertSame('jean.dupont@example.com', $contact->email);
    }

    public function test_returns_null_when_booker_not_found(): void
    {
        $queryBus = new class implements SyncQueryBusInterface {
            public function ask(object $query): mixed
            {
                return null;
            }
        };

        $fetcher = new BookerContactFetcher($queryBus);

        self::assertNull($fetcher->fetch('unknown'));
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
make unit-test ARGS="--filter=BookerContactFetcherTest"
```

Expected: FAIL — class `BookerContactFetcher` does not exist.

- [ ] **Step 3: Implement `BookerContactFetcher`**

`src/Notification/Infrastructure/Service/BookerContactFetcher.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Service;

use App\Booker\Application\UseCase\GetBooker\GetBookerQuery;
use App\Booker\Domain\Model\Booker;
use App\Notification\Domain\Port\BookerContactFetcherInterface;
use App\Notification\Domain\ReadModel\BookerContact;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class BookerContactFetcher implements BookerContactFetcherInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function fetch(string $bookerId): ?BookerContact
    {
        /** @var Booker|null $booker */
        $booker = $this->queryBus->ask(new GetBookerQuery($bookerId));

        if (null === $booker) {
            return null;
        }

        return new BookerContact(
            firstName: $booker->firstName,
            lastName: $booker->lastName,
            email: $booker->email,
        );
    }
}
```

- [ ] **Step 4: Run test to confirm it passes**

```bash
make unit-test ARGS="--filter=BookerContactFetcherTest"
```

Expected: PASS.

- [ ] **Step 5: Write `ReservationDetailsFetcherTest`**

`tests/Notification/Infrastructure/Service/ReservationDetailsFetcherTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure\Service;

use App\Notification\Infrastructure\Service\ReservationDetailsFetcher;
use App\Reservation\Application\UseCase\GetReservation\GetReservationQuery;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationDetailsFetcherTest extends TestCase
{
    public function test_returns_details_when_reservation_found(): void
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

        $queryBus = new class($reservation) implements SyncQueryBusInterface {
            public function __construct(private readonly Reservation $reservation)
            {
            }

            public function ask(object $query): mixed
            {
                return $this->reservation;
            }
        };

        $fetcher = new ReservationDetailsFetcher($queryBus);
        $details = $fetcher->fetch('res-001');

        self::assertNotNull($details);
        self::assertSame('2026-07-01', $details->checkIn->format('Y-m-d'));
        self::assertSame('2026-07-05', $details->checkOut->format('Y-m-d'));
        self::assertSame(40000, $details->totalPriceCents);
    }

    public function test_returns_null_when_reservation_not_found(): void
    {
        $queryBus = new class implements SyncQueryBusInterface {
            public function ask(object $query): mixed
            {
                return null;
            }
        };

        $fetcher = new ReservationDetailsFetcher($queryBus);

        self::assertNull($fetcher->fetch('unknown'));
    }
}
```

- [ ] **Step 6: Run test to confirm it fails**

```bash
make unit-test ARGS="--filter=ReservationDetailsFetcherTest"
```

Expected: FAIL — class `ReservationDetailsFetcher` does not exist.

- [ ] **Step 7: Implement `ReservationDetailsFetcher`**

`src/Notification/Infrastructure/Service/ReservationDetailsFetcher.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Service;

use App\Notification\Domain\Port\ReservationDetailsFetcherInterface;
use App\Notification\Domain\ReadModel\ReservationDetails;
use App\Reservation\Application\UseCase\GetReservation\GetReservationQuery;
use App\Reservation\Domain\Model\Reservation;
use App\Shared\Application\Bus\SyncQueryBusInterface;

final readonly class ReservationDetailsFetcher implements ReservationDetailsFetcherInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function fetch(string $reservationId): ?ReservationDetails
    {
        /** @var Reservation|null $reservation */
        $reservation = $this->queryBus->ask(new GetReservationQuery($reservationId));

        if (null === $reservation) {
            return null;
        }

        return new ReservationDetails(
            checkIn: $reservation->period->checkIn,
            checkOut: $reservation->period->checkOut,
            totalPriceCents: $reservation->totalPrice,
        );
    }
}
```

- [ ] **Step 8: Run all new tests**

```bash
make unit-test ARGS="--filter=BookerContactFetcherTest|ReservationDetailsFetcherTest"
```

Expected: PASS (4 tests).

- [ ] **Step 9: Commit**

```bash
git add src/Notification/Infrastructure/Service/ \
        tests/Notification/Infrastructure/Service/
git commit -m "feat(notification): add BookerContactFetcher and ReservationDetailsFetcher adapters"
```

---

## Task 7: Service config and environment variable

**Files:**
- Create: `config/services/notification.yaml`
- Modify: `config/services.yaml`
- Modify: `.env`

- [ ] **Step 1: Create `config/services/notification.yaml`**

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
        App\Shared\Application\Bus\SyncQueryHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: sync.query.bus}

    App\Notification\Application\:
        resource: '../../src/Notification/Application/'
        exclude:
            - '../../src/Notification/Application/**/*Command.php'

    App\Notification\Infrastructure\:
        resource: '../../src/Notification/Infrastructure/'

    App\Notification\Application\UseCase\SendBookingConfirmationEmail\SendBookingConfirmationEmailCommandHandler:
        tags:
            - {name: messenger.message_handler, bus: messenger.bus.default}
        arguments:
            $mailerFrom: '%env(MAILER_FROM)%'
```

- [ ] **Step 2: Add `notification.yaml` import to `config/services.yaml`**

Add the import at the end of the imports list:

```yaml
imports:
    - { resource: './services/shared.yaml' }
    - { resource: './services/hotel.yaml' }
    - { resource: './services/room.yaml' }
    - { resource: './services/booker.yaml' }
    - { resource: './services/availability.yaml' }
    - { resource: './services/pricing.yaml' }
    - { resource: './services/reservation.yaml' }
    - { resource: './services/exceptions.yaml' }
    - { resource: './services/payment.yaml' }
    - { resource: './services/notification.yaml' }
```

- [ ] **Step 3: Add `MAILER_FROM` to `.env`**

After the `MAILER_DSN` block, add:

```
MAILER_FROM=noreply@book.it
```

- [ ] **Step 4: Commit**

```bash
git add config/services/notification.yaml config/services.yaml .env
git commit -m "feat(notification): add service config and MAILER_FROM env var"
```

---

## Task 8: Final validation

- [ ] **Step 1: Run full unit + integration test suite**

```bash
make unit-test
```

Expected: all tests pass, no regressions.

- [ ] **Step 2: Run linter and architecture checks**

```bash
make lint
```

Expected: no CS violations, no PHPStan errors, no deptrac violations.

- [ ] **Step 3: If lint fails on CS, auto-fix and re-check**

```bash
make apply-cs && make lint
```

- [ ] **Step 4: Commit lint fixes if any**

```bash
git add -p
git commit -m "style(notification): apply CS fixer"
```

# Cancellation Confirmation Email — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Send an email to the Booker when they cancel their Reservation, confirming the cancellation and showing the refund amount if applicable.

**Architecture:** Follows the existing `SendBookingConfirmationEmail` pattern in the `Notification` context — a Symfony EventListener catches `ReservationCancelled`, dispatches an async `SendCancellationConfirmationEmailCommand` (carrying `refundAmountCents` since it is computed at cancellation time and cannot be re-derived), and the async handler fetches `BookerContact` + `ReservationDetails` then sends an email via a new domain port backed by Symfony Mailer + Twig.

**Tech Stack:** PHP 8.4, Symfony 8.0, Symfony Mailer, Twig, PHPUnit

---

## File Map

| Action | Path | Purpose |
|--------|------|---------|
| Create | `src/Notification/Domain/Port/CancellationConfirmationEmailSenderInterface.php` | Domain port for sending the cancellation email |
| Create | `src/Notification/Application/UseCase/SendCancellationConfirmationEmail/SendCancellationConfirmationEmailCommand.php` | Async command: reservationId, bookerId, refundAmountCents |
| Create | `src/Notification/Application/UseCase/SendCancellationConfirmationEmail/SendCancellationConfirmationEmailCommandHandler.php` | Fetches contact + details, delegates to email sender |
| Create | `src/Notification/Infrastructure/EventListener/ReservationCancelledListener.php` | Listens to ReservationCancelled, dispatches async command |
| Create | `src/Notification/Infrastructure/Service/SymfonyMailerCancellationConfirmationEmailSender.php` | Sends email via Symfony Mailer + Twig |
| Create | `templates/emails/cancellation_confirmation.html.twig` | HTML email template |
| Create | `templates/emails/cancellation_confirmation.txt.twig` | Plaintext email template |
| Create | `tests/Notification/Application/UseCase/SendCancellationConfirmationEmail/SendCancellationConfirmationEmailCommandHandlerTest.php` | Unit tests: 4 cases |
| Create | `tests/Notification/Infrastructure/EventListener/ReservationCancelledListenerTest.php` | Unit test: listener dispatches correct command |
| Modify | `config/services/notification.yaml` | Add alias + `$mailerFrom` for new sender service |

---

### Task 1: Create feature branch

- [ ] **Step 1: Switch to a new branch**

```bash
git checkout -b feat/cancellation-email
```

Expected: `Switched to a new branch 'feat/cancellation-email'`

---

### Task 2: Domain port

**Files:**
- Create: `src/Notification/Domain/Port/CancellationConfirmationEmailSenderInterface.php`

- [ ] **Step 1: Create the interface**

```php
<?php

declare(strict_types=1);

namespace App\Notification\Domain\Port;

use App\Notification\Domain\ReadModel\BookerContact;
use App\Notification\Domain\ReadModel\ReservationDetails;

interface CancellationConfirmationEmailSenderInterface
{
    public function send(BookerContact $bookerContact, ReservationDetails $reservationDetails, int $refundAmountCents): void;
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Notification/Domain/Port/CancellationConfirmationEmailSenderInterface.php
git commit -m "feat(notification): add CancellationConfirmationEmailSenderInterface port"
```

---

### Task 3: Async command

**Files:**
- Create: `src/Notification/Application/UseCase/SendCancellationConfirmationEmail/SendCancellationConfirmationEmailCommand.php`

- [ ] **Step 1: Create the command**

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\UseCase\SendCancellationConfirmationEmail;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class SendCancellationConfirmationEmailCommand implements AsyncCommandInterface
{
    public function __construct(
        public string $reservationId,
        public string $bookerId,
        public int $refundAmountCents,
    ) {
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Notification/Application/UseCase/SendCancellationConfirmationEmail/SendCancellationConfirmationEmailCommand.php
git commit -m "feat(notification): add SendCancellationConfirmationEmailCommand"
```

---

### Task 4: Command handler (TDD)

**Files:**
- Create: `tests/Notification/Application/UseCase/SendCancellationConfirmationEmail/SendCancellationConfirmationEmailCommandHandlerTest.php`
- Create: `src/Notification/Application/UseCase/SendCancellationConfirmationEmail/SendCancellationConfirmationEmailCommandHandler.php`

- [ ] **Step 1: Write the failing tests**

`tests/Notification/Application/UseCase/SendCancellationConfirmationEmail/SendCancellationConfirmationEmailCommandHandlerTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application\UseCase\SendCancellationConfirmationEmail;

use App\Notification\Application\UseCase\SendCancellationConfirmationEmail\SendCancellationConfirmationEmailCommand;
use App\Notification\Application\UseCase\SendCancellationConfirmationEmail\SendCancellationConfirmationEmailCommandHandler;
use App\Notification\Domain\Port\BookerContactFetcherInterface;
use App\Notification\Domain\Port\CancellationConfirmationEmailSenderInterface;
use App\Notification\Domain\Port\ReservationDetailsFetcherInterface;
use App\Notification\Domain\ReadModel\BookerContact;
use App\Notification\Domain\ReadModel\ReservationDetails;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Group('unit')]
final class SendCancellationConfirmationEmailCommandHandlerTest extends TestCase
{
    private BookerContactFetcherInterface&MockObject $bookerContactFetcher;
    private ReservationDetailsFetcherInterface&MockObject $reservationDetailsFetcher;
    private CancellationConfirmationEmailSenderInterface&MockObject $emailSender;
    private LoggerInterface&MockObject $logger;
    private SendCancellationConfirmationEmailCommandHandler $handler;

    protected function setUp(): void
    {
        $this->bookerContactFetcher = $this->createMock(BookerContactFetcherInterface::class);
        $this->reservationDetailsFetcher = $this->createMock(ReservationDetailsFetcherInterface::class);
        $this->emailSender = $this->createMock(CancellationConfirmationEmailSenderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new SendCancellationConfirmationEmailCommandHandler(
            $this->bookerContactFetcher,
            $this->reservationDetailsFetcher,
            $this->emailSender,
            $this->logger,
        );
    }

    public function testSendsEmailWithRefund(): void
    {
        $command = new SendCancellationConfirmationEmailCommand(
            reservationId: 'res-1',
            bookerId: 'booker-1',
            refundAmountCents: 15000,
        );

        $contact = new BookerContact('Jean', 'Dupont', 'jean@example.com');
        $details = new ReservationDetails(
            new \DateTimeImmutable('2026-07-01'),
            new \DateTimeImmutable('2026-07-05'),
            15000,
        );

        $this->bookerContactFetcher->expects($this->once())->method('fetch')->with('booker-1')->willReturn($contact);
        $this->reservationDetailsFetcher->expects($this->once())->method('fetch')->with('res-1')->willReturn($details);
        $this->emailSender->expects($this->once())->method('send')->with($contact, $details, 15000);
        $this->logger->expects($this->once())->method('info');

        ($this->handler)($command);
    }

    public function testSendsEmailWithoutRefund(): void
    {
        $command = new SendCancellationConfirmationEmailCommand(
            reservationId: 'res-2',
            bookerId: 'booker-2',
            refundAmountCents: 0,
        );

        $contact = new BookerContact('Marie', 'Martin', 'marie@example.com');
        $details = new ReservationDetails(
            new \DateTimeImmutable('2026-08-01'),
            new \DateTimeImmutable('2026-08-03'),
            20000,
        );

        $this->bookerContactFetcher->method('fetch')->willReturn($contact);
        $this->reservationDetailsFetcher->method('fetch')->willReturn($details);
        $this->emailSender->expects($this->once())->method('send')->with($contact, $details, 0);
        $this->logger->expects($this->once())->method('info');

        ($this->handler)($command);
    }

    public function testSkipsWhenBookerNotFound(): void
    {
        $command = new SendCancellationConfirmationEmailCommand(
            reservationId: 'res-3',
            bookerId: 'unknown',
            refundAmountCents: 0,
        );

        $this->bookerContactFetcher->method('fetch')->willReturn(null);
        $this->reservationDetailsFetcher->expects($this->never())->method('fetch');
        $this->emailSender->expects($this->never())->method('send');
        $this->logger->expects($this->once())->method('warning');

        ($this->handler)($command);
    }

    public function testSkipsWhenReservationNotFound(): void
    {
        $command = new SendCancellationConfirmationEmailCommand(
            reservationId: 'unknown',
            bookerId: 'booker-1',
            refundAmountCents: 0,
        );

        $contact = new BookerContact('Jean', 'Dupont', 'jean@example.com');
        $this->bookerContactFetcher->method('fetch')->willReturn($contact);
        $this->reservationDetailsFetcher->method('fetch')->willReturn(null);
        $this->emailSender->expects($this->never())->method('send');
        $this->logger->expects($this->once())->method('warning');

        ($this->handler)($command);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
make unit-test
```

Expected: 4 failures — `SendCancellationConfirmationEmailCommandHandler` not found

- [ ] **Step 3: Write the handler**

`src/Notification/Application/UseCase/SendCancellationConfirmationEmail/SendCancellationConfirmationEmailCommandHandler.php`

```php
<?php

declare(strict_types=1);

namespace App\Notification\Application\UseCase\SendCancellationConfirmationEmail;

use App\Notification\Domain\Port\BookerContactFetcherInterface;
use App\Notification\Domain\Port\CancellationConfirmationEmailSenderInterface;
use App\Notification\Domain\Port\ReservationDetailsFetcherInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class SendCancellationConfirmationEmailCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(
        private BookerContactFetcherInterface $bookerContactFetcher,
        private ReservationDetailsFetcherInterface $reservationDetailsFetcher,
        private CancellationConfirmationEmailSenderInterface $emailSender,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendCancellationConfirmationEmailCommand $command): void
    {
        $bookerContact = $this->bookerContactFetcher->fetch($command->bookerId);

        if (null === $bookerContact) {
            $this->logger->warning('Cancellation confirmation email skipped: booker not found', [
                'bookerId' => $command->bookerId,
                'reservationId' => $command->reservationId,
            ]);

            return;
        }

        $reservationDetails = $this->reservationDetailsFetcher->fetch($command->reservationId);

        if (null === $reservationDetails) {
            $this->logger->warning('Cancellation confirmation email skipped: reservation not found', [
                'reservationId' => $command->reservationId,
                'bookerId' => $command->bookerId,
            ]);

            return;
        }

        $this->emailSender->send($bookerContact, $reservationDetails, $command->refundAmountCents);

        $this->logger->info('Cancellation confirmation email sent', [
            'reservationId' => $command->reservationId,
            'bookerId' => $command->bookerId,
        ]);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
make unit-test
```

Expected: 4 tests pass, 0 failures

- [ ] **Step 5: Commit**

```bash
git add src/Notification/Application/UseCase/SendCancellationConfirmationEmail/SendCancellationConfirmationEmailCommandHandler.php
git add tests/Notification/Application/UseCase/SendCancellationConfirmationEmail/SendCancellationConfirmationEmailCommandHandlerTest.php
git commit -m "feat(notification): add SendCancellationConfirmationEmailCommandHandler"
```

---

### Task 5: Event listener (TDD)

**Files:**
- Create: `tests/Notification/Infrastructure/EventListener/ReservationCancelledListenerTest.php`
- Create: `src/Notification/Infrastructure/EventListener/ReservationCancelledListener.php`

- [ ] **Step 1: Write the failing test**

`tests/Notification/Infrastructure/EventListener/ReservationCancelledListenerTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure\EventListener;

use App\Notification\Application\UseCase\SendCancellationConfirmationEmail\SendCancellationConfirmationEmailCommand;
use App\Notification\Infrastructure\EventListener\ReservationCancelledListener;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\ReservationCancelled;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationCancelledListenerTest extends TestCase
{
    private AsyncCommandDispatcherInterface&MockObject $commandDispatcher;
    private ReservationCancelledListener $listener;

    protected function setUp(): void
    {
        $this->commandDispatcher = $this->createMock(AsyncCommandDispatcherInterface::class);
        $this->listener = new ReservationCancelledListener($this->commandDispatcher);
    }

    public function testDispatchesSendCancellationConfirmationEmailCommand(): void
    {
        $event = new ReservationCancelled(
            reservationId: 'res-1',
            roomId: 'room-1',
            bookerId: 'booker-1',
            refundAmountCents: 12000,
            checkIn: new \DateTimeImmutable('2026-07-01'),
            checkOut: new \DateTimeImmutable('2026-07-05'),
        );

        $this->commandDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendCancellationConfirmationEmailCommand $cmd) {
                return 'res-1' === $cmd->reservationId
                    && 'booker-1' === $cmd->bookerId
                    && 12000 === $cmd->refundAmountCents;
            }));

        ($this->listener)($event);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
make unit-test
```

Expected: 1 failure — `ReservationCancelledListener` not found

- [ ] **Step 3: Write the listener**

`src/Notification/Infrastructure/EventListener/ReservationCancelledListener.php`

```php
<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\EventListener;

use App\Notification\Application\UseCase\SendCancellationConfirmationEmail\SendCancellationConfirmationEmailCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\ReservationCancelled;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ReservationCancelled::class)]
final readonly class ReservationCancelledListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(ReservationCancelled $event): void
    {
        $this->commandDispatcher->dispatch(new SendCancellationConfirmationEmailCommand(
            reservationId: $event->reservationId,
            bookerId: $event->bookerId,
            refundAmountCents: $event->refundAmountCents,
        ));
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
make unit-test
```

Expected: all tests pass

- [ ] **Step 5: Commit**

```bash
git add src/Notification/Infrastructure/EventListener/ReservationCancelledListener.php
git add tests/Notification/Infrastructure/EventListener/ReservationCancelledListenerTest.php
git commit -m "feat(notification): add ReservationCancelledListener"
```

---

### Task 6: Twig templates

**Files:**
- Create: `templates/emails/cancellation_confirmation.html.twig`
- Create: `templates/emails/cancellation_confirmation.txt.twig`

- [ ] **Step 1: Create the HTML template**

`templates/emails/cancellation_confirmation.html.twig`

```twig
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Votre réservation a été annulée</title>
</head>
<body>
    <p>Bonjour {{ firstName }},</p>
    <p>
        Votre annulation pour le séjour du <strong>{{ checkIn|date('d/m/Y') }}</strong>
        au <strong>{{ checkOut|date('d/m/Y') }}</strong> a bien été prise en compte.
    </p>
    {% if refundAmountCents > 0 %}
    <p>Montant remboursé : <strong>{{ (refundAmountCents / 100)|number_format(2, '.', '') }} €</strong></p>
    {% else %}
    <p>Conformément aux conditions d'annulation, aucun remboursement ne sera effectué.</p>
    {% endif %}
    <p>
        À bientôt,<br>
        L'équipe book.it
    </p>
</body>
</html>
```

- [ ] **Step 2: Create the TXT template**

`templates/emails/cancellation_confirmation.txt.twig`

```twig
Bonjour {{ firstName }},

Votre annulation pour le séjour du {{ checkIn|date('d/m/Y') }} au {{ checkOut|date('d/m/Y') }} a bien été prise en compte.

{% if refundAmountCents > 0 %}
Montant remboursé : {{ (refundAmountCents / 100)|number_format(2, '.', '') }} €
{% else %}
Conformément aux conditions d'annulation, aucun remboursement ne sera effectué.
{% endif %}

À bientôt,
L'équipe book.it
```

- [ ] **Step 3: Commit**

```bash
git add templates/emails/cancellation_confirmation.html.twig templates/emails/cancellation_confirmation.txt.twig
git commit -m "feat(notification): add cancellation confirmation email templates"
```

---

### Task 7: Mailer infrastructure service

**Files:**
- Create: `src/Notification/Infrastructure/Service/SymfonyMailerCancellationConfirmationEmailSender.php`

- [ ] **Step 1: Create the service**

```php
<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Service;

use App\Notification\Domain\Port\CancellationConfirmationEmailSenderInterface;
use App\Notification\Domain\ReadModel\BookerContact;
use App\Notification\Domain\ReadModel\ReservationDetails;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class SymfonyMailerCancellationConfirmationEmailSender implements CancellationConfirmationEmailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private string $mailerFrom,
    ) {
    }

    public function send(BookerContact $bookerContact, ReservationDetails $reservationDetails, int $refundAmountCents): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, 'book.it'))
            ->to(new Address($bookerContact->email, $bookerContact->firstName . ' ' . $bookerContact->lastName))
            ->subject('Votre réservation a été annulée')
            ->textTemplate('emails/cancellation_confirmation.txt.twig')
            ->htmlTemplate('emails/cancellation_confirmation.html.twig')
            ->context([
                'firstName' => $bookerContact->firstName,
                'checkIn' => $reservationDetails->checkIn,
                'checkOut' => $reservationDetails->checkOut,
                'refundAmountCents' => $refundAmountCents,
            ]);

        $this->mailer->send($email);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Notification/Infrastructure/Service/SymfonyMailerCancellationConfirmationEmailSender.php
git commit -m "feat(notification): add SymfonyMailerCancellationConfirmationEmailSender"
```

---

### Task 8: DI wiring

**Files:**
- Modify: `config/services/notification.yaml`

- [ ] **Step 1: Add the alias and `$mailerFrom` binding**

Append to the end of `config/services/notification.yaml` (after the existing `SymfonyMailerBookingConfirmationEmailSender` block):

```yaml
    App\Notification\Domain\Port\CancellationConfirmationEmailSenderInterface:
        alias: App\Notification\Infrastructure\Service\SymfonyMailerCancellationConfirmationEmailSender

    App\Notification\Infrastructure\Service\SymfonyMailerCancellationConfirmationEmailSender:
        arguments:
            $mailerFrom: '%env(MAILER_FROM)%'
```

- [ ] **Step 2: Commit**

```bash
git add config/services/notification.yaml
git commit -m "feat(notification): wire CancellationConfirmationEmailSenderInterface in DI"
```

---

### Task 9: Validate

- [ ] **Step 1: Run the full test suite**

```bash
make test
```

Expected: all tests pass

- [ ] **Step 2: Run linting**

```bash
make lint
```

Expected: no violations (CS Fixer, PHPStan, Deptrac all green)

- [ ] **Step 3: Apply CS fixes if needed**

If `make lint` reports CS Fixer violations, run:

```bash
make apply-cs
git add src/ tests/ templates/
git commit -m "style: apply CS Fixer corrections"
```

Skip this step if `git status` is clean after `make lint`.

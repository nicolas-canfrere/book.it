# HTML Booking Confirmation Email — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an HTML part to the booking confirmation email alongside the existing plain-text part, using Twig templates for both.

**Architecture:** Replace `Symfony\Component\Mime\Email` with `Symfony\Bridge\Twig\Mime\TemplatedEmail` in `SymfonyMailerBookingConfirmationEmailSender`. Both text and HTML bodies move to Twig templates in `templates/emails/`. The unit test migrates from asserting rendered body strings (impossible pre-send with `TemplatedEmail`) to asserting template names and context map.

**Tech Stack:** PHP 8.4 / Symfony 8.0, symfony/twig-bridge (already installed, v8.0.12), Twig 3.

---

## File Map

**Modified:**
- `src/Notification/Infrastructure/Service/SymfonyMailerBookingConfirmationEmailSender.php` — switch to `TemplatedEmail`, set `htmlTemplate()` + `textTemplate()` + `context()`
- `tests/Notification/Infrastructure/Service/SymfonyMailerBookingConfirmationEmailSenderTest.php` — assert `TemplatedEmail` instance, template names, and context keys/values

**Created:**
- `templates/emails/booking_confirmation.html.twig` — HTML email body
- `templates/emails/booking_confirmation.txt.twig` — plain-text email body (replaces inline sprintf)

---

## Task 1: Create feature branch

- [ ] **Step 1: Create and switch to feature branch**

```bash
git checkout -b feat/html-booking-confirmation-email
```

---

## Task 2: Add HTML template + migrate sender to `TemplatedEmail` (TDD)

**Files:**
- Modify: `tests/Notification/Infrastructure/Service/SymfonyMailerBookingConfirmationEmailSenderTest.php`
- Create: `templates/emails/booking_confirmation.html.twig`
- Create: `templates/emails/booking_confirmation.txt.twig`
- Modify: `src/Notification/Infrastructure/Service/SymfonyMailerBookingConfirmationEmailSender.php`

- [ ] **Step 1: Update the test to expect `TemplatedEmail` with templates and context**

Replace the entire content of `tests/Notification/Infrastructure/Service/SymfonyMailerBookingConfirmationEmailSenderTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure\Service;

use App\Notification\Domain\ReadModel\BookerContact;
use App\Notification\Domain\ReadModel\ReservationDetails;
use App\Notification\Infrastructure\Service\SymfonyMailerBookingConfirmationEmailSender;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

#[Group('unit')]
final class SymfonyMailerBookingConfirmationEmailSenderTest extends TestCase
{
    /** @var MailerInterface&MockObject */
    private MailerInterface $mailer;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
    }

    public function test_sends_templated_email_with_correct_templates_and_context(): void
    {
        $sender = new SymfonyMailerBookingConfirmationEmailSender($this->mailer, 'noreply@book.it');

        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');
        $bookerContact = new BookerContact('Jean', 'Dupont', 'jean.dupont@example.com');
        $reservationDetails = new ReservationDetails($checkIn, $checkOut, 40000);

        /** @var TemplatedEmail|null $sentEmail */
        $sentEmail = null;
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willReturnCallback(static function (RawMessage $message) use (&$sentEmail): void {
                $sentEmail = $message;
            });

        $sender->send($bookerContact, $reservationDetails);

        self::assertNotNull($sentEmail);
        self::assertInstanceOf(TemplatedEmail::class, $sentEmail);
        self::assertSame('Votre réservation est confirmée', $sentEmail->getSubject());
        self::assertStringContainsString('jean.dupont@example.com', $sentEmail->getTo()[0]->getAddress());
        self::assertStringContainsString('noreply@book.it', $sentEmail->getFrom()[0]->getAddress());
        self::assertSame('emails/booking_confirmation.html.twig', $sentEmail->getHtmlTemplate());
        self::assertSame('emails/booking_confirmation.txt.twig', $sentEmail->getTextTemplate());
        $context = $sentEmail->getContext();
        self::assertSame('Jean', $context['firstName']);
        self::assertSame($checkIn, $context['checkIn']);
        self::assertSame($checkOut, $context['checkOut']);
        self::assertSame(40000, $context['totalPriceCents']);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
make unit-test ARGS="--filter=SymfonyMailerBookingConfirmationEmailSenderTest"
```

Expected: FAIL — `TemplatedEmail` assertion fails because sender still uses `Email`.

- [ ] **Step 3: Create the HTML email template**

Create `templates/emails/booking_confirmation.html.twig`:

```twig
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Votre réservation est confirmée</title>
</head>
<body>
    <p>Bonjour {{ firstName }},</p>
    <p>
        Votre séjour du <strong>{{ checkIn|date('d/m/Y') }}</strong>
        au <strong>{{ checkOut|date('d/m/Y') }}</strong> est bien enregistré.
    </p>
    <p>Montant total : <strong>{{ (totalPriceCents / 100)|number_format(2, '.', '') }} €</strong></p>
    <p>
        À bientôt,<br>
        L'équipe book.it
    </p>
</body>
</html>
```

- [ ] **Step 4: Create the plain-text email template**

Create `templates/emails/booking_confirmation.txt.twig`:

```twig
Bonjour {{ firstName }},

Votre séjour du {{ checkIn|date('d/m/Y') }} au {{ checkOut|date('d/m/Y') }} est bien enregistré.
Montant total : {{ (totalPriceCents / 100)|number_format(2, '.', '') }} €

À bientôt,
L'équipe book.it
```

- [ ] **Step 5: Update the sender to use `TemplatedEmail`**

Replace the entire content of `src/Notification/Infrastructure/Service/SymfonyMailerBookingConfirmationEmailSender.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Service;

use App\Notification\Domain\Port\BookingConfirmationEmailSenderInterface;
use App\Notification\Domain\ReadModel\BookerContact;
use App\Notification\Domain\ReadModel\ReservationDetails;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class SymfonyMailerBookingConfirmationEmailSender implements BookingConfirmationEmailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private string $mailerFrom,
    ) {
    }

    public function send(BookerContact $bookerContact, ReservationDetails $reservationDetails): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, 'book.it'))
            ->to(new Address($bookerContact->email, $bookerContact->firstName.' '.$bookerContact->lastName))
            ->subject('Votre réservation est confirmée')
            ->textTemplate('emails/booking_confirmation.txt.twig')
            ->htmlTemplate('emails/booking_confirmation.html.twig')
            ->context([
                'firstName' => $bookerContact->firstName,
                'checkIn' => $reservationDetails->checkIn,
                'checkOut' => $reservationDetails->checkOut,
                'totalPriceCents' => $reservationDetails->totalPriceCents,
            ]);

        $this->mailer->send($email);
    }
}
```

- [ ] **Step 6: Run test to confirm it passes**

```bash
make unit-test ARGS="--filter=SymfonyMailerBookingConfirmationEmailSenderTest"
```

Expected: PASS (1 test, 9 assertions).

- [ ] **Step 7: Run full suite + lint**

```bash
make unit-test && make lint
```

Expected: all tests pass, 0 CS/PHPStan/deptrac violations. If CS fixer modifies files, run `make apply-cs` then re-check.

- [ ] **Step 8: Commit**

```bash
git add src/Notification/Infrastructure/Service/SymfonyMailerBookingConfirmationEmailSender.php \
        tests/Notification/Infrastructure/Service/SymfonyMailerBookingConfirmationEmailSenderTest.php \
        templates/emails/
git commit -m "feat(notification): add HTML email via Twig templates"
```

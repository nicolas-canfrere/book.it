# Guest Registration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the Guest concept — pre-registration by the Booker before check-in (optional), and definitive registration by the operator at check-in with a `confirmed → checked_in` status transition.

**Architecture:** Guest is a pure domain entity owned by the Reservation aggregate, persisted in a `reservation_guest` DBAL table. Both `preRegisterGuests` and `checkIn` replace the full guest list and go through the async command bus. The Reservation repository's `save()` and `get()` methods are extended to handle guest persistence alongside status updates.

**Tech Stack:** PHP 8.4 / Symfony 8.0 / Doctrine DBAL (no ORM) / RabbitMQ via Symfony Messenger / PHPUnit / OpenAPI via Nelmio

---

## File Map

### New files
| Path | Purpose |
|------|---------|
| `src/Reservation/Domain/Model/Guest.php` | Pure domain entity: id, firstName, lastName, dateOfBirth |
| `src/Reservation/Domain/Exception/GuestPreRegistrationNotAllowedException.php` | Thrown when pre-registration is rejected by status or date |
| `src/Reservation/Domain/Exception/CheckInNotAllowedException.php` | Thrown when check-in is rejected by status or date |
| `src/Reservation/Application/UseCase/PreRegisterGuests/PreRegisterGuestsCommand.php` | Command DTO carrying reservationId + guest list |
| `src/Reservation/Application/UseCase/PreRegisterGuests/PreRegisterGuestsCommandHandler.php` | Loads reservation, builds Guest[], calls preRegisterGuests, saves |
| `src/Reservation/Application/UseCase/CheckIn/CheckInCommand.php` | Command DTO carrying reservationId + guest list |
| `src/Reservation/Application/UseCase/CheckIn/CheckInCommandHandler.php` | Loads reservation, builds Guest[], calls checkIn, saves |
| `src/Reservation/UI/Http/Controller/PreRegisterGuests/PreRegisterGuestsController.php` | PUT /reservations/{id}/guests → 202 |
| `src/Reservation/UI/Http/Controller/PreRegisterGuests/PreRegisterGuestsRequest.php` | Request DTO with guests array and validation |
| `src/Reservation/UI/Http/Controller/CheckIn/CheckInController.php` | POST /reservations/{id}/check-in → 202 |
| `src/Reservation/UI/Http/Controller/CheckIn/CheckInRequest.php` | Request DTO with guests array and validation |
| `tests/Reservation/Unit/Domain/Model/ReservationGuestTest.php` | Unit tests for Reservation guest methods |
| `tests/Reservation/Integration/UseCase/PreRegisterGuests/PreRegisterGuestsCommandHandlerTest.php` | Integration test for pre-registration handler |
| `tests/Reservation/Integration/UseCase/CheckIn/CheckInCommandHandlerTest.php` | Integration test for check-in handler |
| `tests/Reservation/Functional/Controller/PreRegisterGuests/PreRegisterGuestsControllerTest.php` | Functional test for PUT /reservations/{id}/guests |
| `tests/Reservation/Functional/Controller/CheckIn/CheckInControllerTest.php` | Functional test for POST /reservations/{id}/check-in |

### Modified files
| Path | What changes |
|------|-------------|
| `src/Reservation/Domain/Model/ReservationStatus.php` | Add `CheckedIn = 'checked_in'` case |
| `src/Reservation/Domain/Model/Reservation.php` | Add `$guests` property, `preRegisterGuests()`, `checkIn()` methods |
| `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php` | Extend `save()` to persist guests, extend `get()` to load guests |
| `tests/Reservation/Infrastructure/Persistence/InMemory/InMemoryReservationRepository.php` | Ensure `save()` persists guest changes (object reference — verify it's correct) |
| `config/services/reservation.yaml` | Register `PreRegisterGuestsCommandHandler` and `CheckInCommandHandler` via `_instanceof` |
| `config/services/exceptions.yaml` | Map `GuestPreRegistrationNotAllowedException` and `CheckInNotAllowedException` to HTTP 409 |

---

## Task 0: Create branch

- [ ] **Step 1: Create and switch to feature branch**

```bash
git checkout -b feat/guest-registration
```

- [ ] **Step 2: Verify branch**

```bash
git branch --show-current
```
Expected output: `feat/guest-registration`

---

## Task 1: Guest domain entity

**Files:**
- Create: `src/Reservation/Domain/Model/Guest.php`

- [ ] **Step 1: Create the Guest entity**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

final class Guest
{
    public function __construct(
        public readonly string $id,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly \DateTimeImmutable $dateOfBirth,
    ) {
    }
}
```

- [ ] **Step 2: Run static analysis to verify the class is clean**

```bash
make phpstan
```
Expected: no errors on the new file.

- [ ] **Step 3: Commit**

```bash
git add src/Reservation/Domain/Model/Guest.php
git commit -m "feat(reservation): add Guest domain entity"
```

---

## Task 2: Domain exceptions

**Files:**
- Create: `src/Reservation/Domain/Exception/GuestPreRegistrationNotAllowedException.php`
- Create: `src/Reservation/Domain/Exception/CheckInNotAllowedException.php`

- [ ] **Step 1: Create GuestPreRegistrationNotAllowedException**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

use App\Reservation\Domain\Model\ReservationStatus;

final class GuestPreRegistrationNotAllowedException extends \DomainException
{
    public function __construct(ReservationStatus $status)
    {
        parent::__construct(sprintf(
            'Cannot pre-register guests on a reservation with status "%s".',
            $status->value,
        ));
    }
}
```

- [ ] **Step 2: Create CheckInNotAllowedException**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

use App\Reservation\Domain\Model\ReservationStatus;

final class CheckInNotAllowedException extends \DomainException
{
    public static function wrongStatus(ReservationStatus $status): self
    {
        return new self(sprintf(
            'Check-in is not allowed on a reservation with status "%s".',
            $status->value,
        ));
    }

    public static function tooEarly(\DateTimeImmutable $checkInDate, \DateTimeImmutable $today): self
    {
        return new self(sprintf(
            'Check-in is not allowed before the check-in date %s (today: %s).',
            $checkInDate->format('Y-m-d'),
            $today->format('Y-m-d'),
        ));
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Reservation/Domain/Exception/GuestPreRegistrationNotAllowedException.php \
        src/Reservation/Domain/Exception/CheckInNotAllowedException.php
git commit -m "feat(reservation): add domain exceptions for guest pre-registration and check-in"
```

---

## Task 3: ReservationStatus + Reservation guest methods

**Files:**
- Modify: `src/Reservation/Domain/Model/ReservationStatus.php`
- Modify: `src/Reservation/Domain/Model/Reservation.php`
- Create: `tests/Reservation/Unit/Domain/Model/ReservationGuestTest.php`

- [ ] **Step 1: Write the failing unit tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Unit\Domain\Model;

use App\Reservation\Domain\Exception\CheckInNotAllowedException;
use App\Reservation\Domain\Exception\GuestPreRegistrationNotAllowedException;
use App\Reservation\Domain\Model\Guest;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationGuestTest extends TestCase
{
    private function makeReservation(ReservationStatus $status = ReservationStatus::Confirmed): Reservation
    {
        $reservation = new Reservation(
            id: 'res-uuid-1',
            roomId: 'room-uuid-1',
            bookerId: 'booker-uuid-1',
            period: new DatePeriod(
                new \DateTimeImmutable('2026-07-01'),
                new \DateTimeImmutable('2026-07-03'),
            ),
            totalPrice: 10000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: PriceBreakdown::fromArray([
                ['date' => '2026-07-01', 'rateAmountCents' => 5000, 'discountPercent' => null, 'effectiveAmountCents' => 5000],
                ['date' => '2026-07-02', 'rateAmountCents' => 5000, 'discountPercent' => null, 'effectiveAmountCents' => 5000],
            ]),
            guestCount: new GuestCount(2),
            createdAt: new \DateTimeImmutable('2026-06-01'),
        );
        $reservation->status = $status;

        return $reservation;
    }

    private function makeGuest(string $id = 'g-uuid-1'): Guest
    {
        return new Guest($id, 'Alice', 'Smith', new \DateTimeImmutable('1990-01-15'));
    }

    // --- preRegisterGuests ---

    public function test_pre_register_guests_sets_guests_when_confirmed(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Confirmed);
        $guest = $this->makeGuest();

        $reservation->preRegisterGuests([$guest], new \DateTimeImmutable('2026-06-15'));

        self::assertSame([$guest], $reservation->guests);
    }

    public function test_pre_register_guests_sets_guests_when_pending(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Pending);
        $guest = $this->makeGuest();

        $reservation->preRegisterGuests([$guest], new \DateTimeImmutable('2026-06-15'));

        self::assertSame([$guest], $reservation->guests);
    }

    public function test_pre_register_guests_replaces_existing_guests(): void
    {
        $reservation = $this->makeReservation();
        $reservation->preRegisterGuests([$this->makeGuest('g-1')], new \DateTimeImmutable('2026-06-10'));

        $newGuest = $this->makeGuest('g-2');
        $reservation->preRegisterGuests([$newGuest], new \DateTimeImmutable('2026-06-15'));

        self::assertCount(1, $reservation->guests);
        self::assertSame($newGuest, $reservation->guests[0]);
    }

    public function test_pre_register_guests_allows_empty_list(): void
    {
        $reservation = $this->makeReservation();
        $reservation->preRegisterGuests([$this->makeGuest()], new \DateTimeImmutable('2026-06-10'));
        $reservation->preRegisterGuests([], new \DateTimeImmutable('2026-06-15'));

        self::assertSame([], $reservation->guests);
    }

    public function test_pre_register_guests_throws_when_checked_in(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::CheckedIn);

        $this->expectException(GuestPreRegistrationNotAllowedException::class);
        $reservation->preRegisterGuests([], new \DateTimeImmutable('2026-06-15'));
    }

    public function test_pre_register_guests_throws_when_cancelled(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Cancelled);

        $this->expectException(GuestPreRegistrationNotAllowedException::class);
        $reservation->preRegisterGuests([], new \DateTimeImmutable('2026-06-15'));
    }

    public function test_pre_register_guests_throws_when_expired(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Expired);

        $this->expectException(GuestPreRegistrationNotAllowedException::class);
        $reservation->preRegisterGuests([], new \DateTimeImmutable('2026-06-15'));
    }

    public function test_pre_register_guests_throws_on_check_in_date(): void
    {
        $reservation = $this->makeReservation();

        $this->expectException(GuestPreRegistrationNotAllowedException::class);
        $reservation->preRegisterGuests([], new \DateTimeImmutable('2026-07-01')); // same as check-in
    }

    public function test_pre_register_guests_throws_after_check_in_date(): void
    {
        $reservation = $this->makeReservation();

        $this->expectException(GuestPreRegistrationNotAllowedException::class);
        $reservation->preRegisterGuests([], new \DateTimeImmutable('2026-07-02')); // during stay
    }

    // --- checkIn ---

    public function test_check_in_transitions_status_to_checked_in(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Confirmed);
        $guest = $this->makeGuest();

        $reservation->checkIn([$guest], new \DateTimeImmutable('2026-07-01'));

        self::assertSame(ReservationStatus::CheckedIn, $reservation->status);
    }

    public function test_check_in_sets_guests(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Confirmed);
        $guest = $this->makeGuest();

        $reservation->checkIn([$guest], new \DateTimeImmutable('2026-07-01'));

        self::assertSame([$guest], $reservation->guests);
    }

    public function test_check_in_replaces_pre_registered_guests(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Confirmed);
        $reservation->preRegisterGuests([$this->makeGuest('g-1')], new \DateTimeImmutable('2026-06-15'));
        $finalGuest = $this->makeGuest('g-2');

        $reservation->checkIn([$finalGuest], new \DateTimeImmutable('2026-07-01'));

        self::assertCount(1, $reservation->guests);
        self::assertSame($finalGuest, $reservation->guests[0]);
    }

    public function test_check_in_allowed_after_check_in_date(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Confirmed);

        $reservation->checkIn([], new \DateTimeImmutable('2026-07-02')); // day after check-in

        self::assertSame(ReservationStatus::CheckedIn, $reservation->status);
    }

    public function test_check_in_throws_when_not_confirmed(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Pending);

        $this->expectException(CheckInNotAllowedException::class);
        $reservation->checkIn([], new \DateTimeImmutable('2026-07-01'));
    }

    public function test_check_in_throws_when_cancelled(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Cancelled);

        $this->expectException(CheckInNotAllowedException::class);
        $reservation->checkIn([], new \DateTimeImmutable('2026-07-01'));
    }

    public function test_check_in_throws_before_check_in_date(): void
    {
        $reservation = $this->makeReservation(ReservationStatus::Confirmed);

        $this->expectException(CheckInNotAllowedException::class);
        $reservation->checkIn([], new \DateTimeImmutable('2026-06-30')); // day before
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
make unit-test
```
Expected: failures because `ReservationStatus::CheckedIn` doesn't exist, `$reservation->guests` doesn't exist, and the methods don't exist.

- [ ] **Step 3: Add `CheckedIn` to ReservationStatus**

Replace the entire file `src/Reservation/Domain/Model/ReservationStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case CheckedIn = 'checked_in';
}
```

- [ ] **Step 4: Add `$guests`, `preRegisterGuests()`, and `checkIn()` to Reservation**

Replace the entire file `src/Reservation/Domain/Model/Reservation.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

use App\Reservation\Domain\Exception\CheckInNotAllowedException;
use App\Reservation\Domain\Exception\GuestPreRegistrationNotAllowedException;
use App\Reservation\Domain\Exception\InvalidReservationTransitionException;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;

final class Reservation
{
    public ReservationStatus $status;

    /** @var Guest[] */
    public array $guests = [];

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

    /**
     * @param Guest[] $guests
     */
    public function preRegisterGuests(array $guests, \DateTimeImmutable $today): void
    {
        if (!in_array($this->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
            throw new GuestPreRegistrationNotAllowedException($this->status);
        }

        if ($today >= $this->period->checkIn) {
            throw new GuestPreRegistrationNotAllowedException($this->status);
        }

        $this->guests = $guests;
    }

    /**
     * @param Guest[] $guests
     */
    public function checkIn(array $guests, \DateTimeImmutable $today): void
    {
        if (ReservationStatus::Confirmed !== $this->status) {
            throw CheckInNotAllowedException::wrongStatus($this->status);
        }

        if ($today < $this->period->checkIn) {
            throw CheckInNotAllowedException::tooEarly($this->period->checkIn, $today);
        }

        $this->guests = $guests;
        $this->status = ReservationStatus::CheckedIn;
    }
}
```

- [ ] **Step 5: Run unit tests — they must pass**

```bash
make unit-test
```
Expected: all tests in `ReservationGuestTest` pass.

- [ ] **Step 6: Run linter and static analysis**

```bash
make lint && make phpstan
```
Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add src/Reservation/Domain/Model/ReservationStatus.php \
        src/Reservation/Domain/Model/Reservation.php \
        tests/Reservation/Unit/Domain/Model/ReservationGuestTest.php
git commit -m "feat(reservation): add CheckedIn status and guest registration methods on Reservation"
```

---

## Task 4: PreRegisterGuests use case

**Files:**
- Create: `src/Reservation/Application/UseCase/PreRegisterGuests/PreRegisterGuestsCommand.php`
- Create: `src/Reservation/Application/UseCase/PreRegisterGuests/PreRegisterGuestsCommandHandler.php`
- Create: `tests/Reservation/Integration/UseCase/PreRegisterGuests/PreRegisterGuestsCommandHandlerTest.php`

- [ ] **Step 1: Write the failing integration test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Integration\UseCase\PreRegisterGuests;

use App\Reservation\Application\UseCase\PreRegisterGuests\PreRegisterGuestsCommand;
use App\Reservation\Application\UseCase\PreRegisterGuests\PreRegisterGuestsCommandHandler;
use App\Reservation\Domain\Exception\GuestPreRegistrationNotAllowedException;
use App\Reservation\Domain\Exception\ReservationNotFoundException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PreRegisterGuestsCommandHandlerTest extends KernelTestCase
{
    private PreRegisterGuestsCommandHandler $handler;
    private InMemoryReservationRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryReservationRepository();
        $this->handler = new PreRegisterGuestsCommandHandler($this->repository);
    }

    private function makeConfirmedReservation(string $id = 'res-1'): Reservation
    {
        $reservation = new Reservation(
            id: $id,
            roomId: 'room-1',
            bookerId: 'booker-1',
            period: new DatePeriod(
                new \DateTimeImmutable('2026-07-01'),
                new \DateTimeImmutable('2026-07-03'),
            ),
            totalPrice: 10000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: PriceBreakdown::fromArray([
                ['date' => '2026-07-01', 'rateAmountCents' => 5000, 'discountPercent' => null, 'effectiveAmountCents' => 5000],
                ['date' => '2026-07-02', 'rateAmountCents' => 5000, 'discountPercent' => null, 'effectiveAmountCents' => 5000],
            ]),
            guestCount: new GuestCount(2),
            createdAt: new \DateTimeImmutable('2026-06-01'),
        );
        $reservation->status = ReservationStatus::Confirmed;

        return $reservation;
    }

    public function test_pre_registers_guests_on_confirmed_reservation(): void
    {
        $reservation = $this->makeConfirmedReservation();
        $this->repository->add($reservation);

        ($this->handler)(new PreRegisterGuestsCommand(
            reservationId: 'res-1',
            guests: [
                ['firstName' => 'Alice', 'lastName' => 'Smith', 'dateOfBirth' => '1990-01-15'],
                ['firstName' => 'Bob', 'lastName' => 'Jones', 'dateOfBirth' => '1992-03-20'],
            ],
            today: new \DateTimeImmutable('2026-06-15'),
        ));

        $saved = $this->repository->get('res-1');
        self::assertNotNull($saved);
        self::assertCount(2, $saved->guests);
        self::assertSame('Alice', $saved->guests[0]->firstName);
        self::assertSame('Smith', $saved->guests[0]->lastName);
        self::assertSame('1990-01-15', $saved->guests[0]->dateOfBirth->format('Y-m-d'));
        self::assertSame('Bob', $saved->guests[1]->firstName);
    }

    public function test_throws_when_reservation_not_found(): void
    {
        $this->expectException(ReservationNotFoundException::class);
        ($this->handler)(new PreRegisterGuestsCommand(
            reservationId: 'does-not-exist',
            guests: [],
            today: new \DateTimeImmutable('2026-06-15'),
        ));
    }

    public function test_throws_when_pre_registration_not_allowed(): void
    {
        $reservation = $this->makeConfirmedReservation();
        $reservation->status = ReservationStatus::CheckedIn;
        $this->repository->add($reservation);

        $this->expectException(GuestPreRegistrationNotAllowedException::class);
        ($this->handler)(new PreRegisterGuestsCommand(
            reservationId: 'res-1',
            guests: [],
            today: new \DateTimeImmutable('2026-06-15'),
        ));
    }
}

final class InMemoryReservationRepository implements ReservationRepositoryInterface
{
    /** @var Reservation[] */
    private array $reservations = [];

    public function add(Reservation $reservation): void
    {
        $this->reservations[$reservation->id] = $reservation;
    }

    public function save(Reservation $reservation): void
    {
        $this->reservations[$reservation->id] = $reservation;
    }

    public function get(string $id): ?Reservation
    {
        return $this->reservations[$id] ?? null;
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
make integration-test -- --filter PreRegisterGuestsCommandHandlerTest
```
Expected: class not found errors for `PreRegisterGuestsCommand` and `PreRegisterGuestsCommandHandler`.

- [ ] **Step 3: Create PreRegisterGuestsCommand**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\PreRegisterGuests;

final readonly class PreRegisterGuestsCommand
{
    /**
     * @param list<array{firstName: string, lastName: string, dateOfBirth: string}> $guests
     */
    public function __construct(
        public readonly string $reservationId,
        public readonly array $guests,
        public readonly \DateTimeImmutable $today,
    ) {
    }
}
```

- [ ] **Step 4: Create PreRegisterGuestsCommandHandler**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\PreRegisterGuests;

use App\Reservation\Domain\Exception\ReservationNotFoundException;
use App\Reservation\Domain\Model\Guest;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;
use Symfony\Component\Uid\Uuid;

final class PreRegisterGuestsCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservations,
    ) {
    }

    public function __invoke(PreRegisterGuestsCommand $command): void
    {
        $reservation = $this->reservations->get($command->reservationId);

        if (null === $reservation) {
            throw new ReservationNotFoundException($command->reservationId);
        }

        $guests = array_map(
            fn(array $data) => new Guest(
                id: Uuid::v4()->toRfc4122(),
                firstName: $data['firstName'],
                lastName: $data['lastName'],
                dateOfBirth: new \DateTimeImmutable($data['dateOfBirth']),
            ),
            $command->guests,
        );

        $reservation->preRegisterGuests($guests, $command->today);

        $this->reservations->save($reservation);
    }
}
```

- [ ] **Step 5: Run the integration test — it must pass**

```bash
make integration-test -- --filter PreRegisterGuestsCommandHandlerTest
```
Expected: all 3 tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/Reservation/Application/UseCase/PreRegisterGuests/ \
        tests/Reservation/Integration/UseCase/PreRegisterGuests/
git commit -m "feat(reservation): add PreRegisterGuests use case"
```

---

## Task 5: CheckIn use case

**Files:**
- Create: `src/Reservation/Application/UseCase/CheckIn/CheckInCommand.php`
- Create: `src/Reservation/Application/UseCase/CheckIn/CheckInCommandHandler.php`
- Create: `tests/Reservation/Integration/UseCase/CheckIn/CheckInCommandHandlerTest.php`

- [ ] **Step 1: Write the failing integration test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Integration\UseCase\CheckIn;

use App\Reservation\Application\UseCase\CheckIn\CheckInCommand;
use App\Reservation\Application\UseCase\CheckIn\CheckInCommandHandler;
use App\Reservation\Domain\Exception\CheckInNotAllowedException;
use App\Reservation\Domain\Exception\ReservationNotFoundException;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class CheckInCommandHandlerTest extends KernelTestCase
{
    private CheckInCommandHandler $handler;
    private InMemoryReservationRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryReservationRepository();
        $this->handler = new CheckInCommandHandler($this->repository);
    }

    private function makeConfirmedReservation(string $id = 'res-1'): Reservation
    {
        $reservation = new Reservation(
            id: $id,
            roomId: 'room-1',
            bookerId: 'booker-1',
            period: new DatePeriod(
                new \DateTimeImmutable('2026-07-01'),
                new \DateTimeImmutable('2026-07-03'),
            ),
            totalPrice: 10000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: PriceBreakdown::fromArray([
                ['date' => '2026-07-01', 'rateAmountCents' => 5000, 'discountPercent' => null, 'effectiveAmountCents' => 5000],
                ['date' => '2026-07-02', 'rateAmountCents' => 5000, 'discountPercent' => null, 'effectiveAmountCents' => 5000],
            ]),
            guestCount: new GuestCount(2),
            createdAt: new \DateTimeImmutable('2026-06-01'),
        );
        $reservation->status = ReservationStatus::Confirmed;

        return $reservation;
    }

    public function test_checks_in_and_transitions_status(): void
    {
        $reservation = $this->makeConfirmedReservation();
        $this->repository->add($reservation);

        ($this->handler)(new CheckInCommand(
            reservationId: 'res-1',
            guests: [
                ['firstName' => 'Alice', 'lastName' => 'Smith', 'dateOfBirth' => '1990-01-15'],
            ],
            today: new \DateTimeImmutable('2026-07-01'),
        ));

        $saved = $this->repository->get('res-1');
        self::assertNotNull($saved);
        self::assertSame(ReservationStatus::CheckedIn, $saved->status);
        self::assertCount(1, $saved->guests);
        self::assertSame('Alice', $saved->guests[0]->firstName);
        self::assertSame('1990-01-15', $saved->guests[0]->dateOfBirth->format('Y-m-d'));
    }

    public function test_throws_when_reservation_not_found(): void
    {
        $this->expectException(ReservationNotFoundException::class);
        ($this->handler)(new CheckInCommand(
            reservationId: 'does-not-exist',
            guests: [],
            today: new \DateTimeImmutable('2026-07-01'),
        ));
    }

    public function test_throws_when_check_in_not_allowed(): void
    {
        $reservation = $this->makeConfirmedReservation();
        $this->repository->add($reservation);

        $this->expectException(CheckInNotAllowedException::class);
        ($this->handler)(new CheckInCommand(
            reservationId: 'res-1',
            guests: [],
            today: new \DateTimeImmutable('2026-06-30'), // day before check-in
        ));
    }
}

final class InMemoryReservationRepository implements ReservationRepositoryInterface
{
    /** @var Reservation[] */
    private array $reservations = [];

    public function add(Reservation $reservation): void
    {
        $this->reservations[$reservation->id] = $reservation;
    }

    public function save(Reservation $reservation): void
    {
        $this->reservations[$reservation->id] = $reservation;
    }

    public function get(string $id): ?Reservation
    {
        return $this->reservations[$id] ?? null;
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
make integration-test -- --filter CheckInCommandHandlerTest
```
Expected: class not found errors for `CheckInCommand` and `CheckInCommandHandler`.

- [ ] **Step 3: Create CheckInCommand**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CheckIn;

final readonly class CheckInCommand
{
    /**
     * @param list<array{firstName: string, lastName: string, dateOfBirth: string}> $guests
     */
    public function __construct(
        public readonly string $reservationId,
        public readonly array $guests,
        public readonly \DateTimeImmutable $today,
    ) {
    }
}
```

- [ ] **Step 4: Create CheckInCommandHandler**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\CheckIn;

use App\Reservation\Domain\Exception\ReservationNotFoundException;
use App\Reservation\Domain\Model\Guest;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;
use Symfony\Component\Uid\Uuid;

final class CheckInCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservations,
    ) {
    }

    public function __invoke(CheckInCommand $command): void
    {
        $reservation = $this->reservations->get($command->reservationId);

        if (null === $reservation) {
            throw new ReservationNotFoundException($command->reservationId);
        }

        $guests = array_map(
            fn(array $data) => new Guest(
                id: Uuid::v4()->toRfc4122(),
                firstName: $data['firstName'],
                lastName: $data['lastName'],
                dateOfBirth: new \DateTimeImmutable($data['dateOfBirth']),
            ),
            $command->guests,
        );

        $reservation->checkIn($guests, $command->today);

        $this->reservations->save($reservation);
    }
}
```

- [ ] **Step 5: Run the integration test — it must pass**

```bash
make integration-test -- --filter CheckInCommandHandlerTest
```
Expected: all 3 tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/Reservation/Application/UseCase/CheckIn/ \
        tests/Reservation/Integration/UseCase/CheckIn/
git commit -m "feat(reservation): add CheckIn use case"
```

---

## Task 6: Extend ReservationRepository to persist and load guests

**Files:**
- Modify: `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php`

> The `save()` method currently only updates `status`. We extend it to also replace the guest list in `reservation_guest`. The `get()` method is extended to load guests and attach them to the hydrated Reservation.

- [ ] **Step 1: Update `save()` and `get()` in ReservationRepository**

Replace the entire file:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Persistence\Doctrine;

use App\Reservation\Domain\Model\Guest;
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

        $this->bookit->delete('reservation_guest', ['reservation_id' => $reservation->id]);

        foreach ($reservation->guests as $guest) {
            $this->bookit->insert('reservation_guest', [
                'id' => $guest->id,
                'reservation_id' => $reservation->id,
                'first_name' => $guest->firstName,
                'last_name' => $guest->lastName,
                'date_of_birth' => $guest->dateOfBirth->format('Y-m-d'),
            ]);
        }
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

        $reservation = $this->hydrate($row);

        /** @var list<array{id: string, first_name: string, last_name: string, date_of_birth: string}> $guestRows */
        $guestRows = $this->bookit->fetchAllAssociative(
            'SELECT id, first_name, last_name, date_of_birth
               FROM reservation_guest
              WHERE reservation_id = :id
              ORDER BY id',
            ['id' => $id],
        );

        $reservation->guests = array_map(
            fn(array $g) => new Guest(
                id: $g['id'],
                firstName: $g['first_name'],
                lastName: $g['last_name'],
                dateOfBirth: new \DateTimeImmutable($g['date_of_birth']),
            ),
            $guestRows,
        );

        return $reservation;
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

- [ ] **Step 2: Run static analysis**

```bash
make phpstan
```
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php
git commit -m "feat(reservation): extend ReservationRepository to persist and load guests"
```

---

## Task 7: Database migration

**Files:**
- Create: `migrations/Version<timestamp>.php` (timestamp generated by make command)

- [ ] **Step 1: Generate the migration file with the correct timestamp**

```bash
make generate-migration
```
This creates an empty `migrations/Version<timestamp>.php`. Open that file and fill in the SQL.

- [ ] **Step 2: Fill in the migration SQL**

Replace the generated up/down stubs with:

```php
public function up(Schema $schema): void
{
    $this->addSql(<<<'SQL'
        CREATE TABLE reservation_guest (
            id VARCHAR(36) NOT NULL,
            reservation_id VARCHAR(36) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            date_of_birth DATE NOT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_reservation_guest_reservation
                FOREIGN KEY (reservation_id)
                REFERENCES reservation(id)
                ON DELETE CASCADE
        )
    SQL);
    $this->addSql('CREATE INDEX idx_reservation_guest_reservation_id ON reservation_guest (reservation_id)');
}

public function down(Schema $schema): void
{
    $this->addSql('DROP TABLE reservation_guest');
}
```

- [ ] **Step 3: Run the migration**

```bash
make migrate
```
Expected: migration runs without errors, `reservation_guest` table is created.

- [ ] **Step 4: Run full test suite to catch regressions**

```bash
make test
```
Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add migrations/
git commit -m "feat(reservation): add reservation_guest table migration"
```

---

## Task 8: PreRegisterGuests controller

**Files:**
- Create: `src/Reservation/UI/Http/Controller/PreRegisterGuests/PreRegisterGuestsRequest.php`
- Create: `src/Reservation/UI/Http/Controller/PreRegisterGuests/PreRegisterGuestsController.php`
- Create: `tests/Reservation/Functional/Controller/PreRegisterGuests/PreRegisterGuestsControllerTest.php`

- [ ] **Step 1: Write the failing functional test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Functional\Controller\PreRegisterGuests;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class PreRegisterGuestsControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->db = static::getContainer()->get('doctrine.dbal.bookit_connection');
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        parent::tearDown();
    }

    private function insertConfirmedReservation(string $id): void
    {
        $this->db->insert('reservation', [
            'id' => $id,
            'room_id' => 'room-uuid-1',
            'booker_id' => 'booker-uuid-1',
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'total_price' => 10000,
            'guest_count' => 2,
            'cancellation_terms_days_threshold' => null,
            'price_breakdown' => '[{"date":"2026-07-01","rateAmountCents":5000,"discountPercent":null,"effectiveAmountCents":5000},{"date":"2026-07-02","rateAmountCents":5000,"discountPercent":null,"effectiveAmountCents":5000}]',
            'status' => 'confirmed',
            'created_at' => '2026-06-01 10:00:00',
        ]);
    }

    public function test_pre_registers_guests_and_returns_202(KernelBrowser $client = null): void
    {
        $reservationId = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $this->insertConfirmedReservation($reservationId);

        $this->client->request(
            method: 'PUT',
            uri: "/reservations/{$reservationId}/guests",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'guests' => [
                    ['firstName' => 'Alice', 'lastName' => 'Smith', 'dateOfBirth' => '1990-01-15'],
                    ['firstName' => 'Bob', 'lastName' => 'Jones', 'dateOfBirth' => '1992-03-20'],
                ],
            ]),
        );

        self::assertResponseStatusCodeSame(202);
    }

    public function test_returns_422_when_guest_fields_are_missing(): void
    {
        $reservationId = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $this->insertConfirmedReservation($reservationId);

        $this->client->request(
            method: 'PUT',
            uri: "/reservations/{$reservationId}/guests",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'guests' => [
                    ['firstName' => '', 'lastName' => 'Smith', 'dateOfBirth' => '1990-01-15'],
                ],
            ]),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function test_returns_404_when_reservation_not_found(): void
    {
        $this->client->request(
            method: 'PUT',
            uri: '/reservations/f47ac10b-58cc-4372-a567-0e02b2c3d999/guests',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['guests' => []]),
        );

        self::assertResponseStatusCodeSame(404);
    }
}
```

- [ ] **Step 2: Create PreRegisterGuestsRequest**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\PreRegisterGuests;

use Symfony\Component\Validator\Constraints as Assert;

final class PreRegisterGuestsRequest
{
    /**
     * @param list<array<string, string>> $guests
     */
    public function __construct(
        #[Assert\All([
            new Assert\Collection([
                'firstName' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
                'lastName' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
                'dateOfBirth' => [new Assert\NotBlank(), new Assert\Date()],
            ]),
        ])]
        public readonly array $guests = [],
    ) {
    }
}
```

- [ ] **Step 3: Create PreRegisterGuestsController**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\PreRegisterGuests;

use App\Reservation\Application\UseCase\PreRegisterGuests\PreRegisterGuestsCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class PreRegisterGuestsController
{
    public function __construct(
        private AsyncCommandDispatcherInterface $bus,
    ) {
    }

    #[Route(
        path: '/reservations/{id}/guests',
        name: 'reservation_pre_register_guests',
        requirements: ['id' => Requirement::UUID_V4],
        methods: ['PUT'],
    )]
    #[OA\Put(
        path: '/reservations/{id}/guests',
        summary: 'Pre-register guests on a reservation',
        tags: ['Reservation'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PreRegisterGuestsRequest'))]
    #[OA\Response(response: 202, description: 'Accepted')]
    #[OA\Response(response: 404, description: 'Reservation not found')]
    #[OA\Response(response: 409, description: 'Pre-registration not allowed for current reservation status')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function __invoke(
        string $id,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        PreRegisterGuestsRequest $request,
    ): Response {
        $this->bus->dispatch(new PreRegisterGuestsCommand(
            reservationId: $id,
            guests: $request->guests,
            today: new \DateTimeImmutable('today'),
        ));

        return new Response(status: Response::HTTP_ACCEPTED);
    }
}
```

- [ ] **Step 4: Run the functional test**

```bash
make functional-test -- --filter PreRegisterGuestsControllerTest
```
Expected: tests pass (may require DI config from Task 10 first — if tests fail due to missing service wiring, complete Task 10 then re-run).

- [ ] **Step 5: Commit**

```bash
git add src/Reservation/UI/Http/Controller/PreRegisterGuests/ \
        tests/Reservation/Functional/Controller/PreRegisterGuests/
git commit -m "feat(reservation): add PreRegisterGuests controller"
```

---

## Task 9: CheckIn controller

**Files:**
- Create: `src/Reservation/UI/Http/Controller/CheckIn/CheckInRequest.php`
- Create: `src/Reservation/UI/Http/Controller/CheckIn/CheckInController.php`
- Create: `tests/Reservation/Functional/Controller/CheckIn/CheckInControllerTest.php`

- [ ] **Step 1: Write the failing functional test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Functional\Controller\CheckIn;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class CheckInControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->db = static::getContainer()->get('doctrine.dbal.bookit_connection');
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        parent::tearDown();
    }

    private function insertConfirmedReservation(string $id, string $checkIn = '2026-07-01'): void
    {
        $checkOut = (new \DateTimeImmutable($checkIn))->modify('+2 days')->format('Y-m-d');
        $this->db->insert('reservation', [
            'id' => $id,
            'room_id' => 'room-uuid-1',
            'booker_id' => 'booker-uuid-1',
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'total_price' => 10000,
            'guest_count' => 2,
            'cancellation_terms_days_threshold' => null,
            'price_breakdown' => '[{"date":"' . $checkIn . '","rateAmountCents":5000,"discountPercent":null,"effectiveAmountCents":5000}]',
            'status' => 'confirmed',
            'created_at' => '2026-06-01 10:00:00',
        ]);
    }

    public function test_check_in_returns_202(): void
    {
        $reservationId = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $yesterday = (new \DateTimeImmutable('today'))->modify('-1 day')->format('Y-m-d');
        $this->insertConfirmedReservation($reservationId, $yesterday);

        $this->client->request(
            method: 'POST',
            uri: "/reservations/{$reservationId}/check-in",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'guests' => [
                    ['firstName' => 'Alice', 'lastName' => 'Smith', 'dateOfBirth' => '1990-01-15'],
                ],
            ]),
        );

        self::assertResponseStatusCodeSame(202);
    }

    public function test_returns_422_when_guest_fields_are_missing(): void
    {
        $reservationId = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $yesterday = (new \DateTimeImmutable('today'))->modify('-1 day')->format('Y-m-d');
        $this->insertConfirmedReservation($reservationId, $yesterday);

        $this->client->request(
            method: 'POST',
            uri: "/reservations/{$reservationId}/check-in",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'guests' => [
                    ['firstName' => 'Alice', 'lastName' => '', 'dateOfBirth' => '1990-01-15'],
                ],
            ]),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function test_returns_404_when_reservation_not_found(): void
    {
        $this->client->request(
            method: 'POST',
            uri: '/reservations/f47ac10b-58cc-4372-a567-0e02b2c3d999/check-in',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['guests' => []]),
        );

        self::assertResponseStatusCodeSame(404);
    }
}
```

- [ ] **Step 2: Create CheckInRequest**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\CheckIn;

use Symfony\Component\Validator\Constraints as Assert;

final class CheckInRequest
{
    /**
     * @param list<array<string, string>> $guests
     */
    public function __construct(
        #[Assert\All([
            new Assert\Collection([
                'firstName' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
                'lastName' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
                'dateOfBirth' => [new Assert\NotBlank(), new Assert\Date()],
            ]),
        ])]
        public readonly array $guests = [],
    ) {
    }
}
```

- [ ] **Step 3: Create CheckInController**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\CheckIn;

use App\Reservation\Application\UseCase\CheckIn\CheckInCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class CheckInController
{
    public function __construct(
        private AsyncCommandDispatcherInterface $bus,
    ) {
    }

    #[Route(
        path: '/reservations/{id}/check-in',
        name: 'reservation_check_in',
        requirements: ['id' => Requirement::UUID_V4],
        methods: ['POST'],
    )]
    #[OA\Post(
        path: '/reservations/{id}/check-in',
        summary: 'Check in a reservation',
        tags: ['Reservation'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CheckInRequest'))]
    #[OA\Response(response: 202, description: 'Accepted')]
    #[OA\Response(response: 404, description: 'Reservation not found')]
    #[OA\Response(response: 409, description: 'Check-in not allowed (wrong status or too early)')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function __invoke(
        string $id,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        CheckInRequest $request,
    ): Response {
        $this->bus->dispatch(new CheckInCommand(
            reservationId: $id,
            guests: $request->guests,
            today: new \DateTimeImmutable('today'),
        ));

        return new Response(status: Response::HTTP_ACCEPTED);
    }
}
```

- [ ] **Step 4: Run the functional test**

```bash
make functional-test -- --filter CheckInControllerTest
```
Expected: tests pass (requires DI config from Task 10 — complete Task 10 first if this fails due to missing services).

- [ ] **Step 5: Commit**

```bash
git add src/Reservation/UI/Http/Controller/CheckIn/ \
        tests/Reservation/Functional/Controller/CheckIn/
git commit -m "feat(reservation): add CheckIn controller"
```

---

## Task 10: DI config and exception mapping

**Files:**
- Modify: `config/services/reservation.yaml`
- Modify: `config/services/exceptions.yaml`

- [ ] **Step 1: Register handlers in reservation.yaml**

Open `config/services/reservation.yaml` and verify the `_instanceof` block contains the `AsyncCommandHandlerInterface` mapping. It should look like this (add it if missing):

```yaml
_instanceof:
    App\Shared\Application\Bus\AsyncCommandHandlerInterface:
        tags:
            - {name: messenger.message_handler, bus: messenger.bus.default}
```

The handlers are auto-discovered via the existing `resource:` directive (if the Application directory is already scanned). Verify the `resource:` line covers `src/Reservation/Application/`. If a separate entry is needed, add:

```yaml
App\Reservation\Application\UseCase\PreRegisterGuests\PreRegisterGuestsCommandHandler: ~
App\Reservation\Application\UseCase\CheckIn\CheckInCommandHandler: ~
```

No manual `tags:` on individual services — the `_instanceof` block handles it.

- [ ] **Step 2: Add exception mappings to config/services/exceptions.yaml**

Add these two entries under the `$map` key of `App\Shared\Infrastructure\Http\ExceptionProblemRegistry`:

```yaml
App\Reservation\Domain\Exception\GuestPreRegistrationNotAllowedException:
    type: 'https://book.it/problems/guest-pre-registration-not-allowed'
    title: 'Guest Pre-Registration Not Allowed'
    status: 409

App\Reservation\Domain\Exception\CheckInNotAllowedException:
    type: 'https://book.it/problems/check-in-not-allowed'
    title: 'Check-In Not Allowed'
    status: 409
```

- [ ] **Step 3: Clear cache and verify container compiles**

```bash
make cc && make lint
```
Expected: no errors.

- [ ] **Step 4: Run full test suite**

```bash
make test
```
Expected: all tests pass, including the functional tests from Tasks 8 and 9.

- [ ] **Step 5: Commit**

```bash
git add config/services/reservation.yaml config/services/exceptions.yaml
git commit -m "feat(reservation): wire PreRegisterGuests and CheckIn handlers, map exceptions"
```

---

## Task 11: OpenAPI

- [ ] **Step 1: Generate the OpenAPI spec**

```bash
make openapi
```
Expected: spec regenerated without errors.

- [ ] **Step 2: Verify the new endpoints appear in the spec**

```bash
grep -A5 'pre-register\|check-in' public/api/openapi.yaml
```
Expected: both endpoints appear with correct methods and paths.

- [ ] **Step 3: Commit**

```bash
git add public/api/openapi.yaml   # or wherever openapi output is written
git commit -m "docs(reservation): regenerate OpenAPI spec for guest registration endpoints"
```

---

## Self-Review

### Spec coverage

| Requirement | Covered by |
|-------------|-----------|
| Guest identified by firstName + lastName + dateOfBirth | Task 1 — `Guest` entity |
| Guest is not a persistent account | Guests stored in `reservation_guest` keyed to reservation, no standalone repository |
| Pre-registration by Booker after creation, before check-in (optional) | Task 3 domain method + Task 4 use case + Task 8 controller |
| Pre-registration forbidden on or after check-in date | Task 3 — `preRegisterGuests()` guard |
| Pre-registration forbidden for CheckedIn/Cancelled/Expired | Task 3 — `preRegisterGuests()` status guard |
| Operator check-in registers final guests | Task 5 use case + Task 9 controller |
| Check-in transitions `confirmed → checked_in` | Task 3 — `ReservationStatus::CheckedIn` + `checkIn()` method |
| Check-in only on or after check-in date | Task 3 — `checkIn()` date guard |
| Guest list is replaceable (pre-register replaces previous list) | Task 3 — `$this->guests = $guests` assignment |
| Guest persistence in DB | Task 6 repository + Task 7 migration |
| HTTP 409 for domain constraint violations | Task 10 exception mapping |
| HTTP 404 for unknown reservation | Existing `ReservationNotFoundException` mapping (already in exceptions.yaml) |

### Type consistency check

- `Guest::$id`, `::$firstName`, `::$lastName`, `::$dateOfBirth` — consistent across Tasks 1, 4, 5, 6
- `PreRegisterGuestsCommand::$guests` typed as `list<array{firstName, lastName, dateOfBirth}>` — consistent with handler and controller
- `CheckInCommand::$guests` typed as `list<array{firstName, lastName, dateOfBirth}>` — consistent with handler and controller
- `Reservation::$guests` typed as `Guest[]` — set in Task 3, read in Task 6 (repository hydration)
- `ReservationStatus::CheckedIn` — defined in Task 3, referenced in Task 3 tests and Task 5 integration test
- `GuestPreRegistrationNotAllowedException(ReservationStatus $status)` — defined in Task 2, thrown in Task 3, caught in Task 4 test
- `CheckInNotAllowedException::wrongStatus()` and `::tooEarly()` — defined in Task 2, thrown in Task 3, caught in Task 5 test

### Placeholder scan

No TBDs, TODOs, or hand-wavy steps found. All code is complete and runnable.

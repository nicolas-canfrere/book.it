# Reservation Integration Events — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Déplacer les 4 domain events Reservation vers `Shared\Domain\Event\` pour en faire des integration events publics inter-contextes, en aplatissant les value objects internes dans `ReservationCreated`.

**Architecture:** Les events `ReservationCreated`, `ReservationConfirmed`, `ReservationExpired` et `ReservationPaymentCancelled` deviennent des DTOs plats dans `App\Shared\Domain\Event\`. `ReservationCreated` remplace `CancellationTerms` par `?int $cancellationTermsDaysThreshold` et `PriceBreakdown` par `array $priceBreakdown` (via `->toArray()`). Les 4 anciennes classes dans `Reservation\Domain\Event\` sont supprimées après migration de tous les consommateurs.

**Tech Stack:** PHP 8.4, Symfony 8.0, PHPUnit, deptrac

---

### Task 1 : Créer la branche et les 4 nouveaux events dans Shared

**Files:**
- Create: `src/Shared/Domain/Event/ReservationCreated.php`
- Create: `src/Shared/Domain/Event/ReservationConfirmed.php`
- Create: `src/Shared/Domain/Event/ReservationExpired.php`
- Create: `src/Shared/Domain/Event/ReservationPaymentCancelled.php`

- [ ] **Step 1 : Créer la branche**

```bash
git checkout -b refactor/shared-integration-events
```

- [ ] **Step 2 : Créer `src/Shared/Domain/Event/ReservationCreated.php`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class ReservationCreated
{
    public function __construct(
        public string $reservationId,
        public string $roomId,
        public string $bookerId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $totalPrice,
        public ?int $cancellationTermsDaysThreshold,
        public array $priceBreakdown,
    ) {
    }
}
```

- [ ] **Step 3 : Créer `src/Shared/Domain/Event/ReservationConfirmed.php`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

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

- [ ] **Step 4 : Créer `src/Shared/Domain/Event/ReservationExpired.php`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class ReservationExpired
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

- [ ] **Step 5 : Créer `src/Shared/Domain/Event/ReservationPaymentCancelled.php`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class ReservationPaymentCancelled
{
    public function __construct(
        public string $reservationId,
    ) {
    }
}
```

- [ ] **Step 6 : Commiter**

```bash
git add src/Shared/Domain/Event/
git commit -m "feat(shared): add ReservationCreated/Confirmed/Expired/PaymentCancelled integration events"
```

---

### Task 2 : Mettre à jour le contexte Reservation — handlers

**Files:**
- Modify: `src/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandler.php`
- Modify: `src/Reservation/Application/UseCase/ConfirmReservation/ConfirmReservationCommandHandler.php`
- Modify: `src/Reservation/Application/UseCase/ExpireReservation/ExpireReservationCommandHandler.php`
- Modify: `src/Reservation/Application/UseCase/CancelPendingReservation/CancelPendingReservationCommandHandler.php`

- [ ] **Step 1 : Mettre à jour `CreateReservationCommandHandler`**

Remplacer l'import :
```php
use App\Reservation\Domain\Event\ReservationCreated;
```
par :
```php
use App\Shared\Domain\Event\ReservationCreated;
```

Puis remplacer le bloc de dispatch dans la transaction :
```php
$this->eventDispatcher->dispatch(new ReservationCreated(
    reservationId: $reservation->id,
    roomId: $reservation->roomId,
    bookerId: $reservation->bookerId,
    checkIn: $reservation->period->checkIn,
    checkOut: $reservation->period->checkOut,
    totalPrice: $reservation->totalPrice,
    cancellationTermsDaysThreshold: $reservation->cancellationTerms->daysThreshold,
    priceBreakdown: $reservation->priceBreakdown->toArray(),
));
```

- [ ] **Step 2 : Mettre à jour `ConfirmReservationCommandHandler`**

Remplacer l'import :
```php
use App\Reservation\Domain\Event\ReservationConfirmed;
```
par :
```php
use App\Shared\Domain\Event\ReservationConfirmed;
```

Le corps du dispatch reste identique.

- [ ] **Step 3 : Mettre à jour `ExpireReservationCommandHandler`**

Remplacer l'import :
```php
use App\Reservation\Domain\Event\ReservationExpired;
```
par :
```php
use App\Shared\Domain\Event\ReservationExpired;
```

Le corps du dispatch reste identique.

- [ ] **Step 4 : Mettre à jour `CancelPendingReservationCommandHandler`**

Remplacer l'import :
```php
use App\Reservation\Domain\Event\ReservationPaymentCancelled;
```
par :
```php
use App\Shared\Domain\Event\ReservationPaymentCancelled;
```

Le corps du dispatch reste identique.

- [ ] **Step 5 : Commiter**

```bash
git add src/Reservation/Application/UseCase/
git commit -m "refactor(reservation): use Shared integration events in application handlers"
```

---

### Task 3 : Mettre à jour les tests Reservation

**Files:**
- Modify: `tests/Reservation/Application/UseCase/CreateReservation/CreateReservationCommandHandlerTest.php`
- Modify: `tests/Reservation/Application/UseCase/ExpireReservation/ExpireReservationCommandHandlerTest.php`
- Modify: `tests/Reservation/Integration/UseCase/ConfirmReservation/ConfirmReservationCommandHandlerTest.php`
- Modify: `tests/Reservation/Integration/UseCase/CancelPendingReservation/CancelPendingReservationCommandHandlerTest.php`

- [ ] **Step 1 : Mettre à jour `CreateReservationCommandHandlerTest`**

Remplacer l'import :
```php
use App\Reservation\Domain\Event\ReservationCreated;
```
par :
```php
use App\Shared\Domain\Event\ReservationCreated;
```

Mettre à jour les 3 assertions sur les champs aplatis de l'event (les assertions sur `$reservation` restent inchangées — c'est un objet interne Reservation) :

Remplacer :
```php
self::assertNull($event->cancellationTerms->daysThreshold);
self::assertCount(4, $event->priceBreakdown->nights);
```
par :
```php
self::assertNull($event->cancellationTermsDaysThreshold);
self::assertCount(4, $event->priceBreakdown);
```

Remplacer :
```php
self::assertSame(7, $event->cancellationTerms->daysThreshold);
```
par :
```php
self::assertSame(7, $event->cancellationTermsDaysThreshold);
```

Remplacer :
```php
self::assertCount(2, $event->priceBreakdown->nights);
```
par :
```php
self::assertCount(2, $event->priceBreakdown);
```

- [ ] **Step 2 : Mettre à jour `ExpireReservationCommandHandlerTest`**

Remplacer l'import :
```php
use App\Reservation\Domain\Event\ReservationExpired;
```
par :
```php
use App\Shared\Domain\Event\ReservationExpired;
```

- [ ] **Step 3 : Mettre à jour `ConfirmReservationCommandHandlerTest`**

Remplacer l'import :
```php
use App\Reservation\Domain\Event\ReservationConfirmed;
```
par :
```php
use App\Shared\Domain\Event\ReservationConfirmed;
```

- [ ] **Step 4 : Mettre à jour `CancelPendingReservationCommandHandlerTest`**

Remplacer l'import :
```php
use App\Reservation\Domain\Event\ReservationPaymentCancelled;
```
par :
```php
use App\Shared\Domain\Event\ReservationPaymentCancelled;
```

- [ ] **Step 5 : Lancer les tests**

```bash
docker compose run --rm php make test
```

Expected : tous verts (les tests Reservation compilent et passent avec les nouveaux imports).

- [ ] **Step 6 : Commiter**

```bash
git add tests/Reservation/
git commit -m "test(reservation): update event imports to Shared integration events"
```

---

### Task 4 : Mettre à jour le contexte Availability — listeners

**Files:**
- Modify: `src/Availability/Infrastructure/EventListener/ReservationCreatedListener.php`
- Modify: `src/Availability/Infrastructure/EventListener/ReservationConfirmedListener.php`
- Modify: `src/Availability/Infrastructure/EventListener/ReservationExpiredListener.php`
- Modify: `src/Availability/Infrastructure/EventListener/ReservationPaymentCancelledListener.php`

- [ ] **Step 1 : Mettre à jour `ReservationCreatedListener`**

Remplacer l'import :
```php
use App\Reservation\Domain\Event\ReservationCreated;
```
par :
```php
use App\Shared\Domain\Event\ReservationCreated;
```

La signature `__invoke(ReservationCreated $event)` et le corps du listener restent identiques — les champs utilisés (`roomId`, `reservationId`, `checkIn`, `checkOut`) existent toujours sur la nouvelle classe.

- [ ] **Step 2 : Mettre à jour `ReservationConfirmedListener`**

Remplacer l'import :
```php
use App\Reservation\Domain\Event\ReservationConfirmed;
```
par :
```php
use App\Shared\Domain\Event\ReservationConfirmed;
```

- [ ] **Step 3 : Mettre à jour `ReservationExpiredListener`**

Remplacer l'import :
```php
use App\Reservation\Domain\Event\ReservationExpired;
```
par :
```php
use App\Shared\Domain\Event\ReservationExpired;
```

- [ ] **Step 4 : Mettre à jour `ReservationPaymentCancelledListener`**

Remplacer l'import :
```php
use App\Reservation\Domain\Event\ReservationPaymentCancelled;
```
par :
```php
use App\Shared\Domain\Event\ReservationPaymentCancelled;
```

- [ ] **Step 5 : Commiter**

```bash
git add src/Availability/Infrastructure/EventListener/
git commit -m "refactor(availability): use Shared integration events in event listeners"
```

---

### Task 5 : Mettre à jour le contexte Notification — listener et test

**Files:**
- Modify: `src/Notification/Infrastructure/EventListener/ReservationConfirmedListener.php`
- Modify: `tests/Notification/Infrastructure/EventListener/ReservationConfirmedListenerTest.php`

- [ ] **Step 1 : Mettre à jour `Notification/ReservationConfirmedListener`**

Remplacer l'import :
```php
use App\Reservation\Domain\Event\ReservationConfirmed;
```
par :
```php
use App\Shared\Domain\Event\ReservationConfirmed;
```

- [ ] **Step 2 : Mettre à jour `ReservationConfirmedListenerTest`**

Remplacer l'import :
```php
use App\Reservation\Domain\Event\ReservationConfirmed;
```
par :
```php
use App\Shared\Domain\Event\ReservationConfirmed;
```

- [ ] **Step 3 : Commiter**

```bash
git add src/Notification/Infrastructure/EventListener/ tests/Notification/
git commit -m "refactor(notification): use Shared integration events in event listener"
```

---

### Task 6 : Supprimer les anciens fichiers et vérifier

**Files:**
- Delete: `src/Reservation/Domain/Event/ReservationCreated.php`
- Delete: `src/Reservation/Domain/Event/ReservationConfirmed.php`
- Delete: `src/Reservation/Domain/Event/ReservationExpired.php`
- Delete: `src/Reservation/Domain/Event/ReservationPaymentCancelled.php`

- [ ] **Step 1 : Supprimer les anciens events**

```bash
git rm src/Reservation/Domain/Event/ReservationCreated.php \
       src/Reservation/Domain/Event/ReservationConfirmed.php \
       src/Reservation/Domain/Event/ReservationExpired.php \
       src/Reservation/Domain/Event/ReservationPaymentCancelled.php
```

- [ ] **Step 2 : Vérifier qu'aucune référence résiduelle n'existe**

```bash
grep -r "Reservation.Domain.Event" src/ tests/ --include="*.php"
```

Expected : aucune ligne — si des occurrences apparaissent, les corriger avant de continuer.

- [ ] **Step 3 : Lancer deptrac**

```bash
docker compose run --rm php make deptrac
```

Expected : aucune violation.

- [ ] **Step 4 : Lancer le linter**

```bash
docker compose run --rm php make lint
```

Expected : aucune erreur CS Fixer.

- [ ] **Step 5 : Lancer toute la suite de tests**

```bash
docker compose run --rm php make test
```

Expected : tous verts.

- [ ] **Step 6 : Commiter**

```bash
git add -u
git commit -m "refactor(reservation): remove old Reservation\\Domain\\Event classes (moved to Shared)"
```

# Cas 2 — Découplement Payment/Reservation par chorégraphie événementielle

**Date :** 2026-06-05
**Contexte :** Suite au cas 1 (PRs #46 et #47 — contrats publiés pour les lectures), ce document traite le cas 2 : le couplage en écriture entre Payment et Reservation.
**Statut :** Approuvé — prêt pour implémentation.

---

## Problème

`Payment\Infrastructure\Service\ReservationPaymentConfirmer` importe `ConfirmReservationCommand` (interne à Reservation). `ReservationPaymentCanceller` importe `CancelPendingReservationCommand`. Payment pilote Reservation directement — violation du principe Published Language / Open Host Service.

```php
// AVANT — Payment\Infrastructure\Service\ReservationPaymentConfirmer
use App\Reservation\Application\UseCase\ConfirmReservation\ConfirmReservationCommand; // ❌ interne
$this->commandBus->execute(new ConfirmReservationCommand($reservationId));

// AVANT — Payment\Infrastructure\Service\ReservationPaymentCanceller
use App\Reservation\Application\UseCase\CancelPendingReservation\CancelPendingReservationCommand; // ❌ interne
$this->commandBus->execute(new CancelPendingReservationCommand($reservationId));
```

---

## Solution — Chorégraphie événementielle

Payment publie un **fait** (`PaymentConfirmed` / `PaymentCancelled`). Reservation écoute et réagit en interne. Payment ne connaît plus Reservation.

```
HandlePaymentSuccessCommandHandler
  → EventDispatcherInterface::dispatch(new PaymentConfirmed($reservationId))
                ↓ (Symfony EventDispatcher — synchrone)
PaymentConfirmedListener  [Reservation\Infrastructure\EventListener]
  → SyncCommandBusInterface::execute(new ConfirmReservationCommand($reservationId))
    → ConfirmReservationCommandHandler  (interne Reservation)

HandlePaymentCancellationCommandHandler
  → EventDispatcherInterface::dispatch(new PaymentCancelled($reservationId))
                ↓
PaymentCancelledListener  [Reservation\Infrastructure\EventListener]
  → SyncCommandBusInterface::execute(new CancelPendingReservationCommand($reservationId))
    → CancelPendingReservationCommandHandler  (interne Reservation)
```

> **Cohérence :** l'EventDispatcher Symfony est synchrone — les listeners s'exécutent dans le même thread. Pas de cohérence éventuelle introduite par rapport à l'existant.

> **Hors scope :** `HandlePaymentFailureCommand` reste un stub vide — non traité ici.

---

## Fichiers créés

| Fichier | Rôle |
|---------|------|
| `src/Shared/Domain/Event/PaymentConfirmed.php` | Événement : paiement confirmé, payload `reservationId` |
| `src/Shared/Domain/Event/PaymentCancelled.php` | Événement : paiement annulé, payload `reservationId` |
| `src/Reservation/Infrastructure/EventListener/PaymentConfirmedListener.php` | Listener : dispatche `ConfirmReservationCommand` |
| `src/Reservation/Infrastructure/EventListener/PaymentCancelledListener.php` | Listener : dispatche `CancelPendingReservationCommand` |

### PaymentConfirmed / PaymentCancelled

```php
// src/Shared/Domain/Event/PaymentConfirmed.php
final readonly class PaymentConfirmed
{
    public function __construct(public string $reservationId) {}
}

// src/Shared/Domain/Event/PaymentCancelled.php
final readonly class PaymentCancelled
{
    public function __construct(public string $reservationId) {}
}
```

### PaymentConfirmedListener

```php
// src/Reservation/Infrastructure/EventListener/PaymentConfirmedListener.php
#[AsEventListener(event: PaymentConfirmed::class)]
final readonly class PaymentConfirmedListener
{
    public function __construct(private SyncCommandBusInterface $commandBus) {}

    public function __invoke(PaymentConfirmed $event): void
    {
        $this->commandBus->execute(new ConfirmReservationCommand($event->reservationId));
    }
}
```

Même structure pour `PaymentCancelledListener` → `CancelPendingReservationCommand`.

---

## Fichiers supprimés

| Fichier | Raison |
|---------|--------|
| `src/Payment/Domain/Port/ReservationPaymentConfirmerInterface.php` | Port devenu inutile |
| `src/Payment/Domain/Port/ReservationPaymentCancellerInterface.php` | Port devenu inutile |
| `src/Payment/Infrastructure/Service/ReservationPaymentConfirmer.php` | Remplacé par l'événement |
| `src/Payment/Infrastructure/Service/ReservationPaymentCanceller.php` | Remplacé par l'événement |

---

## Fichiers modifiés

### HandlePaymentSuccessCommandHandler

```php
// AVANT
public function __construct(private ReservationPaymentConfirmerInterface $confirmer) {}
public function __invoke(HandlePaymentSuccessCommand $command): void
{
    $this->confirmer->confirm($command->reservationId);
}

// APRÈS
public function __construct(private EventDispatcherInterface $eventDispatcher) {}
public function __invoke(HandlePaymentSuccessCommand $command): void
{
    $this->eventDispatcher->dispatch(new PaymentConfirmed($command->reservationId));
}
```

### HandlePaymentCancellationCommandHandler

Même transformation : `ReservationPaymentCancellerInterface` → `EventDispatcherInterface`, `$canceller->cancel()` → `dispatch(new PaymentCancelled(...))`.

### domainevents.yaml

Ajouter :

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

---

## Gestion d'erreurs

Les handlers `ConfirmReservationCommandHandler` et `CancelPendingReservationCommandHandler` sont déjà idempotents (return silencieux si réservation introuvable ou mauvais statut). Les listeners n'ont pas besoin de gérer d'exception métier.

Une exception inattendue dans un listener remonte à l'appelant (le handler Payment) — même comportement qu'aujourd'hui.

---

## Tests

### Handlers Payment modifiés

Remplacer le mock de `ReservationPaymentConfirmerInterface` / `ReservationPaymentCancellerInterface` par un mock de `EventDispatcherInterface`. Vérifier que `dispatch()` est appelé avec le bon événement et le bon `reservationId`.

### Nouveaux listeners Reservation

Tests unitaires : mock de `SyncCommandBusInterface`, vérifier que `execute()` reçoit la bonne command avec le bon `reservationId`.

### Handlers Reservation existants

Inchangés — `ConfirmReservationCommandHandler` et `CancelPendingReservationCommandHandler` restent identiques.

---

## Résultat

| Avant | Après |
|-------|-------|
| Payment importe `ConfirmReservationCommand` (interne Reservation) | Payment ne connaît plus Reservation |
| Payment importe `CancelPendingReservationCommand` (interne Reservation) | Payment publie un fait, Reservation réagit |
| 2 ports + 2 services dans Payment | 2 événements dans Shared + 2 listeners dans Reservation |

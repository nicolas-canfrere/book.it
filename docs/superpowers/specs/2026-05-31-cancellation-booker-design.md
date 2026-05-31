# Cancellation par le Booker — Design Spec

**Date**: 2026-05-31  
**Status**: Approved

---

## Contexte

Complétion du cycle de vie Reservation côté Booker. La Cancellation est l'acte volontaire du Booker de résilier sa propre Reservation avant la date de check-in. Le remboursement est déterminé par les `CancellationTerms` snapshotées à la création de la Reservation.

---

## Endpoint

```
POST /reservations/{id}/cancel
```

- Pas de body
- `{id}` : UUID v4 (requirement `Requirement::UUID_V4`)
- Réponses :
  - `204 No Content` — annulation effectuée
  - `404` — Reservation introuvable
  - `409` — statut invalide (`InvalidReservationTransitionException`) ou date dépassée (`CancellationNotAllowedException`)
  - `422` — UUID malformé

---

## Domain

### Champs ajoutés à `Reservation`

```php
public ?\DateTimeImmutable $cancelledAt = null;
public ?string $cancelledBy = null;
```

Nullable, non readonly. Alimentés par `cancelByBooker()`.

### `Reservation::cancelByBooker(\DateTimeImmutable $today): void`

1. Guard statut : lève `InvalidReservationTransitionException($this->status, ReservationStatus::Cancelled)` si statut ≠ `confirmed`
2. Guard date : lève `CancellationNotAllowedException` si `$today >= $this->period->checkIn`
3. Transition : `status = Cancelled`, `cancelledAt = $today`, `cancelledBy = 'booker'`

### `CancellationNotAllowedException`

Nouvelle exception dans `Reservation/Domain/Exception/`. Mappée 409 dans `config/services/exceptions.yaml`.

---

## Application

### `CancelReservationCommand`

```php
public string $reservationId,
public \DateTimeImmutable $today,
```

### `CancelReservationCommandHandler` (implements `SyncCommandHandlerInterface`)

1. Charge la Reservation via `ReservationRepositoryInterface::get()`
2. Lève `ReservationNotFoundException` si absente
3. Appelle `$reservation->cancelByBooker($command->today)`
4. Sauvegarde via `ReservationRepositoryInterface::save()`
5. Calcule `$refundAmountCents = $reservation->cancellationTerms->isRefundable($reservation->cancelledAt, $reservation->period->checkIn) ? $reservation->totalPrice : 0`
6. Dispatche `ReservationCancelled`

---

## Event

### `ReservationCancelled` (dans `Shared/Domain/Event/`)

```php
public string $reservationId,
public string $roomId,
public string $bookerId,
public int $refundAmountCents,
```

---

## Listeners

### `Availability\ReservationCancelledListener`

- Écoute `ReservationCancelled`
- Exécute `DeleteBlockedPeriodByRoomAndPeriodCommand(roomId, checkIn, checkOut)`
- Pattern identique à `ReservationCheckedOutListener`
- Nécessite `checkIn` et `checkOut` dans l'event → les ajouter

> **Note** : `checkIn` et `checkOut` doivent être présents dans `ReservationCancelled` pour que le listener Availability puisse supprimer le Blocked Period.

### `Payment\ReservationCancelledListener`

- Écoute `ReservationCancelled`
- Log uniquement (`$logger->info(...)`) avec `reservationId`, `bookerId`, `refundAmountCents`
- Implémente `AsyncCommandHandlerInterface` (fire-and-forget)

---

## Event — champs complets

En tenant compte du besoin du listener Availability :

```php
public string $reservationId,
public string $roomId,
public string $bookerId,
public int $refundAmountCents,
public \DateTimeImmutable $checkIn,
public \DateTimeImmutable $checkOut,
```

---

## UI

### `CancelReservationController`

```php
#[Route('/reservations/{id}/cancel', name: 'reservation_cancel',
    requirements: ['id' => Requirement::UUID_V4], methods: ['POST'])]
```

- Pas de `MapRequestPayload` (aucun body)
- Injecte `today: new \DateTimeImmutable('today')`
- Retourne `Response::HTTP_NO_CONTENT`

---

## Mapping exceptions

Dans `config/services/exceptions.yaml` :

```yaml
App\Reservation\Domain\Exception\CancellationNotAllowedException:
    type: 'https://book.it/problems/cancellation-not-allowed'
    title: 'Cancellation Not Allowed'
    status: 409
```

---

## Hors scope

- Remboursement effectif vers le Payment Provider (déclaratif seulement pour l'instant)
- Revocation opérateur (feature distincte)
- CancellationNotification (feature distincte)

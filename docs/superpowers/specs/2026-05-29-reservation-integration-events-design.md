# Design — Integration events Reservation dans Shared\Domain\Event\

Date: 2026-05-29

## Contexte

Les events `ReservationCreated`, `ReservationConfirmed`, `ReservationExpired` et `ReservationPaymentCancelled` vivent dans `Reservation\Domain\Event\` mais sont consommés par deux autres contextes :

- `Availability\Infrastructure\EventListener\` (4 listeners)
- `Notification\Infrastructure\EventListener\` (1 listener)

Cette dépendance croise les frontières de contextes sur les internals du domaine Reservation. Les events inter-contextes font partie de l'API publique du contexte producteur — ils doivent vivre dans `Shared\Domain\Event\`.

## Solution

Déplacer les 4 events vers `App\Shared\Domain\Event\`. Aplatir les value objects internes dans `ReservationCreated` en primitives pour respecter la règle deptrac `Shared → ExternalPsr | External` uniquement.

## Changements

### Fichiers créés

`src/Shared/Domain/Event/` — 4 nouvelles classes :

**ReservationCreated**
- `CancellationTerms $cancellationTerms` → `?int $cancellationTermsDaysThreshold`
- `PriceBreakdown $priceBreakdown` → `array $priceBreakdown` (format `PriceBreakdown::toArray()`)
- Tous les autres champs inchangés (primitives, DateTimeImmutable)

**ReservationConfirmed**, **ReservationExpired**, **ReservationPaymentCancelled**
- Namespace uniquement : `App\Shared\Domain\Event\` (corps identique)

### Fichiers modifiés

| Fichier | Changement |
|---------|------------|
| `Reservation\Application\UseCase\CreateReservation\CreateReservationCommandHandler` | Import + passer `->daysThreshold` et `->toArray()` à l'event |
| `Reservation\Application\UseCase\ConfirmReservation\ConfirmReservationCommandHandler` | Import uniquement |
| `Reservation\Application\UseCase\ExpireReservation\ExpireReservationCommandHandler` | Import uniquement |
| `Reservation\Application\UseCase\CancelPendingReservation\CancelPendingReservationCommandHandler` | Import uniquement |
| `Availability\Infrastructure\EventListener\ReservationCreatedListener` | Import uniquement |
| `Availability\Infrastructure\EventListener\ReservationConfirmedListener` | Import uniquement |
| `Availability\Infrastructure\EventListener\ReservationExpiredListener` | Import uniquement |
| `Availability\Infrastructure\EventListener\ReservationPaymentCancelledListener` | Import uniquement |
| `Notification\Infrastructure\EventListener\ReservationConfirmedListener` | Import uniquement |

### Fichiers supprimés

- `src/Reservation/Domain/Event/ReservationCreated.php`
- `src/Reservation/Domain/Event/ReservationConfirmed.php`
- `src/Reservation/Domain/Event/ReservationExpired.php`
- `src/Reservation/Domain/Event/ReservationPaymentCancelled.php`

## Vérification

1. `make deptrac` — aucune violation de couche
2. `make lint` — CS Fixer propre
3. `make test` — suite complète verte

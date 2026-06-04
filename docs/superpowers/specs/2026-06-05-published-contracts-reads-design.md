# Design — Published Contracts pour les lectures cross-contexte

**Date :** 2026-06-05
**Contexte :** Suite au refactoring `BookerFinderInterface` (PR #46) et à la note Obsidian `decoupling-inter-contextes`, appliquer le pattern *Published Language / Open Host Service* à tous les services de lecture cross-contexte.
**Scope :** Lectures uniquement (10 services). Les écritures Payment→Reservation (événements de domaine) sont hors scope.

---

## Problème

Dix services d'infrastructure consomment des classes internes d'autres contextes (Query + agrégat de domaine) via le `SyncQueryBus`. C'est un couplage build-time qui viole le principe DDD de *Published Language*.

### Inventaire des couplages à traiter

| Consommateur | Import interne | Producteur |
|---|---|---|
| `Availability\Infra\RoomExistenceChecker` | `GetRoomQuery` + agrégat `Room` | Room |
| `Pricing\Infra\RoomExistenceChecker` | `GetRoomQuery` + agrégat `Room` | Room |
| `Reservation\Infra\RoomExistenceChecker` | `GetRoomQuery` + agrégat `Room` | Room |
| `Reservation\Infra\RoomCapacityFetcher` | `GetRoomCapacityQuery` + agrégat `Room` | Room |
| `Room\Infra\HotelExistenceChecker` | `GetHotelQuery` + agrégat `Hotel` | Hotel |
| `Notification\Infra\BookerContactFetcher` | `GetBookerQuery` + agrégat `Booker` | Booker |
| `Notification\Infra\ReservationDetailsFetcher` | `GetReservationQuery` + agrégat `Reservation` | Reservation |
| `Reservation\Infra\PricingQuoteFetcher` | `GetPricingQuoteQuery` (retourne array) | Pricing |
| `Reservation\Infra\PricingCancellationPolicyFetcher` | `GetCancellationPolicyQuery` + agrégat `CancellationPolicy` | Pricing |
| `Reservation\Infra\AvailabilityChecker` | `CheckAvailabilityQuery` (retourne bool) | Availability |

---

## Pattern adopté

Identique à ce qui a été fait pour `BookerFinderInterface` (PR #46) :

```
{Context}/Application/Contract/{Entity}FinderInterface.php   ← contrat publié
{Context}/Application/Contract/{Entity}View.php              ← DTO stable
{Context}/Infrastructure/Contract/Doctrine{Entity}Finder.php ← implémentation
```

Le producteur publie une interface + un DTO. Les consommateurs injectent l'interface directement. Le port du domaine consommateur reste inchangé.

---

## Contrats à créer par contexte producteur

### 1. Room (4 consommateurs)

**Contrat publié :**
```php
// Room/Application/Contract/RoomFinderInterface.php
interface RoomFinderInterface
{
    public function find(string $roomId): ?RoomView;
}

// Room/Application/Contract/RoomView.php
final readonly class RoomView
{
    public function __construct(
        public string $id,
        public int $capacity,
    ) {}
}
```

**Implémentation :** `Room/Infrastructure/Contract/DoctrineRoomFinder` — injecte `RoomRepositoryInterface`, mappe `Room` → `RoomView`.

**Consommateurs mis à jour :**
- `Availability\Infra\RoomExistenceChecker` : `find() !== null`
- `Pricing\Infra\RoomExistenceChecker` : `find() !== null`
- `Reservation\Infra\RoomExistenceChecker` : `find() !== null`
- `Reservation\Infra\RoomCapacityFetcher` : `find()->capacity` (un seul contrat couvre les deux besoins)

### 2. Hotel (1 consommateur)

**Contrat publié :**
```php
// Hotel/Application/Contract/HotelFinderInterface.php
interface HotelFinderInterface
{
    public function find(string $hotelId): ?HotelView;
}

// Hotel/Application/Contract/HotelView.php
final readonly class HotelView
{
    public function __construct(public string $id) {}
}
```

**Implémentation :** `Hotel/Infrastructure/Contract/DoctrineHotelFinder` — injecte `HotelRepositoryInterface`, mappe `Hotel` → `HotelView`.

**Consommateur :** `Room\Infra\HotelExistenceChecker` : `find() !== null`

### 3. Booker (1 consommateur — migration uniquement)

`BookerFinderInterface` + `BookerView` **existent déjà** (PR #46). Seul `Notification\Infra\BookerContactFetcher` reste à migrer : remplacer `SyncQueryBus + GetBookerQuery + agrégat Booker` par injection directe de `BookerFinderInterface`.

### 4. Reservation (1 consommateur)

**Contrat publié :**
```php
// Reservation/Application/Contract/ReservationFinderInterface.php
interface ReservationFinderInterface
{
    public function find(string $reservationId): ?ReservationView;
}

// Reservation/Application/Contract/ReservationView.php
final readonly class ReservationView
{
    public function __construct(
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $totalPriceCents,
    ) {}
}
```

**Implémentation :** `Reservation/Infrastructure/Contract/DoctrineReservationFinder` — injecte `ReservationRepositoryInterface`, mappe `Reservation` → `ReservationView`.

**Consommateur :** `Notification\Infra\ReservationDetailsFetcher`

### 5. Pricing (2 consommateurs)

Deux contrats distincts pour deux besoins distincts.

**Contrat quote :**
```php
// Pricing/Application/Contract/PricingQuoteFinderInterface.php
interface PricingQuoteFinderInterface
{
    public function fetch(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteView;
}

// Pricing/Application/Contract/PricingQuoteView.php
final readonly class PricingQuoteView
{
    public function __construct(
        public int $totalAmountCents,
        public array $nights, // list<array{date, rateAmountCents, discountPercent, effectiveAmountCents}>
    ) {}
}
```

**Implémentation `DoctrinePricingQuoteFinder` :** La logique de calcul (3 repositories + itération nuit par nuit) est déjà dans `GetPricingQuoteQueryHandler`. Pour éviter la duplication, l'implémentation compose directement avec le handler — autorisé par deptrac (`Pricing\Infrastructure → Pricing\Application`) :

```php
final readonly class DoctrinePricingQuoteFinder implements PricingQuoteFinderInterface {
    public function __construct(private GetPricingQuoteQueryHandler $handler) {}

    public function fetch(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteView {
        $result = ($this->handler)(new GetPricingQuoteQuery($roomId, $checkIn, $checkOut));
        return new PricingQuoteView($result['totalAmountCents'], $result['nights']);
    }
}
```

**Contrat cancellation policy :**
```php
// Pricing/Application/Contract/CancellationPolicyFinderInterface.php
interface CancellationPolicyFinderInterface
{
    public function find(string $roomId): ?CancellationPolicyView;
}

// Pricing/Application/Contract/CancellationPolicyView.php
final readonly class CancellationPolicyView
{
    public function __construct(public int $daysThreshold) {}
}
```

**Implémentation :** `DoctrineCancellationPolicyFinder` — injecte `CancellationPolicyRepositoryInterface`, mappe `CancellationPolicy` → `CancellationPolicyView`.

**Consommateurs :**
- `Reservation\Infra\PricingQuoteFetcher` → injecte `PricingQuoteFinderInterface`
- `Reservation\Infra\PricingCancellationPolicyFetcher` → injecte `CancellationPolicyFinderInterface`

### 6. Availability (1 consommateur)

Cas particulier : le résultat est un booléen, pas une entité. Le contrat reste une interface de style "checker" (pas de DTO `*View`).

```php
// Availability/Application/Contract/AvailabilityCheckerInterface.php
interface AvailabilityCheckerInterface
{
    public function isAvailable(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool;
}
```

**Implémentation :** `Availability/Infrastructure/Contract/DoctrineAvailabilityChecker` — injecte `BlockedPeriodRepositoryInterface` + `AvailabilityHoldRepositoryInterface`, logique identique au handler existant.

**Consommateur :** `Reservation\Infra\AvailabilityChecker`

---

## Ordre de migration (incrémental)

1. **Booker** — migration seule (contrat déjà existant), 1 fichier à modifier
2. **Hotel** — 1 consommateur, structure simple
3. **Room** — 4 consommateurs, tous simples (existence + capacity)
4. **Reservation** — 1 consommateur (Notification)
5. **Availability** — 1 consommateur, pas de `*View`
6. **Pricing** — le plus complexe (2 contrats, composition avec handler)

---

## Impact sur les tests

Pour chaque contexte producteur :
- Tests unitaires de l'implémentation Infrastructure (`Doctrine*Finder`) avec stub du repository
- Tests unitaires des adaptateurs consommateurs mis à jour

---

## Bonus — deptrac par contexte

En dernière étape (hors scope immédiat) : ajouter des layers par contexte dans `deptrac.yaml` pour n'autoriser que `{Context}\Application\Contract\*` comme point d'entrée cross-contexte. Transforme la convention en garde-fou automatique.

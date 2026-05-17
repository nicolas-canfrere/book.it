# Reservation Context — Étape 1 : Fondation (lifecycle basique)

## Contexte

Le contexte `Reservation` est découpé en 5 étapes successives. Cette spec couvre l'étape 1 :
création, confirmation et annulation d'une réservation, sans blocage de disponibilité (le hold
avec expiration est traité en Étape 2).

Contextes existants consommés via ports :
- **Room** — vérification de l'existence de la chambre
- **Booker** — vérification de l'existence du booker
- **Availability** — vérification de la disponibilité (query, sans bloc)
- **Pricing** — calcul du prix total au moment de la création

---

## Modèle de domaine

### Agrégat `Reservation`

| Champ        | Type                | Notes                                    |
|--------------|---------------------|------------------------------------------|
| `id`         | UuidV4              | Identifiant de la réservation            |
| `roomId`     | UuidV4              | Référence opaque vers Room               |
| `bookerId`   | UuidV4              | Référence opaque vers Booker             |
| `period`     | `DatePeriod`        | VO local (checkIn → checkOut)            |
| `totalPrice` | `int`               | Centimes, snapshot calculé à la création |
| `status`     | `ReservationStatus` | Enum                                     |
| `createdAt`  | `DateTimeImmutable` |                                          |

### `ReservationStatus` (enum)

```
pending | confirmed | cancelled
```

Transitions autorisées :

| De          | Vers        | Use case           |
|-------------|-------------|--------------------|
| `pending`   | `confirmed` | ConfirmReservation |
| `pending`   | `cancelled` | CancelReservation  |
| `confirmed` | `cancelled` | CancelReservation  |

Toute autre transition lève `InvalidReservationTransitionException`.

> Les états `checked_in`, `checked_out`, `no_show` sont réservés aux étapes 3+.

### `DatePeriod` (Value Object)

Dupliqué dans `Reservation/Domain/ValueObject/` — chaque contexte est propriétaire de ses VO.
Ne pas importer depuis `src/Availability/`.

Invariant : `checkOut > checkIn`.

### Exceptions domaine

| Exception                               | Signification                      |
|-----------------------------------------|------------------------------------|
| `ReservationNotFoundException`          | Réservation introuvable            |
| `RoomNotFoundException`                 | Chambre référencée introuvable     |
| `BookerNotFoundException`               | Booker référencé introuvable       |
| `RoomNotAvailableException`             | Chambre occupée sur la période     |
| `RoomNotBookableException`              | Aucun tarif configuré dans Pricing |
| `InvalidReservationTransitionException` | Transition d'état invalide         |

---

## Couche application

### Use cases

| Use case             | Type    | Handler                            |
|----------------------|---------|------------------------------------|
| `CreateReservation`  | Command | `CreateReservationCommandHandler`  |
| `ConfirmReservation` | Command | `ConfirmReservationCommandHandler` |
| `CancelReservation`  | Command | `CancelReservationCommandHandler`  |
| `GetReservation`     | Query   | `GetReservationQueryHandler`       |

### Flux `CreateReservationCommandHandler`

1. Vérifier que la chambre existe → `RoomExistsInterface` → `RoomNotFoundException` si absente
2. Vérifier que le booker existe → `BookerExistsInterface` → `BookerNotFoundException` si absent
3. Vérifier la disponibilité → `RoomAvailabilityCheckerInterface` → `RoomNotAvailableException` si occupée
4. Calculer le prix total → `PriceCalculatorInterface` → `RoomNotBookableException` si aucun tarif configuré (Base Rate absent) — un prix de 0 € est une réservation valide
5. Créer l'agrégat en `pending`, persister, dispatcher `ReservationCreated`

### Ports (`Reservation/Domain/Port/`)

| Port                               | Retour           | Contexte cible     |
|------------------------------------|------------------|--------------------|
| `ReservationRepositoryInterface`   | —                | Persistence locale |
| `RoomExistsInterface`              | `bool`           | Room               |
| `BookerExistsInterface`            | `bool`           | Booker             |
| `RoomAvailabilityCheckerInterface` | `bool`           | Availability       |
| `PriceCalculatorInterface`         | `int` (centimes) | Pricing            |

### Domain events

| Événement              | Dispatché par                      | Payload                                                                    |
|------------------------|------------------------------------|----------------------------------------------------------------------------|
| `ReservationCreated`   | `CreateReservationCommandHandler`  | `reservationId`, `roomId`, `bookerId`, `checkIn`, `checkOut`, `totalPrice` |
| `ReservationConfirmed` | `ConfirmReservationCommandHandler` | `reservationId`, `roomId`, `checkIn`, `checkOut`                           |
| `ReservationCancelled` | `CancelReservationCommandHandler`  | `reservationId`, `roomId`, `checkIn`, `checkOut`                           |

Aucun subscriber dans cette étape. Les events sont posés pour les étapes suivantes
(notamment Étape 2 qui s'appuiera sur `ReservationCreated` pour poser un `BlockedPeriod`
dès la création de la réservation).

---

## Couche HTTP

### Endpoints

| Méthode  | Route                        | Statut succès  |
|----------|------------------------------|----------------|
| `POST`   | `/reservations`              | 201 Created    |
| `GET`    | `/reservations/{id}`         | 200 OK         |
| `PATCH`  | `/reservations/{id}/confirm` | 200 OK         |
| `DELETE` | `/reservations/{id}`         | 204 No Content |

Le paramètre `{id}` utilise `Requirement::UUID_V4`.

### Body `POST /reservations`

```json
{
  "roomId": "uuid-v4",
  "bookerId": "uuid-v4",
  "checkIn": "2026-06-01",
  "checkOut": "2026-06-05"
}
```

Validation (`#[MapRequestPayload]`, `validationFailedStatusCode: 422`) :
- `roomId`, `bookerId` : UUID v4 valide, non null
- `checkIn`, `checkOut` : dates valides, `checkOut > checkIn`, `checkIn >= today` (UTC)

### Réponse (GET / POST / PATCH)

```json
{
  "id": "uuid-v4",
  "roomId": "uuid-v4",
  "bookerId": "uuid-v4",
  "checkIn": "2026-06-01",
  "checkOut": "2026-06-05",
  "totalPrice": 42000,
  "status": "pending",
  "createdAt": "2026-05-17T10:00:00Z"
}
```

### Mappings Problem Details (`config/services/exceptions.yaml`)

| Exception                               | Status |
|-----------------------------------------|--------|
| `ReservationNotFoundException`          | 404    |
| `RoomNotFoundException`                 | 404    |
| `BookerNotFoundException`               | 404    |
| `RoomNotAvailableException`             | 409    |
| `RoomNotBookableException`              | 422    |
| `InvalidReservationTransitionException` | 409    |

---

## Tests

### Unitaires (`#[Group('unit')]`)

- Création de `Reservation` : champs correctement initialisés, status = `pending`
- Transitions valides : `pending → confirmed`, `pending → cancelled`, `confirmed → cancelled`
- Transitions invalides → `InvalidReservationTransitionException`
- `DatePeriod` : invariant `checkOut > checkIn` enforced

### Intégration (`#[Group('integration')]`)

Les ports externes (`RoomExistsInterface`, `BookerExistsInterface`, `RoomAvailabilityCheckerInterface`,
`PriceCalculatorInterface`) sont doublés (stubs) — pas de vraie base Availability/Pricing.

Scénarios par handler :

**CreateReservation** : chambre inexistante, booker inexistant, chambre indisponible,
aucun tarif configuré, succès (réservation en `pending`, event `ReservationCreated` dispatché).

**ConfirmReservation** : réservation introuvable, transition invalide (ex: déjà `cancelled`), succès (event `ReservationConfirmed` dispatché).

**CancelReservation** : réservation introuvable, transition invalide, succès depuis `pending`
(event `ReservationCancelled` dispatché), succès depuis `confirmed` (event `ReservationCancelled` dispatché).

**GetReservation** : introuvable, succès.

### Fonctionnels (`#[Group('functional')]`)

- `POST /reservations` → 201, 404 (chambre introuvable), 404 (booker introuvable), 409 (chambre indisponible), 422 (pas de tarif), 422 (DTO invalide)
- `GET /reservations/{id}` → 200, 404
- `PATCH /reservations/{id}/confirm` → 200, 409, 404
- `DELETE /reservations/{id}` → 204, 404

---

## Limitations connues (résolues en Étape 2)

La chambre est vérifiée libre au moment de la création mais n'est pas bloquée dans Availability.
Deux créations concurrentes sur la même chambre et la même période peuvent toutes deux réussir.
Le hold avec expiration (Étape 2) supprime cette race condition en posant un `BlockedPeriod`
dès la création de la réservation.

La **Cancellation Policy** (fenêtre en heures avant check-in, remboursement intégral avant /
zéro après) est un concept métier identifié mais non implémenté dans cette étape. L'annulation
est traitée comme un simple changement d'état sans logique de remboursement.

> **Note pour l'étape Cancellation Policy :**
> - La politique appartient au contexte **Pricing** (configurée par l'opérateur aux côtés des tarifs).
> - À la création d'une réservation, les termes sont snapshotés sur l'agrégat `Reservation` comme
>   **Cancellation Terms** (immuables, indépendants des futures modifications de la politique chambre).
> - Le port `CancellationPolicyCheckerInterface` (Pricing → Reservation) devra retourner la fenêtre
>   en heures (ou `null` si aucune politique), pas un simple booléen.
> - Une chambre sans Cancellation Policy = annulation toujours remboursable (défaut).

Aucune authentification ni autorisation n'est appliquée sur les endpoints dans cette étape.
Tous les endpoints sont publics. La couche auth sera ajoutée dans une étape ultérieure.

`ConfirmReservation` est exposé comme endpoint HTTP sans vérification de paiement. En stage 1,
n'importe quel appelant peut confirmer une réservation indépendamment du résultat réel d'un
paiement. L'intégration avec un contexte Payment (webhook de confirmation) est prévue dans une
étape ultérieure.

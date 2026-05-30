# Reservation Read Endpoints — Design Spec

**Date:** 2026-05-30
**Status:** Approved

## Summary

Add two read endpoints to the Reservation bounded context:

1. `GET /reservations/{id}` — retrieve a single reservation by its ID
2. `GET /reservations?bookerId={uuid}&page=1&limit=20` — paginated list of reservations for a booker

## Endpoint 1 — `GET /reservations/{id}`

### Route

```
GET /reservations/{id}
requirements: id => UUID v4
```

### Behaviour

- Dispatches the existing `GetReservationQuery($id)` via `SyncQueryBusInterface`
- If the handler returns `null`, throws `ReservationNotFoundException` (already mapped to 404 problem+json in `config/services/exceptions.yaml`)
- Otherwise returns `200 JsonResponse` serialized with the existing `ReservationSerializer`

### Response body (200)

Same shape as the `POST /reservations` response:

```json
{
  "id": "uuid",
  "roomId": "uuid",
  "bookerId": "uuid",
  "checkIn": "2026-06-01",
  "checkOut": "2026-06-05",
  "totalPrice": 42000,
  "guestCount": 2,
  "status": "pending",
  "cancellationTerms": { "daysThreshold": 7 },
  "priceBreakdown": [
    { "date": "2026-06-01", "rateAmountCents": 10000, "discountPercent": null, "effectiveAmountCents": 10000 }
  ],
  "createdAt": "2026-05-30T10:00:00Z"
}
```

### Error responses

| Status | Condition |
|--------|-----------|
| 404 problem+json | Reservation not found |
| 404 | Non-UUID route param (Symfony routing) |

### New files

- `src/Reservation/UI/Http/Controller/GetReservation/GetReservationController.php`

### No changes needed

- `GetReservationQuery` / `GetReservationQueryHandler` — already exist
- `ReservationSerializer` — already exists
- `exceptions.yaml` — `ReservationNotFoundException` already mapped

---

## Endpoint 2 — `GET /reservations?bookerId={uuid}`

### Route

```
GET /reservations
query params: bookerId (UUID v4, required), page (int ≥ 1, default 1), limit (int 1–100, default 20)
```

### Behaviour

- Validates query params via `#[MapQueryString]` (422 on validation failure)
- Dispatches `ListBookerReservationsQuery(bookerId, page, limit)`
- Returns `200` with `data` array and `meta` pagination block
- Unknown `bookerId` returns `data: [], meta: { total: 0, ... }` (no 404)

### Response body (200)

```json
{
  "data": [ /* array of reservation objects, same shape as endpoint 1 */ ],
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 42,
    "totalPages": 3
  }
}
```

### Error responses

| Status | Condition |
|--------|-----------|
| 422 problem+json | `bookerId` absent, not a UUID, `page < 1`, `limit` out of 1–100 |

### New files

**Domain**
- `src/Reservation/Domain/Model/ReservationPage.php` — `readonly class` with `list<Reservation> $reservations` and `int $total`

**Domain port**
- `ReservationRepositoryInterface` — add `listByBooker(string $bookerId, int $page, int $limit): ReservationPage`

**Infrastructure**
- `ReservationRepository::listByBooker()` — two queries: `COUNT(*)` for total, then `SELECT ... LEFT JOIN reservation_guest ORDER BY created_at DESC LIMIT :limit OFFSET :offset`

**Application**
- `src/Reservation/Application/UseCase/ListBookerReservations/ListBookerReservationsQuery.php` — `SyncQueryInterface<ReservationPage>`, fields: `bookerId`, `page`, `limit`
- `src/Reservation/Application/UseCase/ListBookerReservations/ListBookerReservationsQueryHandler.php` — delegates to `$repository->listByBooker()`

**UI**
- `src/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsRequest.php` — `bookerId: UuidV4`, `page: int = 1` (≥ 1), `limit: int = 20` (1–100)
- `src/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsController.php`

---

## Testing

### `GET /reservations/{id}` — functional tests (`#[Group('functional')]`)

- Existing reservation → 200, all fields present and correct
- Unknown ID → 404 problem+json (`type: https://book.it/problems/reservation-not-found`)
- Non-UUID ID → 404 (Symfony routing)

### `GET /reservations?bookerId=` — functional tests (`#[Group('functional')]`)

- Booker with multiple reservations → 200, correct `data` and `meta`
- Page beyond last page → 200 with `data: []`, `meta.total` still correct
- Missing `bookerId` → 422 problem+json
- Non-UUID `bookerId` → 422 problem+json
- `limit > 100` → 422 problem+json
- `page < 1` → 422 problem+json

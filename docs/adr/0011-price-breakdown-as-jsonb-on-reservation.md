# ADR-0011 — Price Breakdown persisted as JSONB on the reservation table

The Price Breakdown (list of Night Prices captured at Reservation creation time) is stored as a `JSONB` column on the `reservation` table rather than in a separate `reservation_night_price` table.

## Considered Options

- **Separate `reservation_night_price` table** — one row per night, foreign key to `reservation`. Rejected: the breakdown is an immutable snapshot, not a queryable entity. No feature requires filtering or aggregating across nights from different reservations. A join adds complexity for purely read-side concerns, and the rows would never be updated or deleted independently of their parent reservation.

- **JSONB column on `reservation`** — the entire breakdown is serialized as a JSON array alongside the other reservation fields. Chosen: the breakdown is always read as a whole with its parent reservation; JSONB keeps that access atomic, schema-free, and without joins. PostgreSQL can still index into it if a future need arises.

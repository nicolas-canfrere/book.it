# Availability Hold stored in PostgreSQL, not Redis

When a Reservation is created, we place an Availability Hold on the Room for 15 minutes to prevent concurrent double-bookings while the Booker completes payment. We store this hold in PostgreSQL (table `availability_hold` with an `expires_at` column) rather than Redis, even though Redis is a natural fit for short-lived, TTL-managed data.

## Considered Options

**Redis** was the first instinct: native TTL, atomic `SET NX EX`, no cleanup job needed. The problem is that Redis is ephemeral — if it crashes, all active holds vanish silently. The fallback (checking pending Reservations in the Reservation context) would create a cross-context dependency: Availability querying Reservation data, which violates the bounded context boundary established in ADR-0005. Redis would also add operational complexity (a second persistence layer to back up, monitor, and reason about) for a dataset that is tiny (only currently-active holds).

**PostgreSQL** keeps the hold alongside the existing `blocked_period` table on the same `bookit` connection. The `hasOverlap` query extends naturally to filter active holds (`expires_at > now()`). ACID guarantees mean the availability check and hold creation are atomic within the same transaction — a concurrent request that slips through the check will fail at insert due to the overlap constraint. Cleanup of expired holds is handled by the same Symfony Messenger consumer that processes `ReservationExpired` events.

## Consequences

A periodic cleanup of orphaned holds (holds whose `ReservationExpired` message was lost) may be needed as a safety net. The `Availability Check` query must always filter holds by `expires_at > now()` to avoid treating stale holds as active blocks.

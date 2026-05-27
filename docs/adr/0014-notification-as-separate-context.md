# ADR 0014 — Notification as a separate bounded context

## Status
Accepted

## Context
When a Reservation is confirmed (payment authorised), the Booker must receive an email. The question was whether to inline this responsibility into the `Reservation` context (e.g. dispatch the email command directly from `ConfirmReservationCommandHandler`) or to isolate it in a dedicated `Notification` context.

Future delivery channels (SMS, push notifications) are explicitly anticipated but out of scope for this iteration.

## Decision
Create a `Notification` bounded context that owns all Booker-facing communications.

The `Notification` context listens to `ReservationConfirmed` via a synchronous event listener (Infrastructure layer) and dispatches a `SendBookingConfirmationEmail` async command over the existing AMQP transport. The handler fetches only what it needs (Booker contact details, stay period, total price) via ports backed by existing query handlers in `Booker` and `Reservation`.

No delivery record is persisted — failures are handled by the Messenger retry strategy (3 attempts, exponential backoff) and logged.

## Alternatives considered
**Inline in Reservation** — simpler initially, but forces `Reservation` to acquire a Mailer dependency and makes adding new channels (SMS, push) messy: either `Reservation` grows unrelated responsibilities or the inline code gets duplicated.

## Consequences
- `Notification` takes a dependency on `ReservationConfirmed` (cross-context event) and on `GetBookerQuery` / `GetReservationQuery` (cross-context queries via the sync query bus) — consistent with the pattern already used by `Availability` and `Reservation`.
- Adding a new delivery channel means adding a new handler inside `Notification` without touching any other context.
- `ReservationConfirmed` gains a `bookerId` field (previously omitted because `Availability` did not need it).

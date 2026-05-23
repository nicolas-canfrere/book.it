# Distinct `ReservationPaymentCancelled` event for payment abandonment

When a Booker abandons the payment flow, the payment provider sends a cancel webhook. This transitions the Reservation from `pending` to `cancelled` — but it is not the same cancellation as a Booker terminating a *confirmed* Reservation (the existing `ReservationCancelled` event). We introduced a dedicated `ReservationPaymentCancelled` event rather than reusing `ReservationCancelled`.

## Considered Options

**Reuse `ReservationCancelled`** and make the Availability listener tolerant: if no Blocked Period exists, fall back to deleting the Availability Hold instead. This keeps the event surface smaller but makes the listener defensive against a state that should be impossible — a confirmed Reservation has a Blocked Period, a pending one never does. The two cases have different Availability side-effects and different semantics (no refund logic applies to payment abandonment, unlike Booker cancellation which carries a `refundAmount`).

**Dedicated `ReservationPaymentCancelled` event** with its own Availability listener that deletes the Availability Hold — symmetric to `ReservationExpiredListener`. Each transition in the Reservation lifecycle maps to exactly one event; no listener needs to branch on state it shouldn't have to inspect.

## Decision

`ReservationPaymentCancelled` is the right name for a distinct concept. `ReservationCancelled` is a Booker-initiated act on a confirmed Reservation; `ReservationPaymentCancelled` is a provider-reported event on a pending one. Conflating them would force the Availability context to reason about Reservation state it does not own.

# ADR-0008 — Two distinct cancellation events: ReservationCancelled and ReservationRevoked

A Booker cancelling their own Reservation and an operator revoking a Reservation both result in `status = cancelled`, but they produce two distinct domain events: `ReservationCancelled` (Booker-initiated) and `ReservationRevoked` (Operator-initiated).

The alternative was a single `ReservationCancelled` event with a `cancelledBy` field discriminating the actor. We rejected it because the two acts carry fundamentally different semantics: a Cancellation is subject to Cancellation Terms (refund may be zero), while a Revocation always triggers a full refund. Downstream listeners — a payment service sending refunds, a notification service choosing the right email template — would have to inspect `cancelledBy` to branch their logic. Named events make that branching explicit at the event boundary, where it belongs, rather than buried inside each listener.

The cost is one additional event type to maintain. The benefit is that each listener subscribes only to what it cares about, and the intent of each act is self-documenting in the event log.

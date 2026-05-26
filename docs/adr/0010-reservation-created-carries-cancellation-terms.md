# ReservationCreated carries CancellationTerms snapshot

`ReservationCreated` includes the `CancellationTerms` snapshot even though no current subscriber needs it. The terms are a core part of the reservation contract — any future Billing, Notification, or Audit context that reacts to a new reservation will need to know the refund eligibility the Booker agreed to, without having to query back into the Reservation context. Omitting it would force every future subscriber to make a synchronous cross-context call at the moment of consumption, coupling them to the Reservation query model.

## Considered Options

- **Omit (YAGNI)** — include only what current subscribers need. Rejected: the terms are determined once at creation time and immutable; including them in the event is the only moment they can be propagated without a follow-up query.
- **Separate `CancellationTermsAttached` event** — rejected: it would fire immediately after `ReservationCreated` for every reservation, adding complexity with no benefit over embedding the data in the single creation event.

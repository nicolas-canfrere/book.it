# Room validates Hotel existence via a domain port

The Room context lives in `src/Room/` and references Hotels by id only — it never imports Hotel domain models. When registering a Room, the `AddRoomCommandHandler` must verify that the referenced Hotel exists before persisting the Room.

We chose to express this as a domain port `HotelExistsInterface` (declared in `Room/Domain/Port/`, implemented in `Room/Infrastructure/`) rather than trusting the caller to supply a valid Hotel id.

## Considered options

- **Trust the caller (no validation)** — the UI layer or command factory is responsible for passing a valid Hotel id. Simpler: no port, no infrastructure dependency. Rejected: a missing Hotel id would produce a Room with a dangling reference, silently violating the invariant "a Room belongs to exactly one Hotel."
- **Domain port `HotelExistsInterface`** — Room declares the need; infrastructure implements it (e.g. a query against the Hotel repository). Chosen: keeps the invariant enforced at the application boundary without coupling Room's domain to Hotel's domain model.

## Consequences

The Room context gains one infrastructure dependency on the Hotel persistence layer. Any future deletion of Hotels must account for orphaned Rooms — this decision makes that obligation explicit.

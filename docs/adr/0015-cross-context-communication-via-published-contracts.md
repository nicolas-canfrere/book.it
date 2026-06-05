# ADR 0015 — Cross-context communication via published contracts and event choreography

## Status
Accepted

## Context
Bounded contexts (`Hotel`, `Room`, `Availability`, `Pricing`, `Booker`, `Reservation`, `Payment`, `Notification`, `Search`) need to read data from, and react to facts produced by, other contexts. Historically a consumer imported the producer's internals: its `Query`/`Command` classes (Application layer) dispatched through the generic `SyncQueryBus`, with query handlers returning domain aggregates. Two coupling problems followed:

- **Reads** — consumers depended on producer use-case classes, and aggregates leaked across the boundary (e.g. `ReservationDetailsFetcher` importing `App\Reservation\Domain\Model\Reservation`).
- **Writes** — `Payment` drove `Reservation` directly by dispatching its `ConfirmReservationCommand`.

The layer-based deptrac configuration could not see this: `Infrastructure → Application` is allowed regardless of context, so nothing prevented reaching into a neighbour's internals.

## Decision
Apply *Published Language / Open Host Service*: a context may only depend on another context's **published contract**, never on its internals.

**Reads — published contract.** Each producer exposes a dedicated namespace `App\{Context}\Application\Contract\` containing:

- an interface (`{Thing}FinderInterface`, `{Thing}CheckerInterface`, …),
- a stable read DTO (`{Thing}View`) — never the aggregate.

The producer implements the contract in `App\{Context}\Infrastructure\Contract\` (e.g. `DoctrineBookerFinder`), owning the aggregate → View mapping. The consumer keeps its own domain port (`Domain\Port\`, expressing the need in its own language) and its Infrastructure adapter delegates to the producer's contract interface instead of dispatching the producer's internal query.

**Writes — event choreography.** A context never drives another context's use cases. The producer publishes a domain event stating a fact (`PaymentConfirmed`), and the interested context reacts with its own listener executing its own command (`Reservation` listens and confirms the reservation itself). See `domainevents.yaml` for the catalogue.

**Events live in the shared kernel.** Cross-context domain events are declared in `App\Shared\Domain\Event\` as pure readonly DTOs (e.g. `PaymentConfirmed` carries only `reservationId`). They are the other half of the published language: neither producer nor consumer ever imports the other's namespace — both depend only on `Shared`, which every layer already sees, so event-based choreography requires no extra deptrac rule. An event names a *fact in the past* about the producer; it carries identifiers and stable primitives, never aggregates.

**Enforcement — deptrac per context.** A second deptrac analysis (`deptrac-contexts.yaml`, run by `make deptrac` alongside `deptrac.yaml`) defines one layer per context plus one `*Contract` layer per published surface. Cross-context dependencies are only allowed towards `*Contract` layers, through an explicit allowlist reflecting actual consumption. Contract classes themselves depend on nothing (pure interfaces/DTOs). It is a separate file because deptrac supports a single ruleset per file: mixing the technical-layer dimension and the context dimension would either produce false positives on the layer-pair cross-product or require ~36 combined layers.

## Alternatives considered
- **Keep the sync query bus for cross-context reads** — generic and already in place, but unenforceable (any context can ask anything), untyped at the boundary, and it normalised aggregate leakage. Rejected.
- **Contracts in `App\Shared\Contract\*`** — simpler deptrac story (everyone already sees `Shared`), but inflates the shared kernel and dilutes ownership. Rejected: the producer owns its published surface.
- **Synchronous command contract for writes (`Payment → Reservation`)** — possible, but the event idiom was already in place and eventual consistency between Payment and Reservation is acceptable. Rejected in favour of choreography.

## Consequences
- Aggregates no longer cross context boundaries; producers can refactor internals freely as long as the `Contract` namespace stays stable. A change to a `*View` — or to an event payload in `Shared\Domain\Event\` — is a **breaking change for consumers** and must be treated like a public API change.
- `SyncQueryBus` remains for intra-context use only.
- Adding a consumer of an existing contract, or publishing a new contract, requires updating the allowlist in `deptrac-contexts.yaml` — this is intentional friction making new coupling explicit and reviewable.
- Supersedes the cross-context query pattern described in the consequences of ADR 0014 (`Notification` now consumes `BookerFinderInterface` / `ReservationFinderInterface` instead of `GetBookerQuery` / `GetReservationQuery`).
- Writes between contexts are eventually consistent (accepted trade-off, e.g. Payment confirmation → Reservation confirmation).

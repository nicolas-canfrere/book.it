# ADR-0005 — Availability as a separate context with opaque, immutable blocks

## Status
Accepted

## Context
We need to manage Room availability over time in preparation for a future Reservation context. Two structural decisions needed to be made: (1) where does availability live in the codebase, and (2) what does a "block" look like?

## Decision

### Separate `Availability` context
Availability lives in `src/Availability/`, not inside `src/Room/`. Room describes a physical space (number, floor). Availability manages its calendar. These evolve independently: Room registration has no reason to know about blocked periods, and the future Reservation context will query Availability directly, not Room.

### Opaque blocks — no reason field
A Blocked Period carries no reason (no type such as "maintenance", "reservation", "closure"). The *why* belongs to the context that creates the block (e.g., Reservation will create its own block when confirming a booking). This context is only the authority on *whether* a Room is available, not *why* it isn't.

### Immutable blocks
A Blocked Period cannot be modified after creation. To correct a block, the operator deletes it and creates a new one. This avoids update-time overlap validation complexity and keeps the model simple.

## Consequences
- The Reservation context will interact with Availability via a port (not direct coupling).
- If a future need arises to distinguish block types (e.g., to allow cancellation of reservation-blocks only), a reason or type field will need to be introduced — this will require a migration.
- Operators cannot partially adjust a block in one operation; delete-and-recreate is the workflow.

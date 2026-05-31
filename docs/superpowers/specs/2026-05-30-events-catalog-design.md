# Events Catalog — Design Spec

**Date:** 2026-05-30
**Status:** Approved

## Objective

Generate a single YAML file documenting all domain events and their listeners, usable by both internal developers and external API consumers.

## Output File

**Path:** `domainevents.yaml` (project root, alongside `openapi.yaml` and `asyncapi.yaml`)

**Committed to git:** yes — treated like `openapi.yaml`, regenerated on demand.

### Format

```yaml
generated_at: "2026-05-30T14:00:00+00:00"
events:
  ReservationCreated:
    class: App\Shared\Domain\Event\ReservationCreated
    properties:
      reservationId: string
      roomId: string
      bookerId: string
      checkIn: DateTimeImmutable
      checkOut: DateTimeImmutable
      totalPrice: int
      cancellationTermsDaysThreshold: "int|null"
      priceBreakdown: array
    listeners:
      - context: Availability
        class: App\Availability\Infrastructure\EventListener\ReservationCreatedListener
  ReservationConfirmed:
    class: App\Shared\Domain\Event\ReservationConfirmed
    properties: { ... }
    listeners:
      - context: Availability
        class: App\Availability\Infrastructure\EventListener\ReservationConfirmedListener
      - context: Notification
        class: App\Notification\Infrastructure\EventListener\ReservationConfirmedListener
```

Events are keyed by short class name. Properties are extracted from the constructor signature. Context is derived from the listener's namespace second segment (e.g. `App\Availability\...` → `Availability`).

## Command

**Class:** `App\Shared\UI\Console\GenerateEventsCatalogCommand`
**Name:** `app:events:catalog`
**Location:** `src/Shared/UI/Console/`

### Algorithm

1. Call `EventDispatcherInterface::getListeners()` to get all registered listeners grouped by event class name.
2. Filter to listeners whose event is a domain event (i.e. under `App\Shared\Domain\Event\`).
3. For each event class, use PHP reflection on its constructor to extract property names and types.
4. For each listener callable, extract:
   - `class`: the fully-qualified listener class name
   - `context`: second namespace segment of the listener class (e.g. `Availability`)
5. Build the catalog array and write it with `Symfony\Component\Yaml\Yaml::dump()` to `domainevents.yaml` at the project root.
6. Output the path and event count to the console.

### Dependencies

- `Symfony\Component\EventDispatcher\EventDispatcherInterface`
- `Symfony\Component\Yaml\Yaml`
- `Symfony\Component\Console\Attribute\AsCommand`

## Makefile Target

```makefile
events: ## Generate domainevents.yaml
	$(CONSOLE) app:events:catalog
```

Follows the same pattern as `make openapi`.

## Testing

**One functional test** using `KernelTestCase`, group `functional`.

**File:** `tests/Shared/UI/Console/GenerateEventsCatalogCommandTest.php`

The test:
1. Boots the kernel and runs the command via `CommandTester`.
2. Asserts the command exits with code 0.
3. Reads the generated `domainevents.yaml`.
4. Asserts all 5 domain events are present (`ReservationCreated`, `ReservationConfirmed`, `ReservationExpired`, `ReservationCheckedOut`, `ReservationPaymentCancelled`).
5. Asserts each event has at least one listener entry with `context` and `class` fields.

No unit tests: the command is orchestration-only; integration is where the value is.

## Constraints

- The command lives in `Shared/UI/Console/` — transversal to all contexts, consistent with the shared UI layer.
- No new PHP attributes on event classes — metadata comes entirely from reflection and the event dispatcher.
- `domainevents.yaml` is not gitignored — it is committed and regenerated when events or listeners change.
- `make events` must be run after adding or removing any event or listener (same discipline as `make openapi`).

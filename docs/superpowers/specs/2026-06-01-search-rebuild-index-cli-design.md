# Search Index Rebuild CLI — Design

**Date:** 2026-06-01
**Status:** Approved

## Context

The search read model (`hotel_room_types`, `room_index`, `unavailable_periods`) is populated incrementally via domain event listeners. There is currently no way to rebuild these tables from scratch — necessary after data loss, a botched migration, or bootstrapping a new environment.

## Goal

Add a Symfony console command `search:rebuild-index` that truncates the three search tables and repopulates them entirely from the source databases.

## Architecture

**New file:** `src/Search/UI/Console/RebuildSearchIndexCommand.php`

Layer: `Search/UI/Console` — consistent with `Shared/UI/Console/GenerateEventsCatalogCommand.php`.

No new application service. Reading source data for a projection rebuild is an infrastructure concern, not a domain rule. Logic stays inline in the command.

## Injected dependencies

| Dependency | Used for |
|---|---|
| `HotelRoomTypeWriter` | upsert room types + update base rates |
| `RoomIndexWriter` | upsert rooms |
| `UnavailablePeriodWriter` | insert holds and blocked periods |
| `Connection $roomConnection` | SELECT all room types and rooms |
| `Connection $pricingConnection` | SELECT all base rates |
| `Connection $availabilityConnection` | SELECT all holds and blocked periods |

`hotelConnection` is not injected directly — `HotelRoomTypeWriter::upsertRoomType` already handles the hotel JOIN internally.

## Execution sequence

1. **TRUNCATE** `unavailable_periods`, `room_index`, `hotel_room_types` (order respects FK dependencies)
2. **Rebuild `hotel_room_types`** — `SELECT id, hotel_id, name, guest_capacity, bed_composition FROM room_type` → `HotelRoomTypeWriter::upsertRoomType` per row
3. **Rebuild `room_index`** — `SELECT id, room_type_id, hotel_id FROM room` → `RoomIndexWriter::upsert` per row
4. **Apply base rates** — `SELECT room_id, amount_cents FROM base_rate` (pricingConnection) → `HotelRoomTypeWriter::updateBaseRateByRoom` per row
5. **Rebuild holds** — `SELECT id, room_id, check_in, check_out FROM hold WHERE expires_at > NOW()` → `UnavailablePeriodWriter::add` per row
6. **Rebuild blocked periods** — `SELECT id, room_id, check_in, check_out FROM blocked_period` → `UnavailablePeriodWriter::add` per row

## CLI interface

```
bin/console search:rebuild-index
```

No options. The operation is always a full truncate+rebuild.

**Output:**
```
Rebuilding search index...
[1/6] Truncating search tables...      done
[2/6] Rebuilding hotel_room_types...   42 room types inserted
[3/6] Rebuilding room_index...         187 rooms inserted
[4/6] Applying base rates...           38 rates applied
[5/6] Rebuilding holds...              12 holds inserted
[6/6] Rebuilding blocked periods...    5 periods inserted
Done.
```

## Error handling

- Any DBAL exception causes the command to exit with `Command::FAILURE` and prints the exception message.
- No global transaction. A failed mid-rebuild leaves partial data — acceptable for a maintenance command (re-run to fix).
- The command warns via `$output->writeln` at the start that search results will be empty during execution.

## Out of scope

- `--dry-run` option
- Per-hotel or per-date filtering
- Atomicity / zero-downtime rebuild

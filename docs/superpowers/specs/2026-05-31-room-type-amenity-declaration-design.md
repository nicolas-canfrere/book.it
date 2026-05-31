# Room Type Amenity Declaration — Design Spec

**Date:** 2026-05-31
**Status:** Approved

## Context

Room Amenities are equipment belonging to a lodging unit (tv, wifi, air_conditioning, minibar, etc.). Per the domain decisions recorded in the Obsidian note "Équipements — Hotel et Room Type" and ADR-0015:

- Room Amenities are carried by the **Room Type**, not the individual Room — all rooms of the same type share the same amenities.
- Amenities are optional at Room Type creation and declared/updated afterwards via a dedicated **Room Type Amenity Declaration** operation.
- Modification = full list replacement. No add/remove single-item operations.
- A separate `RoomAmenity` enum exists (distinct from `HotelAmenity`). No value belongs to both.

## Endpoint

```
PATCH /room-types/{id}/amenities
```

Body: `{ "amenities": ["wifi", "tv", "minibar"] }`

Responses: 204 No Content | 404 Not Found | 422 Unprocessable Entity

## Domain

### RoomAmenity enum

File: `src/Room/Domain/ValueObject/RoomAmenity.php`

Backed string enum with 30 cases:

| Category | Cases |
|---|---|
| Connectivity | `wifi`, `ethernet`, `tv`, `telephone` |
| Climate | `air_conditioning`, `heating`, `ceiling_fan`, `fireplace` |
| Bathroom | `bathtub`, `shower`, `jacuzzi`, `hairdryer`, `bidet` |
| Kitchen | `minibar`, `kettle`, `coffee_machine`, `microwave`, `kitchenette`, `refrigerator` |
| Workspace | `desk`, `safe` |
| Bedding & storage | `blackout_curtains`, `wardrobe` |
| Misc | `balcony`, `terrace`, `iron`, `washing_machine` |

Includes a `values(): string[]` static helper (same pattern as `HotelAmenity`).

Note: `jacuzzi` exists at both levels — common jacuzzi (HotelAmenity) vs private in-room jacuzzi (RoomAmenity). They are independent.

### RoomType model

Add to `src/Room/Domain/Model/RoomType.php`:

- Constructor parameter: `public array $amenities = []` (`@param array<RoomAmenity>`)
- Method: `withAmenities(array $amenities): self` — returns a new instance with the updated amenities, all other fields unchanged.

## Application

### DeclareRoomTypeAmenitiesCommand

File: `src/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommand.php`

```
SyncCommandInterface
- roomTypeId: string
- amenities: string[]
```

### DeclareRoomTypeAmenitiesCommandHandler

File: `src/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommandHandler.php`

```
SyncCommandHandlerInterface
1. Load RoomType via RoomTypeRepositoryInterface::get($command->roomTypeId)
2. Throw RoomTypeNotFoundException if null
3. Map strings to RoomAmenity::from(...)
4. Save via RoomTypeRepositoryInterface::save($roomType->withAmenities($amenities))
```

`RoomTypeRepositoryInterface` gains a new `save(RoomType $roomType): void` method for the amenity-only update (alternative: extend the existing `update()` — see Infrastructure section).

## Infrastructure

### Migration

```sql
ALTER TABLE room.room_type ADD COLUMN amenities text[] NOT NULL DEFAULT '{}';
```

### RoomTypeRepository

File: `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeRepository.php`

Changes:
- `add()`: include `'amenities' => $this->serializeAmenities($roomType->amenities)`
- `update()`: include `'amenities' => $this->serializeAmenities($roomType->amenities)` (also covers the `save()` case — `save()` can delegate to `update()`, or be a dedicated method updating only the `amenities` column)
- `get()` SELECT: include `amenities` column
- `list()` SELECT: include `amenities` column
- `hydrate()`: pass `$this->parseAmenities($row['amenities'])` as the last constructor argument

Private helpers (identical pattern to `HotelRepository`):

```php
/** @param array<RoomAmenity> $amenities */
private function serializeAmenities(array $amenities): string
// returns '{}' for empty, '{wifi,tv,...}' otherwise

/** @return array<RoomAmenity> */
private function parseAmenities(string $raw): array
// parses PostgreSQL text[] literal, maps to RoomAmenity::from(...)
```

## UI

### DeclareRoomTypeAmenitiesRequest

File: `src/Room/UI/Http/Controller/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesRequest.php`

```php
public array $amenities = []
// Assert\All(Assert\Choice(callback: [RoomAmenity::class, 'values']))
// Assert\Unique
// OA\Property(type: 'array', items: OA\Items(type: 'string'), example: ['wifi', 'tv', 'minibar'])
```

### DeclareRoomTypeAmenitiesController

File: `src/Room/UI/Http/Controller/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesController.php`

```
#[Route('/room-types/{id}/amenities', name: 'room_type_declare_amenities', requirements: ['id' => Requirement::UUID_V4], methods: ['PATCH'])]
#[OA\Patch(summary: 'Declare or replace the Room Type Amenity list', tags: ['Room Types'])]
```

Responses documented: 204 No Content, 404 (RoomType not found), 422 (validation error).

### Exception mapping

Add to `config/services/exceptions.yaml`:

```yaml
App\Room\Domain\Exception\RoomTypeNotFoundException:
    type: 'https://book.it/problems/room-type-not-found'
    title: 'Room Type Not Found'
    status: 404
```

(Only add if not already mapped — verify before implementing.)

## Tests

### Unit — DeclareRoomTypeAmenitiesCommandHandler

Group: `unit`

| Scenario | Expected |
|---|---|
| Room type not found | throws `RoomTypeNotFoundException` |
| Valid amenity list | repo receives `RoomType` with correct `RoomAmenity[]` |
| Empty list | repo receives `RoomType` with `amenities = []` |

### Functional — PATCH /room-types/{id}/amenities

Group: `functional`

| Scenario | Expected status |
|---|---|
| Valid amenity list | 204 |
| Unknown room type id | 404 |
| Unknown amenity value | 422 |
| Duplicate amenity value | 422 |
| Empty list | 204 |

## Out of Scope

- Filtering the Room Type Catalogue by `RoomAmenity` (tracked separately in ADR-0015 context)
- Individual add/remove operations — full list replacement only
- Amenities on individual Rooms — always on the Room Type

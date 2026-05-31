# Hotel Amenity Declaration — Design

**Date:** 2026-05-31
**Scope:** Hotel context only — write side (declaration) + read side (filtering, serialization)

---

## Summary

Add the ability for an operator to declare or replace the full list of Hotel Amenities on a Hotel. Amenities are optional at registration and replaceable at any time via a `PATCH /hotels/{id}/amenities` endpoint. The Hotel Catalogue becomes filterable by amenity (AND semantics). Hotel responses include the amenity list.

---

## Domain

**`HotelAmenity`** — new `BackedEnum` (string) in `Hotel\Domain\ValueObject\HotelAmenity.php`.

Values (28 total, drawn from the domain glossary):

| Category     | Values                                                                       |
|--------------|------------------------------------------------------------------------------|
| Services     | `concierge`, `room_service`, `laundry`, `airport_shuttle`, `luggage_storage` |
| Restauration | `restaurant`, `bar`                                                          |
| Bien-être    | `pool`, `spa`, `sauna`, `gym`, `jacuzzi`                                     |
| Famille      | `playground`, `kids_club`, `babysitting`                                     |
| Mobilité     | `parking`, `ev_charging`, `elevator`, `wheelchair_accessible`                |
| Réunions     | `conference_room`, `business_center`                                         |
| Divers       | `pets_allowed`, `garden`, `terrace`, `beach_access`, `ski_access`            |

Static helper: `HotelAmenity::values(): string[]` — returns `array_column(HotelAmenity::cases(), 'value')`.

**`Hotel`** model acquires:
- `public array $amenities = []` (typed `array<HotelAmenity>`) — optional, defaults to empty
- `withAmenities(array $amenities): self` — returns new instance with all other fields preserved, same pattern as `withStarRating`

No new domain exceptions. An empty amenity list is valid.

---

## Application

New use case directory: `Hotel\Application\UseCase\DeclareHotelAmenities\`

**`DeclareHotelAmenitiesCommand`** implements `SyncCommandInterface`:
```
hotelId: string
amenities: string[]   // raw strings — enum conversion in handler
```

**`DeclareHotelAmenitiesCommandHandler`** implements `SyncCommandHandlerInterface`:
1. Load hotel via `HotelRepositoryInterface::get($command->hotelId)`
2. Throw `HotelNotFoundException` if null
3. Convert `string[]` → `HotelAmenity[]` via `HotelAmenity::from()`
4. Call `$hotel->withAmenities($amenities)` then `$repository->save($hotel)`

`HotelRepositoryInterface::list()` signature extended: `?array $amenities = null` added as last parameter.

---

## Infrastructure

### Migration

```sql
ALTER TABLE hotel.hotel ADD COLUMN amenities text[] NOT NULL DEFAULT '{}';
```

### HotelRepository changes

| Method                 | Change                                                                                                       |
|------------------------|--------------------------------------------------------------------------------------------------------------|
| `add()`                | Insert `amenities = '{}'` (empty at registration)                                                            |
| `save()`               | Add `amenities` to UPDATE via `serializeAmenities()`                                                         |
| `get()`                | SELECT includes `amenities`; hydrate parses `{pool,gym}` → `HotelAmenity[]`                                  |
| `list()`               | If `$amenities` non-null: add `amenities @> :amenities` to WHERE (PostgreSQL array-contains — AND semantics) |
| `hydrate()`            | Parse PostgreSQL array string `{pool,gym}` → `HotelAmenity[]`                                                |
| `serializeAmenities()` | Private — formats `HotelAmenity[]` → `{pool,gym,...}` string for DBAL                                        |

PostgreSQL `@>` operator on `text[]` provides native AND semantics: a hotel matches only if its `amenities` column contains all requested values.

---

## UI

### New endpoint

`PATCH /hotels/{id}/amenities` — replaces the full amenity list.

**`DeclareHotelAmenitiesRequest`**:
```php
amenities: string[]
  - #[Assert\All([new Assert\Choice(choices: HotelAmenity::values())])]
  - #[Assert\Unique]
```

**`DeclareHotelAmenitiesController`**:
- Dispatches `DeclareHotelAmenitiesCommand` via `SyncCommandBusInterface`
- Returns `204 No Content` on success

Responses:
- `204` — amenities replaced
- `404` — hotel not found
- `422` — unknown amenity value or duplicate entries

### Updated endpoints

**`ListHotelsRequest`**: add `?array $amenities = null` with `#[Assert\All([new Assert\Choice(choices: HotelAmenity::values())])]`. Query param: `amenities[]=pool&amenities[]=gym`.

**`HotelSerializer`** (GetHotel) and **`HotelCatalogueSerializer`** (ListHotels): add `amenities` field — array of strings (enum values).

**`ListHotelsQueryHandler`** and **`ListHotelsQuery`**: forward the `amenities` filter down to `HotelRepositoryInterface::list()`.

---

## Error Handling

No new exception mappings needed in `exceptions.yaml` — `HotelNotFoundException` is already mapped to 404. Unknown enum values and duplicates are caught at the `#[MapRequestPayload]` validation layer (422).

---

## Testing

### Unit (`#[Group('unit')]`)

`DeclareHotelAmenitiesCommandHandlerTest`:
- Hotel not found → `HotelNotFoundException`
- Valid list → `save()` called with correct `HotelAmenity[]`
- Empty list → `save()` called with empty array

### Integration (`#[Group('integration')]`)

`HotelRepositoryTest`:
- `save()` persists amenities, `get()` reloads them correctly
- `list()` with amenity filter returns only matching hotels (AND: hotel with pool+gym matches filter `[pool]`, does not match filter `[pool, spa]`)

### Functional (`#[Group('functional')]`)

`DeclareHotelAmenitiesControllerTest`:
- `204` with valid list
- `204` with empty list (removes all amenities)
- `404` for unknown hotel id
- `422` for unknown amenity value
- `422` for duplicate values in the list

`ListHotelsControllerTest`:
- `?amenities[]=pool&amenities[]=gym` returns only hotels with both amenities
- `?amenities[]=unknown` returns `422`

`GetHotelControllerTest`:
- Response includes `amenities` field (array of strings)

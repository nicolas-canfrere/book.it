# Search Context — Read Model & Events (Iteration 1)

## Scope

First iteration toward a hotel search bar (`GET /search?city=&checkIn=&checkOut=&guests=`).

This iteration covers:
- PostgreSQL schema for the `Search` read model (3 tables)
- Domain events dispatched from `Hotel` and `Room` command handlers

**Not in this iteration:** Search listeners/projectors, Availability events, Pricing events, the `GET /search` endpoint.

## Database Schema

One Doctrine migration creates 3 tables.

### `search_hotel_room_types`

Denormalized projection, one row per (hotel × room type). Primary key on `room_type_id` (globally unique, natural granularity for Room events).

| Column | Type | Notes |
|---|---|---|
| `room_type_id` | UUID PK | |
| `hotel_id` | UUID NOT NULL | |
| `hotel_name` | VARCHAR(255) NOT NULL | |
| `city` | VARCHAR(255) NOT NULL | |
| `country` | VARCHAR(255) NOT NULL | |
| `star_rating` | SMALLINT NULL | null = unclassified |
| `hotel_amenities` | JSONB NOT NULL DEFAULT '[]' | |
| `room_type_name` | VARCHAR(255) NOT NULL | |
| `guest_capacity` | SMALLINT NOT NULL | |
| `bed_composition` | JSONB NOT NULL | |
| `room_amenities` | JSONB NOT NULL DEFAULT '[]' | |
| `base_price_cents` | INT NULL | filled by Pricing events (future) |

### `search_room_index`

Index of physical rooms. Fed by `RoomRegistered`. Used to join against `search_unavailable_periods` at query time.

| Column | Type | Notes |
|---|---|---|
| `room_id` | UUID PK | |
| `room_type_id` | UUID NOT NULL FK → search_hotel_room_types | |
| `hotel_id` | UUID NOT NULL | |

### `search_unavailable_periods`

One row per blocked period per physical room. Fed by Availability events (future iteration).

| Column | Type | Notes |
|---|---|---|
| `id` | UUID PK | |
| `room_id` | UUID NOT NULL FK → search_room_index | |
| `room_type_id` | UUID NOT NULL | denormalized for query convenience |
| `hotel_id` | UUID NOT NULL | denormalized for query convenience |
| `period` | DATERANGE NOT NULL | GiST index |

The `&&` operator on `daterange` with a GiST index is the intended use case for this PostgreSQL type.

### Future search query shape

```sql
SELECT s.*
FROM search_hotel_room_types s
WHERE s.city = :city
  AND s.guest_capacity >= :guests
  AND (
    SELECT COUNT(*)
    FROM search_room_index r
    WHERE r.room_type_id = s.room_type_id
      AND NOT EXISTS (
        SELECT 1 FROM search_unavailable_periods u
        WHERE u.room_id = r.room_id
          AND u.period && daterange(:checkIn, :checkOut)
      )
  ) > 0
```

## Domain Events

All events are `readonly` classes in `Shared\Domain\Event`, following the existing pattern (no base class or interface).

### Hotel events

**`HotelRegistered`**
```
hotelId: string
name: string
city: string
country: string
starRating: ?int
createdAt: DateTimeImmutable
```

**`StarRatingClassified`**
```
hotelId: string
starRating: ?int   -- null = rating removed
```

**`HotelAmenityDeclared`**
```
hotelId: string
amenities: string[]  -- enum values from HotelAmenity
```

### Room events

**`RoomTypeRegistered`**
```
roomTypeId: string
hotelId: string
name: string
guestCapacity: int
bedComposition: array   -- serialized from BedComposition
createdAt: DateTimeImmutable
```

**`RoomTypeUpdated`**
```
roomTypeId: string
hotelId: string
name: string
guestCapacity: int
bedComposition: array
```
Note: `surfaceM2` and `isAccessible` are intentionally excluded — not projected in the read model.

**`RoomTypeAmenityDeclared`**
```
roomTypeId: string
hotelId: string
amenities: string[]  -- enum values from RoomAmenity
```

**`RoomTypeDeleted`**
```
roomTypeId: string
hotelId: string
```

**`RoomRegistered`**
```
roomId: string
hotelId: string
roomTypeId: string
```

## Event Dispatch

Each command handler receives `Psr\EventDispatcher\EventDispatcherInterface` and dispatches after the repository call. Pattern is identical to `ConfirmReservationCommandHandler`.

| Handler | Event dispatched |
|---|---|
| `RegisterHotelCommandHandler` | `HotelRegistered` |
| `ClassifyHotelCommandHandler` | `StarRatingClassified` |
| `DeclareHotelAmenitiesCommandHandler` | `HotelAmenityDeclared` |
| `RegisterRoomTypeCommandHandler` | `RoomTypeRegistered` |
| `UpdateRoomTypeCommandHandler` | `RoomTypeUpdated` |
| `DeclareRoomTypeAmenitiesCommandHandler` | `RoomTypeAmenityDeclared` |
| `DeleteRoomTypeCommandHandler` | `RoomTypeDeleted` |
| `RegisterRoomCommandHandler` | `RoomRegistered` |

## Testing

One unit test per modified command handler. Each test:
- Mocks `EventDispatcherInterface`
- Asserts `dispatch()` is called once with the correct event and field values

No integration tests for Search listeners in this iteration (listeners don't exist yet).

## Tracking — What's Next

| Item | Status |
|---|---|
| Search DB schema (3 tables) | **this iteration** |
| Hotel events + dispatch (3 events, 3 handlers) | **this iteration** |
| Room events + dispatch (5 events, 5 handlers) | **this iteration** |
| Unit tests (8 handlers) | **this iteration** |
| Availability events (`BlockedPeriodCreated/Deleted`, `AvailabilityHoldCreated/Expired`) | next |
| `App\Search\` context skeleton (YAML, deptrac config) | next |
| Search projectors/listeners (populate the 3 tables) | next |
| `GET /search` query handler + endpoint | next |
| Pricing events (`BaseRateSet`, `RatePeriodCreated/Deleted`) | future (optional) |
| `RoomReassigned` event + dispatch | future (when use case is implemented) |

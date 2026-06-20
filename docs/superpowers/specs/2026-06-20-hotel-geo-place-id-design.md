# Hotel — propagate GeoNames `geoPlaceId`

## Context

The Obsidian note "Référentiel géographique des villes" (vault `tech`, `BookIt/Geo`) lays out a 7-step plan to replace free-text city matching with a GeoNames-backed referential. Steps 1-3 are done: `geo.geo_place` table imported, `Geo` bounded context created, `GET /geo/places` fuzzy autocomplete endpoint live.

This spec covers **step 4 only**: propagate the GeoNames identifier to the `Hotel` aggregate, captured via the same autocomplete endpoint in the back office, so hotel creation and (later) availability search share one consistent referential. Steps 5-7 (Search read model, availability filter by id, public front) are out of scope.

## Decisions

- `Address.city` (free text) is **kept as-is**. `geoPlaceId` is an **additive, optional** disambiguator — no breaking change to `RegisterHotel`.
- `geoPlaceId` lives on the `Address` value object (it disambiguates a location, alongside street/postal code/city/country), not directly on `Hotel`.
- When a `geoPlaceId` is supplied at registration, the Hotel context **validates** it against the Geo context via a published contract — guaranteeing referential consistency, per the note's stated goal.
- Naming: the PHP property/parameter/argument is `geoPlaceId` everywhere (not `geonamesId`), matching the existing `GeoPlaceId` value object. JSON keys and the SQL column follow the same name (`geoPlaceId` / `geo_place_id`).

## Design

### Domain (`App\Hotel\Domain`)

- `Model\Address`: add `public ?GeoPlaceId $geoPlaceId = null` (type `App\Shared\Domain\ValueObject\GeoPlaceId`, already shared cross-context).
- `Exception\InvalidGeoPlaceException`: thrown when a supplied `geoPlaceId` doesn't match any known `GeoPlace`.
- `Port\GeoPlaceCheckerInterface`: `exists(GeoPlaceId $id): bool`.

### Cross-context contract (Geo → Hotel)

Per ADR 0015, Hotel cannot import Geo internals. Geo publishes a contract:

- `App\Geo\Application\Contract\GeoPlaceCheckerInterface::exists(GeoPlaceId $id): bool`
- `App\Geo\Infrastructure\Contract\DbalGeoPlaceChecker` implements it — queries `geo.geo_place` via `geoConnection` (`SELECT 1 FROM geo.geo_place WHERE geoname_id = :id`).
- `App\Hotel\Infrastructure\Service\GeoPlaceChecker` implements Hotel's domain port, delegating to Geo's published contract (mirrors the existing `DoctrineHotelFinder` pattern).
- `deptrac-contexts.yaml`: add a `GeoContract` layer (`App\Geo\Application\Contract\.*`) and allow `Hotel` to depend on it, alongside the existing `Geo` layer rule.

### Application (`RegisterHotel` use case)

- `RegisterHotelCommandFactory::create()`: add `?int $geoPlaceId = null` param; builds `new Address(..., geoPlaceId: null !== $geoPlaceId ? new GeoPlaceId((string) $geoPlaceId) : null)`.
- `RegisterHotelCommandHandler`: after building `Address`, if `$command->address->geoPlaceId !== null`, call `GeoPlaceCheckerInterface::exists()`; throw `InvalidGeoPlaceException` if false. Check happens before persistence.
- `config/services/exceptions.yaml`: map `InvalidGeoPlaceException` → 422, type `https://book.it/problems/invalid-geo-place`, title `Invalid Geo Place`.

### UI (back office)

- `RegisterHotelRequest`: add `?int $geoPlaceId = null` (`#[Assert\Positive]`, `#[OA\Property(type: 'integer', example: 2988507, nullable: true)]`).
- `RegisterHotelController`: pass `$request->geoPlaceId` to the factory; add `geoPlaceId` to the OA response schema (nullable integer).
- `HotelSerializer`: add `geoPlaceId` (nullable int) to the serialized payload — covers `GetHotelController`, `RegisterHotelController`, and `ListHotelsController` (via `HotelCatalogueSerializer`, which reuses `HotelSerializer`).

The back-office UI itself (consuming `GET /geo/places` to populate the field before submitting `geoPlaceId`) is a frontend concern outside this PHP repo's scope — not built here.

### Persistence

- New migration: `ALTER TABLE hotel.hotel ADD COLUMN geo_place_id BIGINT NULL`. No cross-schema foreign key — contexts stay decoupled at the DB level; consistency is enforced at the application layer via the contract check above.
- `HotelRepository` (Doctrine): persist/read `geo_place_id` in `add`, `save`, `get`, and `list`/`hydrate`.

## Testing

- Unit: `Address` construction with/without `geoPlaceId`; `RegisterHotelCommandHandler` throws `InvalidGeoPlaceException` when the checker returns false, persists normally when true or null.
- Integration: `DbalGeoPlaceChecker::exists()` against a seeded `geo.geo_place` row; `HotelRepository` round-trips `geoPlaceId` (including null).
- Functional: `POST /hotels` with a valid/invalid/absent `geoPlaceId` → 201 / 422 / 201 respectively; `GET /hotels/{id}` and `GET /hotels` include `geoPlaceId` in the response.

## Out of scope

- Search context read model, availability filter by `geoPlaceId`, public front autocomplete (steps 5-7 of the note) — separate future work.
- Backfilling `geoPlaceId` for existing hotels.

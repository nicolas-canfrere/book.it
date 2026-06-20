# Search — Filter Availability Search by Geo Place Id Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the equality filter on the free-text `city` field in the availability search endpoint (`GET /api/v1/search`) with a filter on the GeoNames `geoPlaceId`, evolving the API contract so the front sends a disambiguated place id (selected via the Geo Place Search autocomplete) instead of relying on `city` for filtering.

**Architecture:** `search.hotel_room_types` already carries a nullable `geo_place_id` column, denormalized from `Hotel.Address.geoPlaceId` (step 5 of the referential plan, already shipped). This plan is step 6: thread `geoPlaceId` through the existing `SearchAvailableRoomTypes` use case (Request → Query → Handler → Finder → SQL), making it the sole filtering criterion. `city` stays in the HTTP request as a required field (the front still sends the raw text typed by the user, per the transitional contract decided with the product owner) but is no longer used in the `WHERE` clause — it is not even threaded past the controller.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL, PostgreSQL 16.

## Global Constraints

- `geoPlaceId` is **mandatory** in the HTTP request — there is no fallback to a `city` equality filter.
- `city` stays **mandatory** in the HTTP request (transitional — front still sends it as free text) but is **not used for filtering** and is **not threaded** into the Query/Handler/Finder/SQL layers.
- `Address.geoPlaceId` is optional on `Hotel` (see CONTEXT.md) — hotels with no `geo_place_id` set will no longer appear in any search result once this ships, since the filter is now exclusive on `geo_place_id`. This is an accepted consequence of the product decision, not a bug to work around.
- Follow `App\{Context}\{Layer}` architecture: `Domain\Port` for the finder interface, `Infrastructure\Finder` for the DBAL implementation, `Application\UseCase\SearchAvailableRoomTypes` for Query/Handler, `UI\Http\Controller\SearchAvailableRoomTypes` for Controller/Request.
- Run `make openapi` after changing the route's request/response shape.
- Functional tests use `#[Group('functional')]` and extend `AuthenticatedWebTestCase` (existing pattern in `tests/Search/Functional/SearchAvailableRoomTypesTest.php`).

---

### Task 1: Index `geo_place_id` on the read model

**Files:**
- Create: `migrations/Version20260620110000.php`

**Interfaces:**
- Produces: index `idx_search_hotel_room_types_geo_place_id` on `search.hotel_room_types (geo_place_id)`, used by the `WHERE s.geo_place_id = :geoPlaceId` filter introduced in Task 3.

- [ ] **Step 1: Generate the migration skeleton**

Run: `make generate-migration`

This creates a new file under `migrations/` named `VersionYYYYMMDDHHMMSS.php` with empty `up()`/`down()` methods. Rename it to `migrations/Version20260620110000.php` if the generated timestamp differs (keep migrations in this repo monotonically ordered; `20260620100000` is the last existing one, for the `geo_place_id` column itself).

- [ ] **Step 2: Write the migration**

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index search.hotel_room_types.geo_place_id — now the sole filter for availability search';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_search_hotel_room_types_geo_place_id ON search.hotel_room_types (geo_place_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_search_hotel_room_types_geo_place_id');
    }
}
```

- [ ] **Step 3: Run the migration**

Run: `make migrate`
Expected: migration `Version20260620110000` reported as migrated, no errors.

- [ ] **Step 4: Commit**

```bash
git add migrations/Version20260620110000.php
git commit -m "feat(search): index geo_place_id on the availability search read model"
```

---

### Task 2: Update the Domain port to filter by `geoPlaceId`

**Files:**
- Modify: `src/Search/Domain/Port/AvailableRoomTypeFinderInterface.php`

**Interfaces:**
- Produces: `AvailableRoomTypeFinderInterface::find(string $geoPlaceId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut, int $guests): list<AvailableRoomType>` — consumed by Task 3 (implementation) and Task 5 (handler).

- [ ] **Step 1: Replace the `city` parameter with `geoPlaceId`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

use App\Search\Domain\AvailableRoomType;

interface AvailableRoomTypeFinderInterface
{
    /** @return list<AvailableRoomType> */
    public function find(
        string $geoPlaceId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
        int $guests,
    ): array;
}
```

- [ ] **Step 2: Commit**

This compiles only once Task 3 updates the implementing class — commit together with Task 3.

---

### Task 3: Update the DBAL finder to filter on `geo_place_id`

**Files:**
- Modify: `src/Search/Infrastructure/Finder/DbalAvailableRoomTypeFinder.php`
- Test: `tests/Search/Functional/SearchAvailableRoomTypesTest.php` (extended in Task 7 — this task changes production code only; Task 7 covers it end-to-end through the controller, which is the existing test boundary for this finder)

**Interfaces:**
- Consumes: `AvailableRoomTypeFinderInterface` (Task 2).
- Produces: `DbalAvailableRoomTypeFinder::find(string $geoPlaceId, ...)` filtering `search.hotel_room_types` by `geo_place_id` equality instead of `city` equality.

- [ ] **Step 1: Update the SQL filter and method signature**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Finder;

use App\Search\Domain\AvailableRoomType;
use App\Search\Domain\Port\AvailableRoomTypeFinderInterface;
use Doctrine\DBAL\Connection;

final readonly class DbalAvailableRoomTypeFinder implements AvailableRoomTypeFinderInterface
{
    public function __construct(private Connection $searchConnection)
    {
    }

    /** @return list<AvailableRoomType> */
    public function find(
        string $geoPlaceId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
        int $guests,
    ): array {
        $rows = $this->searchConnection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                s.hotel_id,
                s.hotel_name,
                s.city,
                s.country,
                s.geo_place_id,
                s.star_rating,
                s.hotel_amenities,
                s.room_type_id,
                s.room_type_name,
                s.guest_capacity,
                s.bed_composition,
                s.room_amenities,
                s.base_price_cents
            FROM hotel_room_types s
            WHERE s.geo_place_id = :geoPlaceId
              AND s.guest_capacity >= :guests
              AND (
                SELECT COUNT(*)
                FROM room_index r
                WHERE r.room_type_id = s.room_type_id
                  AND NOT EXISTS (
                    SELECT 1
                    FROM unavailable_periods u
                    WHERE u.room_id = r.room_id
                      AND u.period && daterange(:checkIn, :checkOut)
                  )
              ) > 0
            ORDER BY s.hotel_name, s.room_type_name
            SQL,
            [
                'geoPlaceId' => $geoPlaceId,
                'guests' => $guests,
                'checkIn' => $checkIn->format('Y-m-d'),
                'checkOut' => $checkOut->format('Y-m-d'),
            ],
        );

        $results = [];
        foreach ($rows as $row) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): AvailableRoomType
    {
        /** @var array{hotel_id:string,hotel_name:string,city:string,country:string,geo_place_id:string|null,star_rating:string|null,hotel_amenities:string,room_type_id:string,room_type_name:string,guest_capacity:string,bed_composition:string,room_amenities:string,base_price_cents:string|null} $row */
        /** @var list<string> $hotelAmenities */
        $hotelAmenities = json_decode((string) $row['hotel_amenities'], true, flags: \JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $bedComposition */
        $bedComposition = json_decode((string) $row['bed_composition'], true, flags: \JSON_THROW_ON_ERROR);
        /** @var list<string> $roomAmenities */
        $roomAmenities = json_decode((string) $row['room_amenities'], true, flags: \JSON_THROW_ON_ERROR);

        return new AvailableRoomType(
            hotelId: (string) $row['hotel_id'],
            hotelName: (string) $row['hotel_name'],
            city: (string) $row['city'],
            country: (string) $row['country'],
            geoPlaceId: $row['geo_place_id'],
            starRating: null !== $row['star_rating'] ? (int) $row['star_rating'] : null,
            hotelAmenities: $hotelAmenities,
            roomTypeId: (string) $row['room_type_id'],
            roomTypeName: (string) $row['room_type_name'],
            guestCapacity: (int) $row['guest_capacity'],
            bedComposition: $bedComposition,
            roomAmenities: $roomAmenities,
            basePriceCents: null !== $row['base_price_cents'] ? (int) $row['base_price_cents'] : null,
        );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Search/Domain/Port/AvailableRoomTypeFinderInterface.php src/Search/Infrastructure/Finder/DbalAvailableRoomTypeFinder.php
git commit -m "feat(search): filter availability search by geo_place_id instead of city"
```

---

### Task 4: Update the Query to carry `geoPlaceId`

**Files:**
- Modify: `src/Search/Application/UseCase/SearchAvailableRoomTypes/SearchAvailableRoomTypesQuery.php`

**Interfaces:**
- Produces: `SearchAvailableRoomTypesQuery::$geoPlaceId` (replaces `$city`) — consumed by Task 5 (handler).

- [ ] **Step 1: Replace `city` with `geoPlaceId`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\SearchAvailableRoomTypes;

use App\Search\Domain\AvailableRoomType;
use App\Shared\Application\Bus\SyncQueryInterface;

/** @implements SyncQueryInterface<list<AvailableRoomType>> */
final readonly class SearchAvailableRoomTypesQuery implements SyncQueryInterface
{
    public function __construct(
        public string $geoPlaceId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $guests,
    ) {
    }
}
```

- [ ] **Step 2: Commit**

Commit together with Task 5 (handler) since the Query alone doesn't compile against the unchanged handler.

---

### Task 5: Update the Handler to pass `geoPlaceId` to the finder

**Files:**
- Modify: `src/Search/Application/UseCase/SearchAvailableRoomTypes/SearchAvailableRoomTypesQueryHandler.php`

**Interfaces:**
- Consumes: `SearchAvailableRoomTypesQuery::$geoPlaceId` (Task 4), `AvailableRoomTypeFinderInterface::find(string $geoPlaceId, ...)` (Task 2).

- [ ] **Step 1: Forward `geoPlaceId` instead of `city`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\SearchAvailableRoomTypes;

use App\Search\Domain\AvailableRoomType;
use App\Search\Domain\Port\AvailableRoomTypeFinderInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class SearchAvailableRoomTypesQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private AvailableRoomTypeFinderInterface $finder)
    {
    }

    /** @return list<AvailableRoomType> */
    public function __invoke(SearchAvailableRoomTypesQuery $query): array
    {
        return $this->finder->find(
            geoPlaceId: $query->geoPlaceId,
            checkIn: $query->checkIn,
            checkOut: $query->checkOut,
            guests: $query->guests,
        );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Search/Application/UseCase/SearchAvailableRoomTypes/SearchAvailableRoomTypesQuery.php src/Search/Application/UseCase/SearchAvailableRoomTypes/SearchAvailableRoomTypesQueryHandler.php
git commit -m "feat(search): thread geoPlaceId through the SearchAvailableRoomTypes query"
```

---

### Task 6: Update the Request DTO and Controller — `geoPlaceId` required, `city` kept but unused for filtering

**Files:**
- Modify: `src/Search/UI/Http/Controller/SearchAvailableRoomTypes/SearchAvailableRoomTypesRequest.php`
- Modify: `src/Search/UI/Http/Controller/SearchAvailableRoomTypes/SearchAvailableRoomTypesController.php`

**Interfaces:**
- Consumes: `SearchAvailableRoomTypesQuery::__construct(string $geoPlaceId, ...)` (Task 4).
- Produces: HTTP contract for `GET /api/v1/search` — `geoPlaceId` (required) and `city` (required, informational) query params.

- [ ] **Step 1: Add `geoPlaceId` to the Request DTO, keep `city` required**

```php
<?php

declare(strict_types=1);

namespace App\Search\UI\Http\Controller\SearchAvailableRoomTypes;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SearchAvailableRoomTypesRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[OA\Parameter(
            name: 'geoPlaceId',
            in: 'query',
            required: true,
            description: 'GeoNames id of the place selected via Geo Place Search autocomplete — sole filtering criterion',
            schema: new OA\Schema(type: 'string', example: '2988507'),
        )]
        public ?string $geoPlaceId = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[OA\Parameter(
            name: 'city',
            in: 'query',
            required: true,
            description: 'Free-text city name typed by the visitor — informational only, not used for filtering',
            schema: new OA\Schema(type: 'string', example: 'Paris'),
        )]
        public ?string $city = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Parameter(name: 'checkIn', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-07-01'))]
        public ?string $checkIn = null,
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Parameter(name: 'checkOut', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-07-05'))]
        public ?string $checkOut = null,
        #[Assert\NotBlank]
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(20)]
        #[OA\Parameter(name: 'guests', in: 'query', required: true, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 20, example: 2))]
        public ?int $guests = null,
    ) {
    }
}
```

- [ ] **Step 2: Update the Controller — pass `geoPlaceId` to the Query, drop `city` from it**

```php
<?php

declare(strict_types=1);

namespace App\Search\UI\Http\Controller\SearchAvailableRoomTypes;

use App\Search\Application\UseCase\SearchAvailableRoomTypes\SearchAvailableRoomTypesQuery;
use App\Search\Domain\AvailableRoomType;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/search',
    name: 'search_available_room_types',
    methods: ['GET'],
)]
#[OA\Get(
    summary: 'Search available room types',
    tags: ['Search'],
    responses: [
        new OA\Response(
            response: Response::HTTP_OK,
            description: 'List of available hotel room types matching the criteria',
            content: new OA\JsonContent(
                type: 'array',
                items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'hotelId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'hotelName', type: 'string', example: 'Grand Hôtel du Louvre'),
                        new OA\Property(property: 'city', type: 'string', example: 'Paris'),
                        new OA\Property(property: 'country', type: 'string', example: 'France'),
                        new OA\Property(property: 'geoPlaceId', type: 'string', nullable: true, example: '2988507'),
                        new OA\Property(property: 'starRating', type: 'integer', nullable: true, minimum: 1, maximum: 5, example: 4),
                        new OA\Property(property: 'hotelAmenities', type: 'array', items: new OA\Items(type: 'string'), example: ['pool', 'spa']),
                        new OA\Property(property: 'roomTypeId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'roomTypeName', type: 'string', example: 'Deluxe Double'),
                        new OA\Property(property: 'guestCapacity', type: 'integer', minimum: 1, example: 2),
                        new OA\Property(property: 'bedComposition', type: 'object', example: ['double' => 1]),
                        new OA\Property(property: 'roomAmenities', type: 'array', items: new OA\Items(type: 'string'), example: ['air_conditioning', 'minibar']),
                        new OA\Property(property: 'basePriceCents', type: 'integer', nullable: true, description: 'Base price in euro cents', example: 18000),
                    ],
                    type: 'object',
                ),
            ),
        ),
        new OA\Response(
            response: Response::HTTP_UNPROCESSABLE_ENTITY,
            description: 'Validation error',
            content: new OA\MediaType(
                mediaType: 'application/problem+json',
                schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'),
            ),
        ),
    ],
)]
final readonly class SearchAvailableRoomTypesController
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        SearchAvailableRoomTypesRequest $request,
    ): JsonResponse {
        /** @var list<AvailableRoomType> $results */
        $results = $this->queryBus->ask(new SearchAvailableRoomTypesQuery(
            geoPlaceId: (string) $request->geoPlaceId,
            checkIn: new \DateTimeImmutable((string) $request->checkIn),
            checkOut: new \DateTimeImmutable((string) $request->checkOut),
            guests: (int) $request->guests,
        ));

        return new JsonResponse($results);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Search/UI/Http/Controller/SearchAvailableRoomTypes/SearchAvailableRoomTypesRequest.php src/Search/UI/Http/Controller/SearchAvailableRoomTypes/SearchAvailableRoomTypesController.php
git commit -m "feat(search): require geoPlaceId on the availability search endpoint"
```

---

### Task 7: Update the functional test suite

**Files:**
- Modify: `tests/Search/Functional/SearchAvailableRoomTypesTest.php`

**Interfaces:**
- Consumes: `GET /api/v1/search` with `geoPlaceId`, `city`, `checkIn`, `checkOut`, `guests` query params (Task 6); `doctrine.dbal.search_connection` service for read-model fixtures (existing pattern, see `tests/Search/Functional/Console/RebuildSearchIndexCommandTest.php`).

- [ ] **Step 1: Write the failing test file**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Functional;

use App\Tests\Shared\AuthenticatedWebTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('functional')]
final class SearchAvailableRoomTypesTest extends AuthenticatedWebTestCase
{
    private const HOTEL_ID = '77777777-7777-7777-7777-777777777777';
    private const ROOM_TYPE_ID = '88888888-8888-8888-8888-888888888888';
    private const ROOM_ID = '99999999-9999-9999-9999-999999999999';
    private const GEO_PLACE_ID = '2988507';

    private Connection $searchConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->searchConnection = static::getContainer()->get('doctrine.dbal.search_connection');
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    #[Test]
    public function itReturns200WithEmptyResultsWhenNothingMatches(): void
    {
        $client = static::createAuthenticatedClient();
        $client->request('GET', '/api/v1/search?geoPlaceId=0000000&city=Nowhere&checkIn=2026-07-01&checkOut=2026-07-05&guests=2');

        self::assertResponseStatusCodeSame(200);
        self::assertJson((string) $client->getResponse()->getContent());

        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($body);
        self::assertSame([], $body);
    }

    #[Test]
    public function itReturns422WhenGeoPlaceIdIsMissing(): void
    {
        $client = static::createAuthenticatedClient();
        $client->request('GET', '/api/v1/search?city=Paris&checkIn=2026-07-01&checkOut=2026-07-05&guests=2');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns422WhenCityIsMissing(): void
    {
        $client = static::createAuthenticatedClient();
        $client->request('GET', '/api/v1/search?geoPlaceId=2988507&checkIn=2026-07-01&checkOut=2026-07-05&guests=2');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns422WhenGuestsIsZero(): void
    {
        $client = static::createAuthenticatedClient();
        $client->request('GET', '/api/v1/search?geoPlaceId=2988507&city=Paris&checkIn=2026-07-01&checkOut=2026-07-05&guests=0');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturnsResultsFilteredByGeoPlaceIdRegardlessOfCityText(): void
    {
        $this->insertFixtures();

        $client = static::createAuthenticatedClient();
        $client->request('GET', '/api/v1/search?geoPlaceId=' . self::GEO_PLACE_ID . '&city=ThisTextIsIgnored&checkIn=2026-07-01&checkOut=2026-07-05&guests=2');

        self::assertResponseStatusCodeSame(200);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($body);
        self::assertCount(1, $body);
        self::assertSame(self::ROOM_TYPE_ID, $body[0]['roomTypeId']);
        self::assertSame(self::GEO_PLACE_ID, $body[0]['geoPlaceId']);
    }

    #[Test]
    public function itReturnsNoResultsWhenGeoPlaceIdDoesNotMatchEvenIfCityMatches(): void
    {
        $this->insertFixtures();

        $client = static::createAuthenticatedClient();
        $client->request('GET', '/api/v1/search?geoPlaceId=9999999&city=Paris&checkIn=2026-07-01&checkOut=2026-07-05&guests=2');

        self::assertResponseStatusCodeSame(200);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame([], $body);
    }

    private function insertFixtures(): void
    {
        $this->searchConnection->executeStatement(
            'INSERT INTO hotel_room_types (room_type_id, hotel_id, hotel_name, city, country, geo_place_id, hotel_amenities, room_type_name, guest_capacity, bed_composition, room_amenities)
             VALUES (:roomTypeId, :hotelId, :hotelName, :city, :country, :geoPlaceId, :hotelAmenities, :roomTypeName, :guestCapacity, :bedComposition, :roomAmenities)',
            [
                'roomTypeId' => self::ROOM_TYPE_ID,
                'hotelId' => self::HOTEL_ID,
                'hotelName' => 'Grand Hôtel du Louvre',
                'city' => 'Paris',
                'country' => 'FR',
                'geoPlaceId' => self::GEO_PLACE_ID,
                'hotelAmenities' => '[]',
                'roomTypeName' => 'Deluxe Double',
                'guestCapacity' => 2,
                'bedComposition' => '{"double":1}',
                'roomAmenities' => '[]',
            ],
        );

        $this->searchConnection->executeStatement(
            'INSERT INTO room_index (room_id, room_type_id, hotel_id) VALUES (:roomId, :roomTypeId, :hotelId)',
            [
                'roomId' => self::ROOM_ID,
                'roomTypeId' => self::ROOM_TYPE_ID,
                'hotelId' => self::HOTEL_ID,
            ],
        );
    }

    private function cleanUp(): void
    {
        $this->searchConnection->executeStatement('DELETE FROM unavailable_periods');
        $this->searchConnection->executeStatement('DELETE FROM room_index');
        $this->searchConnection->executeStatement('DELETE FROM hotel_room_types');
    }
}
```

- [ ] **Step 2: Run the test to verify it compiles and the new assertions are meaningful**

Run: `make functional-test`
Expected before Tasks 1–6 are applied: `itReturns422WhenGeoPlaceIdIsMissing` and the two `geoPlaceId`-filtering tests FAIL (the current controller has no `geoPlaceId` param, and the finder filters on `city`). If running this task after Tasks 1–6 are already applied (recommended order — see note below), skip straight to Step 3.

- [ ] **Step 3: Run the full suite to confirm everything passes together**

Run: `make functional-test`
Expected: all tests in `SearchAvailableRoomTypesTest` PASS, including the renamed `itReturns422WhenGeoPlaceIdIsMissing` and the two new geo-filtering tests.

- [ ] **Step 4: Commit**

```bash
git add tests/Search/Functional/SearchAvailableRoomTypesTest.php
git commit -m "test(search): cover geo_place_id filtering on the availability search endpoint"
```

---

### Task 8: Regenerate OpenAPI and run full lint/test suite

**Files:**
- Modify: `openapi.yaml` (generated)

- [ ] **Step 1: Regenerate the OpenAPI spec**

Run: `make openapi`
Expected: `openapi.yaml` updated — the `/search` GET operation now lists `geoPlaceId` as a required query parameter, alongside the still-required `city`.

- [ ] **Step 2: Run the full lint and test suite**

Run: `make lint && make test`
Expected: CS Fixer, PHPStan, and Deptrac all pass; all unit/integration/functional tests pass.

- [ ] **Step 3: Commit**

```bash
git add openapi.yaml
git commit -m "docs(search): regenerate openapi.yaml for geoPlaceId-based availability search"
```

---

## Note on execution order

Tasks 2–6 are interdependent (Query/Handler/Finder/Controller must change together to keep the codebase compiling) — a subagent-driven executor should treat Tasks 2 through 6 as a single reviewable unit if strict single-task compilability is required, or apply them in the listed order within one execution pass before running any test. Task 7's tests are written against the target end-state contract and will only pass once Tasks 1–6 are complete.

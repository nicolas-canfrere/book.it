# Geo Context Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a new bounded context `Geo` that imports the GeoNames open dataset into a dedicated PostgreSQL schema and exposes a public, fuzzy-search `GET /api/v1/geo/places` endpoint (autocomplete) over it.

**Architecture:** Standard 4-layer bounded context (`Domain` / `Application` / `Infrastructure` / `UI`) with its own DBAL connection (`geo`) isolated via `search_path` on the shared `bookit` database, following the exact pattern already used by `Search`, `Booker`, `Hotel`, etc. The referential data (`geo_place` table) is populated by a console command reading a locally-downloaded GeoNames dump file — no live HTTP calls to GeoNames at runtime. Fuzzy matching uses PostgreSQL's `pg_trgm` extension (see ADR-0016).

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL (raw SQL, no ORM mapping — matches `Search` context style), PostgreSQL 16 `pg_trgm`.

## Global Constraints

- Single use case this iteration: `SearchGeoPlaces` (fuzzy lookup). No `GetGeoPlace(id)` lookup yet.
- `query` parameter: minimum 2 characters, maximum 255 characters.
- Results: maximum 10, ranked by similarity, fixed (not client-configurable).
- Admin1 subdivision is stored and returned as its raw GeoNames code (e.g. `TX`), never resolved to a full name (e.g. `Texas`) — that resolution is explicitly out of scope.
- Endpoint is public — no authentication.
- Response shape: `{"data": [...]}`, no `meta` (no pagination).
- `id` field is an `integer` (the GeoNames numeric id), not a string.
- Import command takes a local file path argument — it never downloads from GeoNames itself.
- All new PHP files: `declare(strict_types=1)`, no inline SQL outside `Infrastructure/`.
- Branch `feat/geo-context` already checked out — do not commit to `main`.

---

## Task 1: Geo schema migration

**Files:**
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (exact name/timestamp determined by `make generate-migration` when run — referred to below as `<generated-file>` / `<generated-class>`)

**Interfaces:**
- Produces: PostgreSQL schema `geo`, table `geo.geo_place(geoname_id BIGINT PK, name VARCHAR(255), ascii_name VARCHAR(255), country_code VARCHAR(2), admin1_code VARCHAR(20) NULL)` with `COMMENT ON TABLE`/`COMMENT ON COLUMN` documentation, GIN trigram indexes on `name` and `ascii_name`, `pg_trgm` extension enabled.

- [ ] **Step 1: Generate the migration skeleton**

Run: `make generate-migration`
Expected: output prints something like `Created Migration: /app/migrations/VersionYYYYMMDDHHMMSS.php`. Note the exact class name and file path — this is `<generated-file>` / `<generated-class>` for the rest of this task.

- [ ] **Step 2: Fill in the migration body**

Edit `<generated-file>`, replacing the skeleton's `getDescription()`, `up()`, and `down()` with:

```php
    public function getDescription(): string
    {
        return 'Add Geo context schema and geo_place referential table with pg_trgm fuzzy search support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS geo');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        $this->addSql(<<<'SQL'
            CREATE TABLE geo.geo_place (
                geoname_id   BIGINT       NOT NULL,
                name         VARCHAR(255) NOT NULL,
                ascii_name   VARCHAR(255) NOT NULL,
                country_code VARCHAR(2)   NOT NULL,
                admin1_code  VARCHAR(20)  NULL,
                PRIMARY KEY (geoname_id)
            )
            SQL);

        $this->addSql('CREATE INDEX idx_geo_geo_place_name_trgm ON geo.geo_place USING GIN (name gin_trgm_ops)');
        $this->addSql('CREATE INDEX idx_geo_geo_place_ascii_name_trgm ON geo.geo_place USING GIN (ascii_name gin_trgm_ops)');

        $this->addSql("COMMENT ON TABLE geo.geo_place IS 'Referential of geographic places imported from the GeoNames open dataset, used to power Geo Place Search (fuzzy autocomplete). Distinct from the free-text city field on a Hotel Address.'");
        $this->addSql("COMMENT ON COLUMN geo.geo_place.geoname_id IS 'GeoNames numeric identifier (stable across dump re-imports) — primary key.'");
        $this->addSql("COMMENT ON COLUMN geo.geo_place.name IS 'GeoNames display name of the place, in its native/local spelling (e.g. \"Paris\").'");
        $this->addSql("COMMENT ON COLUMN geo.geo_place.ascii_name IS 'ASCII-normalized name (accents stripped), used together with name for pg_trgm fuzzy matching.'");
        $this->addSql("COMMENT ON COLUMN geo.geo_place.country_code IS 'ISO 3166-1 alpha-2 country code, as provided by GeoNames.'");
        $this->addSql("COMMENT ON COLUMN geo.geo_place.admin1_code IS 'Raw GeoNames admin1 subdivision code (state/region, e.g. \"TX\"), not resolved to a full name. Nullable: absent for some countries.'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS geo.geo_place');
        $this->addSql('DROP SCHEMA IF EXISTS geo');
    }
```

- [ ] **Step 3: Run the migration**

Run: `make migrate`
Expected: output shows `<generated-class>` migrated, no errors.

- [ ] **Step 4: Verify migration status**

Run: `docker compose run --rm --remove-orphans php bin/console doctrine:migrations:status`
Expected: "Already executed Migrations" includes `<generated-class>`.

- [ ] **Step 5: Commit**

```bash
git add migrations/<generated-file>
git commit -m "feat(geo): add geo schema migration with pg_trgm-indexed geo_place table"
```

---

## Task 2: Domain and Infrastructure layers + DI wiring

**Files:**
- Create: `src/Shared/Domain/ValueObject/GeoPlaceId.php`
- Create: `src/Geo/Domain/GeoPlace.php`
- Create: `src/Geo/Domain/Port/GeoPlaceFinderInterface.php`
- Create: `src/Geo/Domain/Port/GeoPlaceWriterInterface.php`
- Create: `src/Geo/Infrastructure/Finder/DbalGeoPlaceFinder.php`
- Create: `src/Geo/Infrastructure/Persistence/DbalGeoPlaceWriter.php`
- Create: `config/services/geo.yaml`
- Modify: `config/packages/doctrine.yaml` — add `geo` connection
- Modify: `deptrac-contexts.yaml` — add `Geo` layer + ruleset entry
- Test: `tests/Shared/Domain/ValueObject/GeoPlaceIdTest.php`
- Test: `tests/Geo/Infrastructure/Finder/DbalGeoPlaceFinderTest.php`
- Test: `tests/Geo/Infrastructure/Persistence/DbalGeoPlaceWriterTest.php`

**Interfaces:**
- Produces: `App\Shared\Domain\ValueObject\GeoPlaceId` (readonly: `string $value`, `__toString()`, `equals(GeoPlaceId $other): bool`); `App\Geo\Domain\GeoPlace` (readonly: `GeoPlaceId $id`, `string $name`, `string $countryCode`, `?string $admin1Code`); `App\Geo\Domain\Port\GeoPlaceFinderInterface::search(string $query, int $limit): list<GeoPlace>`; `App\Geo\Domain\Port\GeoPlaceWriterInterface::upsert(GeoPlaceId $id, string $name, string $asciiName, string $countryCode, ?string $admin1Code): void`.

- [ ] **Step 1: Write the failing unit test for `GeoPlaceId`**

`tests/Shared/Domain/ValueObject/GeoPlaceIdTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\GeoPlaceId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GeoPlaceIdTest extends TestCase
{
    #[Test]
    public function itExposesItsValueAsAString(): void
    {
        $id = new GeoPlaceId('2988507');

        self::assertSame('2988507', $id->value);
        self::assertSame('2988507', (string) $id);
    }

    #[Test]
    public function itComparesByValue(): void
    {
        self::assertTrue((new GeoPlaceId('2988507'))->equals(new GeoPlaceId('2988507')));
        self::assertFalse((new GeoPlaceId('2988507'))->equals(new GeoPlaceId('4717560')));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `make unit-test`
Expected: FAIL — `Class "App\Shared\Domain\ValueObject\GeoPlaceId" not found`.

- [ ] **Step 3: Write `GeoPlaceId`**

`src/Shared/Domain/ValueObject/GeoPlaceId.php` — mirrors the existing `HotelId`/`RoomId` value objects in the same namespace:

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class GeoPlaceId
{
    public function __construct(public string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(GeoPlaceId $other): bool
    {
        return $this->value === $other->value;
    }
}
```

- [ ] **Step 4: Run the `GeoPlaceId` test to verify it passes**

Run: `make unit-test`
Expected: PASS.

- [ ] **Step 5: Write the failing unit test for the Finder**

`tests/Geo/Infrastructure/Finder/DbalGeoPlaceFinderTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Geo\Infrastructure\Finder;

use App\Geo\Infrastructure\Finder\DbalGeoPlaceFinder;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DbalGeoPlaceFinderTest extends TestCase
{
    #[Test]
    public function itHydratesRowsIntoGeoPlaces(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->stringContains('FROM geo_place'),
                ['query' => 'pari', 'limit' => 10],
            )
            ->willReturn([
                ['geoname_id' => '2988507', 'name' => 'Paris', 'country_code' => 'FR', 'admin1_code' => '11'],
                ['geoname_id' => '4717560', 'name' => 'Paris', 'country_code' => 'US', 'admin1_code' => 'TX'],
            ]);

        $results = (new DbalGeoPlaceFinder($connection))->search('pari', 10);

        self::assertCount(2, $results);
        self::assertSame('2988507', $results[0]->id->value);
        self::assertSame('Paris', $results[0]->name);
        self::assertSame('FR', $results[0]->countryCode);
        self::assertSame('11', $results[0]->admin1Code);
        self::assertSame('4717560', $results[1]->id->value);
        self::assertSame('TX', $results[1]->admin1Code);
    }

    #[Test]
    public function itHydratesNullAdmin1Code(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['geoname_id' => '2950159', 'name' => 'Berlin', 'country_code' => 'DE', 'admin1_code' => null],
        ]);

        $results = (new DbalGeoPlaceFinder($connection))->search('berl', 10);

        self::assertNull($results[0]->admin1Code);
    }
}
```

- [ ] **Step 6: Run it to verify it fails**

Run: `make unit-test`
Expected: FAIL — `Class "App\Geo\Infrastructure\Finder\DbalGeoPlaceFinder" not found`.

- [ ] **Step 7: Write the Domain model and ports**

`src/Geo/Domain/GeoPlace.php`:

```php
<?php

declare(strict_types=1);

namespace App\Geo\Domain;

use App\Shared\Domain\ValueObject\GeoPlaceId;

final readonly class GeoPlace
{
    public function __construct(
        public GeoPlaceId $id,
        public string $name,
        public string $countryCode,
        public ?string $admin1Code,
    ) {
    }
}
```

`src/Geo/Domain/Port/GeoPlaceFinderInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Geo\Domain\Port;

use App\Geo\Domain\GeoPlace;

interface GeoPlaceFinderInterface
{
    /** @return list<GeoPlace> */
    public function search(string $query, int $limit): array;
}
```

`src/Geo/Domain/Port/GeoPlaceWriterInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Geo\Domain\Port;

use App\Shared\Domain\ValueObject\GeoPlaceId;

interface GeoPlaceWriterInterface
{
    public function upsert(
        GeoPlaceId $id,
        string $name,
        string $asciiName,
        string $countryCode,
        ?string $admin1Code,
    ): void;
}
```

- [ ] **Step 8: Write the Finder implementation**

`src/Geo/Infrastructure/Finder/DbalGeoPlaceFinder.php`:

```php
<?php

declare(strict_types=1);

namespace App\Geo\Infrastructure\Finder;

use App\Geo\Domain\GeoPlace;
use App\Geo\Domain\Port\GeoPlaceFinderInterface;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use Doctrine\DBAL\Connection;

final readonly class DbalGeoPlaceFinder implements GeoPlaceFinderInterface
{
    public function __construct(private Connection $geoConnection)
    {
    }

    /** @return list<GeoPlace> */
    public function search(string $query, int $limit): array
    {
        $rows = $this->geoConnection->fetchAllAssociative(
            <<<'SQL'
            SELECT geoname_id, name, country_code, admin1_code
            FROM geo_place
            WHERE name % :query OR ascii_name % :query
            ORDER BY GREATEST(similarity(name, :query), similarity(ascii_name, :query)) DESC
            LIMIT :limit
            SQL,
            ['query' => $query, 'limit' => $limit],
        );

        $results = [];
        foreach ($rows as $row) {
            /** @var array{geoname_id: string|int, name: string, country_code: string, admin1_code: string|null} $row */
            $results[] = new GeoPlace(
                id: new GeoPlaceId((string) $row['geoname_id']),
                name: (string) $row['name'],
                countryCode: (string) $row['country_code'],
                admin1Code: $row['admin1_code'],
            );
        }

        return $results;
    }
}
```

- [ ] **Step 9: Run the Finder test to verify it passes**

Run: `make unit-test`
Expected: PASS.

- [ ] **Step 10: Write the failing unit test for the Writer**

`tests/Geo/Infrastructure/Persistence/DbalGeoPlaceWriterTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Geo\Infrastructure\Persistence;

use App\Geo\Infrastructure\Persistence\DbalGeoPlaceWriter;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DbalGeoPlaceWriterTest extends TestCase
{
    #[Test]
    public function itUpsertsAGeoPlace(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('ON CONFLICT (geoname_id) DO UPDATE'),
                [
                    'geonameId' => '2988507',
                    'name' => 'Paris',
                    'asciiName' => 'Paris',
                    'countryCode' => 'FR',
                    'admin1Code' => '11',
                ],
            );

        (new DbalGeoPlaceWriter($connection))->upsert(new GeoPlaceId('2988507'), 'Paris', 'Paris', 'FR', '11');
    }
}
```

- [ ] **Step 11: Run it to verify it fails**

Run: `make unit-test`
Expected: FAIL — `Class "App\Geo\Infrastructure\Persistence\DbalGeoPlaceWriter" not found`.

- [ ] **Step 12: Write the Writer implementation**

`src/Geo/Infrastructure/Persistence/DbalGeoPlaceWriter.php`:

```php
<?php

declare(strict_types=1);

namespace App\Geo\Infrastructure\Persistence;

use App\Geo\Domain\Port\GeoPlaceWriterInterface;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use Doctrine\DBAL\Connection;

final readonly class DbalGeoPlaceWriter implements GeoPlaceWriterInterface
{
    public function __construct(private Connection $geoConnection)
    {
    }

    public function upsert(
        GeoPlaceId $id,
        string $name,
        string $asciiName,
        string $countryCode,
        ?string $admin1Code,
    ): void {
        $this->geoConnection->executeStatement(
            <<<'SQL'
            INSERT INTO geo_place (geoname_id, name, ascii_name, country_code, admin1_code)
            VALUES (:geonameId, :name, :asciiName, :countryCode, :admin1Code)
            ON CONFLICT (geoname_id) DO UPDATE SET
                name = EXCLUDED.name,
                ascii_name = EXCLUDED.ascii_name,
                country_code = EXCLUDED.country_code,
                admin1_code = EXCLUDED.admin1_code
            SQL,
            [
                'geonameId' => $id->value,
                'name' => $name,
                'asciiName' => $asciiName,
                'countryCode' => $countryCode,
                'admin1Code' => $admin1Code,
            ],
        );
    }
}
```

- [ ] **Step 13: Run the Writer test to verify it passes**

Run: `make unit-test`
Expected: PASS.

- [ ] **Step 14: Add the `geo` DBAL connection**

Modify `config/packages/doctrine.yaml` — add this entry inside `doctrine.dbal.connections`, after the `translation` entry:

```yaml
            geo:
                server_version: '16'
                url: '%env(resolve:BOOKIT_DATABASE_URL)%' # same DB, isolated via search_path=geo (set by SearchPathMiddleware)
                profiling_collect_backtrace: '%kernel.debug%'
```

- [ ] **Step 15: Create the DI config for the Geo context**

`config/services/geo.yaml` — only `Domain` and `Infrastructure` resources exist on disk at this point; `Application` and `UI` resources are added in later tasks once those directories exist (adding a `resource:` entry for a non-existent directory throws `FileLocatorFileNotFoundException`):

```yaml
parameters: {}

services:
    _defaults:
        autowire: true
        autoconfigure: true
    _instanceof:
        App\Shared\Application\Bus\SyncQueryHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: sync.query.bus}
        App\Shared\Application\Bus\AsyncCommandHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: messenger.bus.default}

    App\Geo\Domain\:
        resource: '../../src/Geo/Domain/'

    App\Geo\Infrastructure\:
        resource: '../../src/Geo/Infrastructure/'

    bookit.doctrine.middleware.search_path.geo:
        class: App\Shared\Infrastructure\Doctrine\SearchPathMiddleware
        arguments:
            $schema: 'geo'
        tags:
            - {name: doctrine.middleware, connection: geo}
```

- [ ] **Step 16: Add the `Geo` layer to deptrac-contexts.yaml**

Modify `deptrac-contexts.yaml` — add this layer block right after the `BookerContract` block (before `Hotel`):

```yaml
        -
            name: Geo
            collectors:
                -
                    type: classLike
                    value: 'App\\Geo\\.*'
```

And add this ruleset entry right after the `Booker:` ruleset block (before `Hotel:`):

```yaml
        Geo:
            - Shared
            - Vendor
```

- [ ] **Step 17: Verify the container compiles and architecture rules pass**

Run: `docker compose run --rm --remove-orphans php bin/console cache:clear && make deptrac`
Expected: cache clears without error (container compiles); deptrac reports no violations.

- [ ] **Step 18: Commit**

```bash
git add src/Shared/Domain/ValueObject/GeoPlaceId.php src/Geo/Domain src/Geo/Infrastructure config/services/geo.yaml config/packages/doctrine.yaml deptrac-contexts.yaml tests/Shared/Domain/ValueObject/GeoPlaceIdTest.php tests/Geo/Infrastructure
git commit -m "feat(geo): add GeoPlaceId value object, GeoPlace domain model, DBAL finder/writer, and geo connection wiring"
```

---

## Task 3: Application layer — `SearchGeoPlaces` use case

**Files:**
- Create: `src/Geo/Application/UseCase/SearchGeoPlaces/SearchGeoPlacesQuery.php`
- Create: `src/Geo/Application/UseCase/SearchGeoPlaces/SearchGeoPlacesQueryHandler.php`
- Modify: `config/services/geo.yaml` — add `Application` resource
- Test: `tests/Geo/Infrastructure/Finder/InMemory/InMemoryGeoPlaceFinder.php` (test double)
- Test: `tests/Geo/Application/UseCase/SearchGeoPlaces/SearchGeoPlacesQueryHandlerTest.php`

**Interfaces:**
- Consumes: `App\Geo\Domain\Port\GeoPlaceFinderInterface::search(string $query, int $limit): list<GeoPlace>` (Task 2).
- Produces: `App\Geo\Application\UseCase\SearchGeoPlaces\SearchGeoPlacesQuery` (readonly: `string $query`); `SearchGeoPlacesQueryHandler::__invoke(SearchGeoPlacesQuery): list<GeoPlace>`, always calling the finder with `limit = 10`.

- [ ] **Step 1: Write the in-memory test double**

`tests/Geo/Infrastructure/Finder/InMemory/InMemoryGeoPlaceFinder.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Geo\Infrastructure\Finder\InMemory;

use App\Geo\Domain\GeoPlace;
use App\Geo\Domain\Port\GeoPlaceFinderInterface;

final class InMemoryGeoPlaceFinder implements GeoPlaceFinderInterface
{
    /** @var list<GeoPlace> */
    private array $places = [];

    /** @var array{query: string, limit: int}|null */
    public ?array $lastCall = null;

    public function addPlace(GeoPlace $place): void
    {
        $this->places[] = $place;
    }

    /** @return list<GeoPlace> */
    public function search(string $query, int $limit): array
    {
        $this->lastCall = ['query' => $query, 'limit' => $limit];

        return array_slice($this->places, 0, $limit);
    }
}
```

- [ ] **Step 2: Write the failing unit test for the handler**

`tests/Geo/Application/UseCase/SearchGeoPlaces/SearchGeoPlacesQueryHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Geo\Application\UseCase\SearchGeoPlaces;

use App\Geo\Application\UseCase\SearchGeoPlaces\SearchGeoPlacesQuery;
use App\Geo\Application\UseCase\SearchGeoPlaces\SearchGeoPlacesQueryHandler;
use App\Geo\Domain\GeoPlace;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use App\Tests\Geo\Infrastructure\Finder\InMemory\InMemoryGeoPlaceFinder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SearchGeoPlacesQueryHandlerTest extends TestCase
{
    #[Test]
    public function itReturnsPlacesFromTheFinder(): void
    {
        $finder = new InMemoryGeoPlaceFinder();
        $finder->addPlace(new GeoPlace(id: new GeoPlaceId('2988507'), name: 'Paris', countryCode: 'FR', admin1Code: '11'));
        $handler = new SearchGeoPlacesQueryHandler($finder);

        $result = $handler(new SearchGeoPlacesQuery('pari'));

        self::assertCount(1, $result);
        self::assertSame('2988507', $result[0]->id->value);
    }

    #[Test]
    public function itDelegatesToTheFinderWithAMaxLimitOfTen(): void
    {
        $finder = new InMemoryGeoPlaceFinder();
        $handler = new SearchGeoPlacesQueryHandler($finder);

        $handler(new SearchGeoPlacesQuery('pari'));

        self::assertSame(['query' => 'pari', 'limit' => 10], $finder->lastCall);
    }
}
```

- [ ] **Step 3: Run it to verify it fails**

Run: `make unit-test`
Expected: FAIL — `Class "App\Geo\Application\UseCase\SearchGeoPlaces\SearchGeoPlacesQuery" not found`.

- [ ] **Step 4: Write the Query and Handler**

`src/Geo/Application/UseCase/SearchGeoPlaces/SearchGeoPlacesQuery.php`:

```php
<?php

declare(strict_types=1);

namespace App\Geo\Application\UseCase\SearchGeoPlaces;

use App\Geo\Domain\GeoPlace;
use App\Shared\Application\Bus\SyncQueryInterface;

/** @implements SyncQueryInterface<list<GeoPlace>> */
final readonly class SearchGeoPlacesQuery implements SyncQueryInterface
{
    public function __construct(public string $query)
    {
    }
}
```

`src/Geo/Application/UseCase/SearchGeoPlaces/SearchGeoPlacesQueryHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Geo\Application\UseCase\SearchGeoPlaces;

use App\Geo\Domain\GeoPlace;
use App\Geo\Domain\Port\GeoPlaceFinderInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class SearchGeoPlacesQueryHandler implements SyncQueryHandlerInterface
{
    private const int MAX_RESULTS = 10;

    public function __construct(private GeoPlaceFinderInterface $finder)
    {
    }

    /** @return list<GeoPlace> */
    public function __invoke(SearchGeoPlacesQuery $query): array
    {
        return $this->finder->search($query->query, self::MAX_RESULTS);
    }
}
```

- [ ] **Step 5: Add the `Application` resource to the DI config**

Modify `config/services/geo.yaml` — add this block after `App\Geo\Infrastructure\:`:

```yaml
    App\Geo\Application\:
        resource: '../../src/Geo/Application/'
        exclude:
            - '../../src/Geo/Application/**/*Query.php'
            - '../../src/Geo/Application/**/*Command.php'
```

- [ ] **Step 6: Run the handler test to verify it passes**

Run: `make unit-test`
Expected: PASS.

- [ ] **Step 7: Verify the container still compiles**

Run: `docker compose run --rm --remove-orphans php bin/console cache:clear`
Expected: no error.

- [ ] **Step 8: Commit**

```bash
git add src/Geo/Application config/services/geo.yaml tests/Geo/Infrastructure/Finder/InMemory tests/Geo/Application
git commit -m "feat(geo): add SearchGeoPlaces application use case"
```

---

## Task 4: UI layer — `GET /api/v1/geo/places` endpoint

**Files:**
- Create: `src/Geo/UI/Http/Controller/GeoPlaceSerializer.php`
- Create: `src/Geo/UI/Http/Controller/SearchGeoPlaces/SearchGeoPlacesRequest.php`
- Create: `src/Geo/UI/Http/Controller/SearchGeoPlaces/SearchGeoPlacesController.php`
- Modify: `config/services/geo.yaml` — add `UI` resource
- Test: `tests/Geo/UI/Http/Controller/GeoPlaceSerializerTest.php`
- Test: `tests/Geo/UI/Http/Controller/SearchGeoPlaces/SearchGeoPlacesControllerTest.php`

**Interfaces:**
- Consumes: `App\Geo\Application\UseCase\SearchGeoPlaces\SearchGeoPlacesQuery` (Task 3), `App\Shared\Application\Bus\SyncQueryBusInterface::ask()`.
- Produces: `App\Geo\UI\Http\Controller\GeoPlaceSerializer::serialize(GeoPlace $geoPlace): array{id: int, name: string, countryCode: string, admin1Code: string|null}` (casts `GeoPlaceId::$value` to `int` for the wire contract); route `search_geo_places` at `GET /api/v1/geo/places?query=...`, returning `200 {"data": [{"id": int, "name": string, "countryCode": string, "admin1Code": string|null}, ...]}` or `422` on validation failure.

- [ ] **Step 1: Write the failing unit test for the serializer**

`tests/Geo/UI/Http/Controller/GeoPlaceSerializerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Geo\UI\Http\Controller;

use App\Geo\Domain\GeoPlace;
use App\Geo\UI\Http\Controller\GeoPlaceSerializer;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GeoPlaceSerializerTest extends TestCase
{
    #[Test]
    public function itSerializesAGeoPlaceWithAnIntegerId(): void
    {
        $geoPlace = new GeoPlace(id: new GeoPlaceId('2988507'), name: 'Paris', countryCode: 'FR', admin1Code: '11');

        $result = (new GeoPlaceSerializer())->serialize($geoPlace);

        self::assertSame([
            'id' => 2988507,
            'name' => 'Paris',
            'countryCode' => 'FR',
            'admin1Code' => '11',
        ], $result);
    }

    #[Test]
    public function itSerializesANullAdmin1Code(): void
    {
        $geoPlace = new GeoPlace(id: new GeoPlaceId('2950159'), name: 'Berlin', countryCode: 'DE', admin1Code: null);

        $result = (new GeoPlaceSerializer())->serialize($geoPlace);

        self::assertNull($result['admin1Code']);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `make unit-test`
Expected: FAIL — `Class "App\Geo\UI\Http\Controller\GeoPlaceSerializer" not found`.

- [ ] **Step 3: Write the serializer**

`src/Geo/UI/Http/Controller/GeoPlaceSerializer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Geo\UI\Http\Controller;

use App\Geo\Domain\GeoPlace;

final class GeoPlaceSerializer
{
    /** @return array{id: int, name: string, countryCode: string, admin1Code: string|null} */
    public function serialize(GeoPlace $geoPlace): array
    {
        return [
            'id' => (int) $geoPlace->id->value,
            'name' => $geoPlace->name,
            'countryCode' => $geoPlace->countryCode,
            'admin1Code' => $geoPlace->admin1Code,
        ];
    }
}
```

- [ ] **Step 4: Run the serializer test to verify it passes**

Run: `make unit-test`
Expected: PASS.

- [ ] **Step 5: Write the failing functional test**

`tests/Geo/UI/Http/Controller/SearchGeoPlaces/SearchGeoPlacesControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Geo\UI\Http\Controller\SearchGeoPlaces;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class SearchGeoPlacesControllerTest extends WebTestCase
{
    #[Test]
    public function itReturnsPlacesMatchingTheFuzzyQuery(): void
    {
        $client = static::createClient();
        $geoConnection = static::getContainer()->get('doctrine.dbal.geo_connection');
        \assert($geoConnection instanceof Connection);
        $geoConnection->executeStatement('TRUNCATE geo_place');
        $geoConnection->executeStatement(
            "INSERT INTO geo_place (geoname_id, name, ascii_name, country_code, admin1_code) VALUES
                (2988507, 'Paris', 'Paris', 'FR', '11'),
                (4717560, 'Paris', 'Paris', 'US', 'TX'),
                (2950159, 'Berlin', 'Berlin', 'DE', NULL)",
        );

        $client->request(method: 'GET', uri: '/api/v1/geo/places?query=pari');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{id: int, name: string, countryCode: string, admin1Code: string|null}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $ids = array_column($body['data'], 'id');
        self::assertContains(2988507, $ids);
        self::assertContains(4717560, $ids);
        self::assertNotContains(2950159, $ids);
    }

    #[Test]
    public function itReturns422WhenQueryIsTooShort(): void
    {
        $client = static::createClient();

        $client->request(method: 'GET', uri: '/api/v1/geo/places?query=p');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }
}
```

- [ ] **Step 6: Run it to verify it fails**

Run: `make functional-test`
Expected: FAIL — route `search_geo_places` not found (404), or class not found.

- [ ] **Step 7: Write the Request DTO**

`src/Geo/UI/Http/Controller/SearchGeoPlaces/SearchGeoPlacesRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Geo\UI\Http\Controller\SearchGeoPlaces;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SearchGeoPlacesRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        #[OA\Parameter(name: 'query', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'pari'))]
        public ?string $query = null,
    ) {
    }
}
```

- [ ] **Step 8: Write the Controller**

`src/Geo/UI/Http/Controller/SearchGeoPlaces/SearchGeoPlacesController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Geo\UI\Http\Controller\SearchGeoPlaces;

use App\Geo\Application\UseCase\SearchGeoPlaces\SearchGeoPlacesQuery;
use App\Geo\Domain\GeoPlace;
use App\Geo\UI\Http\Controller\GeoPlaceSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/geo/places',
    name: 'search_geo_places',
    methods: ['GET'],
)]
#[OA\Get(
    summary: 'Search Geo Places by fuzzy name match',
    tags: ['Geo'],
    responses: [
        new OA\Response(
            response: Response::HTTP_OK,
            description: 'Geo Places matching the query, ranked by similarity',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 2988507),
                                new OA\Property(property: 'name', type: 'string', example: 'Paris'),
                                new OA\Property(property: 'countryCode', type: 'string', example: 'FR'),
                                new OA\Property(property: 'admin1Code', type: 'string', nullable: true, example: '11'),
                            ],
                            type: 'object',
                        ),
                    ),
                ],
                type: 'object',
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
final readonly class SearchGeoPlacesController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private GeoPlaceSerializer $serializer,
    ) {
    }

    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        SearchGeoPlacesRequest $request,
    ): JsonResponse {
        /** @var list<GeoPlace> $results */
        $results = $this->queryBus->ask(new SearchGeoPlacesQuery(query: (string) $request->query));

        return new JsonResponse(['data' => array_map($this->serializer->serialize(...), $results)]);
    }
}
```

- [ ] **Step 9: Add the `UI` resource to the DI config**

Modify `config/services/geo.yaml` — add this block after `App\Geo\Application\:` (and its `exclude`):

```yaml
    App\Geo\UI\:
        resource: '../../src/Geo/UI/'
        exclude:
            - '../../src/Geo/UI/**/*Request.php'
```

- [ ] **Step 10: Run the functional test to verify it passes**

Run: `make functional-test`
Expected: PASS.

- [ ] **Step 11: Regenerate the OpenAPI spec**

Run: `make openapi`
Expected: `openapi.yaml` is updated with the `GET /geo/places` path; no errors.

- [ ] **Step 12: Commit**

```bash
git add src/Geo/UI config/services/geo.yaml tests/Geo/UI openapi.yaml
git commit -m "feat(geo): add public GET /geo/places autocomplete endpoint with integer-id serializer"
```

---

## Task 5: GeoNames import console command

**Files:**
- Create: `src/Geo/UI/Console/ImportGeoPlacesCommand.php`
- Test: `tests/Geo/Functional/Console/ImportGeoPlacesCommandTest.php`

**Interfaces:**
- Consumes: `App\Geo\Domain\Port\GeoPlaceWriterInterface::upsert(GeoPlaceId $id, string $name, string $asciiName, string $countryCode, ?string $admin1Code): void` (Task 2).
- Produces: console command `geo:import-places <file>`, parsing a GeoNames tab-separated cities dump (columns: `geonameid`, `name`, `asciiname`, ..., `country code` at index 8, ..., `admin1 code` at index 10) and upserting each row.

- [ ] **Step 1: Write the failing functional test**

`tests/Geo/Functional/Console/ImportGeoPlacesCommandTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Geo\Functional\Console;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('functional')]
final class ImportGeoPlacesCommandTest extends KernelTestCase
{
    private Connection $geoConnection;

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = static::getContainer()->get('doctrine.dbal.geo_connection');
        \assert($connection instanceof Connection);
        $this->geoConnection = $connection;
        $this->geoConnection->executeStatement('TRUNCATE geo_place');
    }

    #[Test]
    public function itImportsPlacesFromADumpFile(): void
    {
        $fixturePath = sys_get_temp_dir() . '/geo_places_fixture.txt';
        file_put_contents(
            $fixturePath,
            "2988507\tParis\tParis\tParis,Pariz\t48.85341\t2.3488\tP\tPPLC\tFR\t\t11\t75\t751\t75056\t2138551\t\t42\tEurope/Paris\t2024-01-01\n"
            . "4717560\tParis\tParis\t\t33.66094\t-95.55551\tP\tPPL\tUS\t\tTX\t\t\t\t25171\t\t136\tAmerica/Chicago\t2024-01-01\n",
        );

        $application = new Application(self::$kernel);
        $command = $application->find('geo:import-places');
        $tester = new CommandTester($command);
        $tester->execute(['file' => $fixturePath]);

        $tester->assertCommandIsSuccessful();
        unlink($fixturePath);

        $rows = $this->geoConnection->fetchAllAssociative('SELECT geoname_id, name, country_code, admin1_code FROM geo_place ORDER BY geoname_id');
        self::assertCount(2, $rows);
        self::assertSame('FR', $rows[0]['country_code']);
        self::assertSame('11', $rows[0]['admin1_code']);
        self::assertSame('US', $rows[1]['country_code']);
        self::assertSame('TX', $rows[1]['admin1_code']);
    }

    #[Test]
    public function itFailsWhenFileDoesNotExist(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('geo:import-places');
        $tester = new CommandTester($command);
        $tester->execute(['file' => '/nonexistent/path.txt']);

        self::assertSame(1, $tester->getStatusCode());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `make functional-test`
Expected: FAIL — command `geo:import-places` does not exist.

- [ ] **Step 3: Write the command**

`src/Geo/UI/Console/ImportGeoPlacesCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Geo\UI\Console;

use App\Geo\Domain\Port\GeoPlaceWriterInterface;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'geo:import-places', description: 'Import Geo Places from a GeoNames cities dump file (e.g. cities500.txt)')]
final class ImportGeoPlacesCommand extends Command
{
    public function __construct(private readonly GeoPlaceWriterInterface $writer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to the GeoNames tab-separated dump file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $path */
        $path = $input->getArgument('file');

        if (!is_file($path)) {
            $output->writeln("<error>File not found: {$path}</error>");

            return Command::FAILURE;
        }

        $handle = fopen($path, 'r');
        if (false === $handle) {
            $output->writeln("<error>Unable to open file: {$path}</error>");

            return Command::FAILURE;
        }

        $count = 0;
        while (false !== ($line = fgets($handle))) {
            $line = rtrim($line, "\n");
            if ('' === $line) {
                continue;
            }

            $columns = explode("\t", $line);

            $this->writer->upsert(
                id: new GeoPlaceId($columns[0]),
                name: $columns[1],
                asciiName: $columns[2],
                countryCode: $columns[8],
                admin1Code: '' !== $columns[10] ? $columns[10] : null,
            );
            ++$count;
        }

        fclose($handle);

        $output->writeln("Imported {$count} Geo Places.");

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `make functional-test`
Expected: PASS.

- [ ] **Step 5: Run the full lint and test suite**

Run: `make lint && make test`
Expected: CS Fixer, PHPStan, Deptrac all pass; full unit + functional test suite passes.

- [ ] **Step 6: Commit**

```bash
git add src/Geo/UI/Console tests/Geo/Functional
git commit -m "feat(geo): add geo:import-places console command for GeoNames dump import"
```

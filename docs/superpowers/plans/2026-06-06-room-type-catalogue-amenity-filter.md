# Room Type Catalogue — Amenity Filter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose a booker-facing `GET /hotels/{hotelId}/room-type-catalogue` endpoint that returns a paginated list of Room Types, filterable by one or more Room Amenities (AND logic — must have all requested amenities).

**Architecture:** New use case `ListRoomTypesByAmenity` (Query + Handler) backed by a dedicated read port `RoomTypeCatalogueFinderInterface`, implemented by a DBAL finder using PostgreSQL array containment (`@>`). The existing operator endpoint (`/room-types`) is untouched; the new endpoint is purely booker-facing, as specified in ADR 0015.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL (raw SQL), PostgreSQL 16 (`text[]` + `@>` operator), PHPUnit, NelmioApiDocBundle.

---

## File Map

| Action | Path | Purpose |
|--------|------|---------|
| Create | `src/Room/Domain/Port/RoomTypeCatalogueFinderInterface.php` | Port — read-only contract for Room Type Catalogue queries |
| Create | `src/Room/Application/UseCase/ListRoomTypesByAmenity/ListRoomTypesByAmenityQuery.php` | Query message |
| Create | `src/Room/Application/UseCase/ListRoomTypesByAmenity/ListRoomTypesByAmenityQueryHandler.php` | Handler — delegates to finder |
| Create | `src/Room/Infrastructure/Persistence/Doctrine/DbalRoomTypeCatalogueFinder.php` | DBAL implementation — SQL with `@>` filter |
| Create | `src/Room/UI/Http/Controller/ListRoomTypesByAmenity/ListRoomTypesByAmenityRequest.php` | Query string DTO (`amenities[]`, `page`, `limit`) |
| Create | `src/Room/UI/Http/Controller/ListRoomTypesByAmenity/ListRoomTypesByAmenityController.php` | Controller — `GET /hotels/{hotelId}/room-type-catalogue` |
| Modify | `config/services/room.yaml` | Bind `RoomTypeCatalogueFinderInterface` → `DbalRoomTypeCatalogueFinder` |
| Create | `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomTypeCatalogueFinder.php` | Test double for unit tests |
| Create | `tests/Room/Application/UseCase/ListRoomTypesByAmenity/ListRoomTypesByAmenityQueryHandlerTest.php` | Unit tests (Group: unit) |
| Create | `tests/Room/UI/Http/Controller/ListRoomTypesByAmenity/ListRoomTypesByAmenityControllerTest.php` | Functional tests (Group: functional) |

---

## Task 1: Create branch

- [ ] **Step 1: Create and switch to feature branch**

```bash
git checkout -b feat/room-type-catalogue-amenity-filter
```

Expected: `Switched to a new branch 'feat/room-type-catalogue-amenity-filter'`

---

## Task 2: Domain port

**Files:**
- Create: `src/Room/Domain/Port/RoomTypeCatalogueFinderInterface.php`

- [ ] **Step 1: Create the port interface**

```php
<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Room\Domain\Model\RoomTypePage;

interface RoomTypeCatalogueFinderInterface
{
    /** @param string[] $amenities */
    public function find(string $hotelId, array $amenities, int $page, int $limit): RoomTypePage;
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Room/Domain/Port/RoomTypeCatalogueFinderInterface.php
git commit -m "feat(room): add RoomTypeCatalogueFinderInterface port"
```

---

## Task 3: Application layer — Query, Handler, and unit tests

**Files:**
- Create: `src/Room/Application/UseCase/ListRoomTypesByAmenity/ListRoomTypesByAmenityQuery.php`
- Create: `src/Room/Application/UseCase/ListRoomTypesByAmenity/ListRoomTypesByAmenityQueryHandler.php`
- Create: `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomTypeCatalogueFinder.php`
- Create: `tests/Room/Application/UseCase/ListRoomTypesByAmenity/ListRoomTypesByAmenityQueryHandlerTest.php`

- [ ] **Step 1: Create the in-memory test double**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Infrastructure\Persistence\InMemory;

use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Model\RoomTypePage;
use App\Room\Domain\Port\RoomTypeCatalogueFinderInterface;

final class InMemoryRoomTypeCatalogueFinder implements RoomTypeCatalogueFinderInterface
{
    /** @var array<string, RoomType> */
    private array $roomTypes = [];

    public function add(RoomType $roomType): void
    {
        $this->roomTypes[$roomType->id] = $roomType;
    }

    /** @param string[] $amenities */
    public function find(string $hotelId, array $amenities, int $page, int $limit): RoomTypePage
    {
        $filtered = array_values(array_filter(
            $this->roomTypes,
            static function (RoomType $rt) use ($hotelId, $amenities): bool {
                if ($rt->hotelId !== $hotelId) {
                    return false;
                }
                $declared = array_map(static fn($a) => $a->value, $rt->amenities);
                foreach ($amenities as $required) {
                    if (!in_array($required, $declared, true)) {
                        return false;
                    }
                }

                return true;
            },
        ));

        usort($filtered, static fn(RoomType $a, RoomType $b) => strcmp($a->name, $b->name));
        $total = count($filtered);
        $slice = array_slice($filtered, ($page - 1) * $limit, $limit);

        return new RoomTypePage($slice, $total);
    }
}
```

- [ ] **Step 2: Write the failing unit test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\ListRoomTypesByAmenity;

use App\Room\Application\UseCase\ListRoomTypesByAmenity\ListRoomTypesByAmenityQuery;
use App\Room\Application\UseCase\ListRoomTypesByAmenity\ListRoomTypesByAmenityQueryHandler;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommandHandler;
use App\Room\Application\UseCase\DeclareRoomTypeAmenities\DeclareRoomTypeAmenitiesCommand;
use App\Room\Application\UseCase\DeclareRoomTypeAmenities\DeclareRoomTypeAmenitiesCommandHandler;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeCatalogueFinder;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ListRoomTypesByAmenityQueryHandlerTest extends TestCase
{
    private const string HOTEL_ID = '550e8400-e29b-41d4-a716-446655440000';
    private const string RT_WIFI_BALCONY = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380001';
    private const string RT_WIFI_ONLY = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380002';
    private const string RT_NO_AMENITIES = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380003';

    private InMemoryRoomTypeCatalogueFinder $finder;
    private ListRoomTypesByAmenityQueryHandler $handler;

    protected function setUp(): void
    {
        $repository = new InMemoryRoomTypeRepository();
        $this->finder = new InMemoryRoomTypeCatalogueFinder();

        $registerHandler = new RegisterRoomTypeCommandHandler(
            $repository,
            new FakeHotelExistenceChecker(),
            new FakeEventDispatcher(),
        );
        $amenitiesHandler = new DeclareRoomTypeAmenitiesCommandHandler(
            $repository,
            new FakeEventDispatcher(),
        );

        ($registerHandler)(new RegisterRoomTypeCommand(
            id: self::RT_WIFI_BALCONY,
            hotelId: self::HOTEL_ID,
            name: 'Suite Balcony',
            livingSpaceCount: 2,
            surfaceM2: null,
            guestCapacity: 2,
            isAccessible: false,
            bedEntries: [['type' => 'double', 'count' => 1]],
            createdAt: new \DateTimeImmutable(),
        ));
        ($amenitiesHandler)(new DeclareRoomTypeAmenitiesCommand(self::RT_WIFI_BALCONY, ['wifi', 'balcony']));

        ($registerHandler)(new RegisterRoomTypeCommand(
            id: self::RT_WIFI_ONLY,
            hotelId: self::HOTEL_ID,
            name: 'Standard',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 2,
            isAccessible: false,
            bedEntries: [['type' => 'single', 'count' => 1]],
            createdAt: new \DateTimeImmutable(),
        ));
        ($amenitiesHandler)(new DeclareRoomTypeAmenitiesCommand(self::RT_WIFI_ONLY, ['wifi']));

        ($registerHandler)(new RegisterRoomTypeCommand(
            id: self::RT_NO_AMENITIES,
            hotelId: self::HOTEL_ID,
            name: 'Basic',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 1,
            isAccessible: false,
            bedEntries: [['type' => 'single', 'count' => 1]],
            createdAt: new \DateTimeImmutable(),
        ));

        // Sync registered room types into the finder
        foreach ([$repository->get(self::RT_WIFI_BALCONY), $repository->get(self::RT_WIFI_ONLY), $repository->get(self::RT_NO_AMENITIES)] as $rt) {
            if (null !== $rt) {
                $this->finder->add($rt);
            }
        }

        $this->handler = new ListRoomTypesByAmenityQueryHandler($this->finder);
    }

    #[Test]
    public function itReturnsAllRoomTypesWhenNoAmenityFilterGiven(): void
    {
        $page = ($this->handler)(new ListRoomTypesByAmenityQuery(self::HOTEL_ID, [], 1, 20));

        self::assertSame(3, $page->total);
    }

    #[Test]
    public function itFiltersRoomTypesByASingleAmenity(): void
    {
        $page = ($this->handler)(new ListRoomTypesByAmenityQuery(self::HOTEL_ID, ['wifi'], 1, 20));

        self::assertSame(2, $page->total);
        self::assertSame('Standard', $page->roomTypes[0]->name);
        self::assertSame('Suite Balcony', $page->roomTypes[1]->name);
    }

    #[Test]
    public function itFiltersRoomTypesByMultipleAmenitiesWithAndLogic(): void
    {
        $page = ($this->handler)(new ListRoomTypesByAmenityQuery(self::HOTEL_ID, ['wifi', 'balcony'], 1, 20));

        self::assertSame(1, $page->total);
        self::assertSame('Suite Balcony', $page->roomTypes[0]->name);
    }

    #[Test]
    public function itReturnsEmptyPageWhenNoRoomTypeMatchesAllAmenities(): void
    {
        $page = ($this->handler)(new ListRoomTypesByAmenityQuery(self::HOTEL_ID, ['wifi', 'balcony', 'jacuzzi'], 1, 20));

        self::assertSame(0, $page->total);
        self::assertCount(0, $page->roomTypes);
    }
}
```

- [ ] **Step 3: Run the test to verify it fails (class not found)**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Room/Application/UseCase/ListRoomTypesByAmenity/ --group unit
```

Expected: error — `Class "App\Room\Application\UseCase\ListRoomTypesByAmenity\ListRoomTypesByAmenityQueryHandler" not found`

- [ ] **Step 4: Create the Query class**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\ListRoomTypesByAmenity;

use App\Room\Domain\Model\RoomTypePage;
use App\Shared\Application\Bus\SyncQueryInterface;

/**
 * @implements SyncQueryInterface<RoomTypePage>
 */
final readonly class ListRoomTypesByAmenityQuery implements SyncQueryInterface
{
    /** @param string[] $amenities */
    public function __construct(
        public string $hotelId,
        public array $amenities,
        public int $page,
        public int $limit,
    ) {
    }
}
```

- [ ] **Step 5: Create the QueryHandler**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\ListRoomTypesByAmenity;

use App\Room\Domain\Model\RoomTypePage;
use App\Room\Domain\Port\RoomTypeCatalogueFinderInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class ListRoomTypesByAmenityQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private RoomTypeCatalogueFinderInterface $finder)
    {
    }

    public function __invoke(ListRoomTypesByAmenityQuery $query): RoomTypePage
    {
        return $this->finder->find($query->hotelId, $query->amenities, $query->page, $query->limit);
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Room/Application/UseCase/ListRoomTypesByAmenity/ --group unit
```

Expected: 4 tests, 4 assertions — OK

- [ ] **Step 7: Commit**

```bash
git add \
  src/Room/Application/UseCase/ListRoomTypesByAmenity/ \
  tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomTypeCatalogueFinder.php \
  tests/Room/Application/UseCase/ListRoomTypesByAmenity/
git commit -m "feat(room): add ListRoomTypesByAmenity query and handler"
```

---

## Task 4: Infrastructure — DBAL finder + DI wiring

**Files:**
- Create: `src/Room/Infrastructure/Persistence/Doctrine/DbalRoomTypeCatalogueFinder.php`
- Modify: `config/services/room.yaml`

- [ ] **Step 1: Create the DBAL finder**

The `amenities` column is a PostgreSQL `text[]`. The `@>` operator checks containment: `amenities @> '{wifi,balcony}'::text[]` returns true if the array contains all listed values.

```php
<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Model\RoomTypePage;
use App\Room\Domain\Port\RoomTypeCatalogueFinderInterface;
use App\Room\Domain\ValueObject\BedComposition;
use App\Room\Domain\ValueObject\RoomAmenity;
use Doctrine\DBAL\Connection;

final readonly class DbalRoomTypeCatalogueFinder implements RoomTypeCatalogueFinderInterface
{
    public function __construct(private Connection $roomConnection)
    {
    }

    /** @param string[] $amenities */
    public function find(string $hotelId, array $amenities, int $page, int $limit): RoomTypePage
    {
        $whereClause = 'WHERE hotel_id = :hotelId';
        $params = ['hotelId' => $hotelId];

        if ([] !== $amenities) {
            $whereClause .= ' AND amenities @> :filter::text[]';
            $params['filter'] = '{' . implode(',', $amenities) . '}';
        }

        /** @var int|string $count */
        $count = $this->roomConnection->fetchOne(
            "SELECT COUNT(*) FROM room_type {$whereClause}",
            $params,
        );
        $total = (int) $count;

        /** @var list<array{id: string, hotel_id: string, name: string, living_space_count: int|string, surface_m2: int|string|null, guest_capacity: int|string, is_accessible: string|bool, bed_composition: string, amenities: string, created_at: string}> $rows */
        $rows = $this->roomConnection->fetchAllAssociative(
            "SELECT id, hotel_id, name, living_space_count, surface_m2, guest_capacity, is_accessible, bed_composition, amenities, created_at FROM room_type {$whereClause} ORDER BY name ASC LIMIT :limit OFFSET :offset",
            array_merge($params, ['limit' => $limit, 'offset' => ($page - 1) * $limit]),
        );

        return new RoomTypePage(array_map($this->hydrate(...), $rows), $total);
    }

    /**
     * @param array{id: string, hotel_id: string, name: string, living_space_count: int|string, surface_m2: int|string|null, guest_capacity: int|string, is_accessible: string|bool, bed_composition: string, amenities: string, created_at: string} $row
     */
    private function hydrate(array $row): RoomType
    {
        /** @var list<array{type: string, count: int}> $bedData */
        $bedData = json_decode($row['bed_composition'], true, 512, \JSON_THROW_ON_ERROR);

        return new RoomType(
            $row['id'],
            $row['hotel_id'],
            $row['name'],
            (int) $row['living_space_count'],
            null !== $row['surface_m2'] ? (int) $row['surface_m2'] : null,
            (int) $row['guest_capacity'],
            't' === $row['is_accessible'] || true === $row['is_accessible'],
            BedComposition::fromArray($bedData),
            new \DateTimeImmutable($row['created_at']),
            $this->parseAmenities($row['amenities']),
        );
    }

    /** @return array<RoomAmenity> */
    private function parseAmenities(string $raw): array
    {
        if ('{}' === $raw) {
            return [];
        }

        preg_match_all('/"([^"]+)"|([^,{}]+)/', $raw, $matches);
        $values = array_map(
            static fn(string $quoted, string $plain): string => '' !== $quoted ? $quoted : $plain,
            $matches[1],
            $matches[2],
        );

        return array_map(RoomAmenity::from(...), $values);
    }
}
```

- [ ] **Step 2: Bind the port in `config/services/room.yaml`**

Add after the existing `App\Room\Domain\Port\RoomCapacityFinderInterface:` binding:

```yaml
    App\Room\Domain\Port\RoomTypeCatalogueFinderInterface:
        class: App\Room\Infrastructure\Persistence\Doctrine\DbalRoomTypeCatalogueFinder
```

- [ ] **Step 3: Commit**

```bash
git add \
  src/Room/Infrastructure/Persistence/Doctrine/DbalRoomTypeCatalogueFinder.php \
  config/services/room.yaml
git commit -m "feat(room): add DbalRoomTypeCatalogueFinder with amenity AND filter"
```

---

## Task 5: UI — Controller, Request DTO, and functional tests

**Files:**
- Create: `src/Room/UI/Http/Controller/ListRoomTypesByAmenity/ListRoomTypesByAmenityRequest.php`
- Create: `src/Room/UI/Http/Controller/ListRoomTypesByAmenity/ListRoomTypesByAmenityController.php`
- Create: `tests/Room/UI/Http/Controller/ListRoomTypesByAmenity/ListRoomTypesByAmenityControllerTest.php`

- [ ] **Step 1: Write the failing functional test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\ListRoomTypesByAmenity;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class ListRoomTypesByAmenityControllerTest extends WebTestCase
{
    private const array HOTEL_PAYLOAD = [
        'name' => 'Hotel Filtre',
        'streetAddress' => '5 rue des Lilas',
        'postalCode' => '69001',
        'city' => 'Lyon',
        'country' => 'FR',
    ];

    #[Test]
    public function itReturnsAllRoomTypesWhenNoAmenityFilterGiven(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Suite', ['wifi', 'balcony']);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Standard', ['wifi']);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Basic', []);

        $client->request('GET', "/api/v1/hotels/{$hotelId}/room-type-catalogue");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{name: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(3, $body['meta']['total']);
    }

    #[Test]
    public function itFiltersByASingleAmenityAndReturnsSortedResults(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Suite', ['wifi', 'balcony']);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Standard', ['wifi']);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Basic', []);

        $client->request('GET', "/api/v1/hotels/{$hotelId}/room-type-catalogue?amenities[]=wifi");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{name: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(2, $body['meta']['total']);
        self::assertSame('Standard', $body['data'][0]['name']);
        self::assertSame('Suite', $body['data'][1]['name']);
    }

    #[Test]
    public function itFiltersByMultipleAmenitiesWithAndLogic(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Suite', ['wifi', 'balcony']);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Standard', ['wifi']);
        $this->registerRoomTypeWithAmenities($client, $hotelId, 'Basic', []);

        $client->request('GET', "/api/v1/hotels/{$hotelId}/room-type-catalogue?amenities[]=wifi&amenities[]=balcony");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{name: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['meta']['total']);
        self::assertSame('Suite', $body['data'][0]['name']);
    }

    #[Test]
    public function itRejects422ForAnInvalidAmenityValue(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);

        $client->request('GET', "/api/v1/hotels/{$hotelId}/room-type-catalogue?amenities[]=not_a_real_amenity");

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    private function registerHotel(KernelBrowser $client): string
    {
        $client->request('POST', '/api/v1/hotels', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    /** @param string[] $amenities */
    private function registerRoomTypeWithAmenities(KernelBrowser $client, string $hotelId, string $name, array $amenities): string
    {
        $payload = ['name' => $name, 'livingSpaceCount' => 1, 'guestCapacity' => 2, 'isAccessible' => false, 'bedComposition' => [['type' => 'double', 'count' => 1]]];
        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $rt */
        $rt = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $roomTypeId = $rt['id'];

        if ([] !== $amenities) {
            $client->request('PATCH', "/api/v1/hotels/{$hotelId}/room-types/{$roomTypeId}/amenities", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['amenities' => $amenities], \JSON_THROW_ON_ERROR));
        }

        return $roomTypeId;
    }
}
```

- [ ] **Step 2: Run the test to verify it fails (route not found)**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Room/UI/Http/Controller/ListRoomTypesByAmenity/ --group functional
```

Expected: FAIL — 404 Not Found (route doesn't exist yet)

- [ ] **Step 3: Create the Request DTO**

```php
<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\ListRoomTypesByAmenity;

use App\Room\Domain\ValueObject\RoomAmenity;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListRoomTypesByAmenityRequest
{
    public function __construct(
        #[Assert\All([new Assert\Choice(choices: RoomAmenity::values())])]
        #[OA\Parameter(
            name: 'amenities[]',
            in: 'query',
            schema: new OA\Schema(
                type: 'array',
                items: new OA\Items(type: 'string', enum: RoomAmenity::values()),
            ),
        )]
        public array $amenities = [],
        #[Assert\GreaterThanOrEqual(1)]
        #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1))]
        public int $page = 1,
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(100)]
        #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1))]
        public int $limit = 20,
    ) {
    }
}
```

- [ ] **Step 4: Create the Controller**

```php
<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\ListRoomTypesByAmenity;

use App\Room\Application\UseCase\ListRoomTypesByAmenity\ListRoomTypesByAmenityQuery;
use App\Room\UI\Http\Controller\ListRoomTypes\RoomTypeCatalogueSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class ListRoomTypesByAmenityController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private RoomTypeCatalogueSerializer $serializer,
    ) {
    }

    #[Route(
        path: '/hotels/{hotelId}/room-type-catalogue',
        name: 'room_list_room_type_catalogue',
        requirements: ['hotelId' => Requirement::UUID_V4],
        methods: ['GET'],
    )]
    #[OA\Get(
        summary: 'List Room Types of a Hotel — Booker-facing catalogue, filterable by Room Amenities',
        tags: ['Room Types'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Paginated Room Type Catalogue',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'hotelId', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'name', type: 'string', example: 'Suite Balcony'),
                                    new OA\Property(property: 'livingSpaceCount', type: 'integer', example: 2),
                                    new OA\Property(property: 'surfaceM2', type: 'integer', nullable: true, example: 45),
                                    new OA\Property(property: 'guestCapacity', type: 'integer', example: 2),
                                    new OA\Property(property: 'isAccessible', type: 'boolean'),
                                    new OA\Property(
                                        property: 'bedComposition',
                                        type: 'array',
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(property: 'type', type: 'string', example: 'double'),
                                                new OA\Property(property: 'count', type: 'integer', example: 1),
                                            ],
                                            type: 'object',
                                        ),
                                    ),
                                    new OA\Property(property: 'amenities', type: 'array', items: new OA\Items(type: 'string'), example: ['wifi', 'balcony']),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                                ],
                                type: 'object',
                            ),
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [
                                new OA\Property(property: 'page', type: 'integer', example: 1),
                                new OA\Property(property: 'limit', type: 'integer', example: 20),
                                new OA\Property(property: 'total', type: 'integer', example: 3),
                                new OA\Property(property: 'totalPages', type: 'integer', example: 1),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_UNPROCESSABLE_ENTITY,
                description: 'Validation error',
                content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail')),
            ),
        ],
    )]
    public function __invoke(
        string $hotelId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ListRoomTypesByAmenityRequest $request = new ListRoomTypesByAmenityRequest(),
    ): Response {
        $page = $this->queryBus->ask(new ListRoomTypesByAmenityQuery($hotelId, $request->amenities, $request->page, $request->limit));

        return new JsonResponse($this->serializer->serialize($page, $request->page, $request->limit));
    }
}
```

- [ ] **Step 5: Run the functional tests**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Room/UI/Http/Controller/ListRoomTypesByAmenity/ --group functional
```

Expected: 4 tests — OK

- [ ] **Step 6: Run the full test suite to check for regressions**

```bash
make test
```

Expected: all green

- [ ] **Step 7: Run static analysis**

```bash
make lint
```

Expected: no errors

- [ ] **Step 8: Regenerate OpenAPI spec**

```bash
make openapi
```

- [ ] **Step 9: Commit**

```bash
git add \
  src/Room/UI/Http/Controller/ListRoomTypesByAmenity/ \
  tests/Room/UI/Http/Controller/ListRoomTypesByAmenity/ \
  openapi.yaml
git commit -m "feat(room): add GET /hotels/{hotelId}/room-type-catalogue with amenity filter"
```

---

## Task 6: Open Pull Request

- [ ] **Step 1: Push the branch**

```bash
git push -u origin feat/room-type-catalogue-amenity-filter
```

- [ ] **Step 2: Open PR**

```bash
gh pr create \
  --title "feat(room): Room Type Catalogue with amenity filter" \
  --body "$(cat <<'EOF'
## Summary
- Adds booker-facing `GET /hotels/{hotelId}/room-type-catalogue` endpoint
- Supports filtering by one or more Room Amenities (AND logic — must have all)
- New `RoomTypeCatalogueFinderInterface` port + `DbalRoomTypeCatalogueFinder` implementation (PostgreSQL `@>` array containment)
- Separate use case from operator's `/room-types` list — per ADR 0015

## Test plan
- [ ] Unit tests: `make unit-test` — 4 tests covering no-filter, single amenity, AND multi-amenity, no-match
- [ ] Functional tests: `make functional-test` — 4 tests covering same scenarios + 422 for invalid amenity
- [ ] Static analysis: `make lint` — no errors
- [ ] OpenAPI spec regenerated with new endpoint

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

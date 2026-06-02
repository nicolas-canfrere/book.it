# Amenities Catalogue Endpoints Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `GET /api/v1/hotel-amenities` and `GET /api/v1/room-type-amenities` endpoints that return the full catalogue of possible amenity values from the PHP enums.

**Architecture:** Full CQRS pattern — a `SyncQueryInterface` + `SyncQueryHandlerInterface` pair per context, wired through the `SyncQueryBusInterface` in the controller. The handlers have no repository dependency: they read the enum directly via `::values()`.

**Tech Stack:** PHP 8.4, Symfony 8, Symfony Messenger (sync query bus), OpenAPI attributes (NelmioApiDocBundle)

---

## File Map

### Hotel context — new files
| File | Role |
|------|------|
| `src/Hotel/Application/UseCase/GetHotelAmenities/GetHotelAmenitiesQuery.php` | Query DTO |
| `src/Hotel/Application/UseCase/GetHotelAmenities/GetHotelAmenitiesQueryHandler.php` | Returns `HotelAmenity::values()` |
| `src/Hotel/UI/Http/Controller/GetHotelAmenities/GetHotelAmenitiesController.php` | `GET /hotel-amenities` |
| `tests/Hotel/Application/UseCase/GetHotelAmenities/GetHotelAmenitiesQueryHandlerTest.php` | Unit test |
| `tests/Hotel/UI/Http/Controller/GetHotelAmenities/GetHotelAmenitiesControllerTest.php` | Functional test |

### Room context — new files
| File | Role |
|------|------|
| `src/Room/Application/UseCase/GetRoomTypeAmenities/GetRoomTypeAmenitiesQuery.php` | Query DTO |
| `src/Room/Application/UseCase/GetRoomTypeAmenities/GetRoomTypeAmenitiesQueryHandler.php` | Returns `RoomAmenity::values()` |
| `src/Room/UI/Http/Controller/GetRoomTypeAmenities/GetRoomTypeAmenitiesController.php` | `GET /room-type-amenities` |
| `tests/Room/Application/UseCase/GetRoomTypeAmenities/GetRoomTypeAmenitiesQueryHandlerTest.php` | Unit test |
| `tests/Room/UI/Http/Controller/GetRoomTypeAmenities/GetRoomTypeAmenitiesControllerTest.php` | Functional test |

### Modified files
| File | Change |
|------|--------|
| `openapi.yaml` | Regenerated via `make openapi` at the end |

---

## Task 1: Create branch

- [ ] **Create feature branch**

```bash
git checkout -b feat/amenities-catalogue-endpoints
```

---

## Task 2: Hotel — Query + QueryHandler with unit test

**Files:**
- Create: `src/Hotel/Application/UseCase/GetHotelAmenities/GetHotelAmenitiesQuery.php`
- Create: `src/Hotel/Application/UseCase/GetHotelAmenities/GetHotelAmenitiesQueryHandler.php`
- Create: `tests/Hotel/Application/UseCase/GetHotelAmenities/GetHotelAmenitiesQueryHandlerTest.php`

- [ ] **Write the failing unit test**

```php
<?php
// tests/Hotel/Application/UseCase/GetHotelAmenities/GetHotelAmenitiesQueryHandlerTest.php
declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\GetHotelAmenities;

use App\Hotel\Application\UseCase\GetHotelAmenities\GetHotelAmenitiesQuery;
use App\Hotel\Application\UseCase\GetHotelAmenities\GetHotelAmenitiesQueryHandler;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetHotelAmenitiesQueryHandlerTest extends TestCase
{
    #[Test]
    public function itReturnsAllHotelAmenityValues(): void
    {
        $handler = new GetHotelAmenitiesQueryHandler();

        $result = ($handler)(new GetHotelAmenitiesQuery());

        self::assertSame(HotelAmenity::values(), $result);
        self::assertContains('pool', $result);
        self::assertContains('parking', $result);
    }
}
```

- [ ] **Run test to verify it fails**

```bash
make unit-test
```
Expected: FAIL — class `GetHotelAmenitiesQuery` not found.

- [ ] **Create the Query class**

```php
<?php
// src/Hotel/Application/UseCase/GetHotelAmenities/GetHotelAmenitiesQuery.php
declare(strict_types=1);

namespace App\Hotel\Application\UseCase\GetHotelAmenities;

use App\Shared\Application\Bus\SyncQueryInterface;

/**
 * @implements SyncQueryInterface<string[]>
 */
final readonly class GetHotelAmenitiesQuery implements SyncQueryInterface
{
}
```

- [ ] **Create the QueryHandler class**

```php
<?php
// src/Hotel/Application/UseCase/GetHotelAmenities/GetHotelAmenitiesQueryHandler.php
declare(strict_types=1);

namespace App\Hotel\Application\UseCase\GetHotelAmenities;

use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetHotelAmenitiesQueryHandler implements SyncQueryHandlerInterface
{
    /** @return string[] */
    public function __invoke(GetHotelAmenitiesQuery $query): array
    {
        return HotelAmenity::values();
    }
}
```

- [ ] **Run test to verify it passes**

```bash
make unit-test
```
Expected: PASS.

- [ ] **Commit**

```bash
git add src/Hotel/Application/UseCase/GetHotelAmenities/ tests/Hotel/Application/UseCase/GetHotelAmenities/
git commit -m "feat(hotel): add GetHotelAmenitiesQuery and QueryHandler"
```

---

## Task 3: Hotel — Controller with functional test

**Files:**
- Create: `src/Hotel/UI/Http/Controller/GetHotelAmenities/GetHotelAmenitiesController.php`
- Create: `tests/Hotel/UI/Http/Controller/GetHotelAmenities/GetHotelAmenitiesControllerTest.php`

- [ ] **Write the failing functional test**

```php
<?php
// tests/Hotel/UI/Http/Controller/GetHotelAmenities/GetHotelAmenitiesControllerTest.php
declare(strict_types=1);

namespace App\Tests\Hotel\UI\Http\Controller\GetHotelAmenities;

use App\Hotel\Domain\ValueObject\HotelAmenity;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetHotelAmenitiesControllerTest extends WebTestCase
{
    #[Test]
    public function itReturnsAllHotelAmenities(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/hotel-amenities');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));

        /** @var array{amenities: string[]} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('amenities', $body);
        self::assertEqualsCanonicalizing(HotelAmenity::values(), $body['amenities']);
    }
}
```

- [ ] **Run test to verify it fails**

```bash
make functional-test
```
Expected: FAIL — 404 (route not registered).

- [ ] **Create the Controller**

```php
<?php
// src/Hotel/UI/Http/Controller/GetHotelAmenities/GetHotelAmenitiesController.php
declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\GetHotelAmenities;

use App\Hotel\Application\UseCase\GetHotelAmenities\GetHotelAmenitiesQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetHotelAmenitiesController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
    ) {
    }

    #[Route(path: '/hotel-amenities', name: 'hotel_get_amenities_catalogue', methods: ['GET'])]
    #[OA\Get(
        summary: 'List all possible hotel amenities',
        tags: ['Hotels'],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Amenities catalogue',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'amenities',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            example: ['pool', 'spa', 'gym'],
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(): Response
    {
        /** @var string[] $amenities */
        $amenities = $this->queryBus->ask(new GetHotelAmenitiesQuery());

        return new JsonResponse(['amenities' => $amenities]);
    }
}
```

- [ ] **Run test to verify it passes**

```bash
make functional-test
```
Expected: PASS.

- [ ] **Commit**

```bash
git add src/Hotel/UI/Http/Controller/GetHotelAmenities/ tests/Hotel/UI/Http/Controller/GetHotelAmenities/
git commit -m "feat(hotel): add GET /hotel-amenities catalogue endpoint"
```

---

## Task 4: Room — Query + QueryHandler with unit test

**Files:**
- Create: `src/Room/Application/UseCase/GetRoomTypeAmenities/GetRoomTypeAmenitiesQuery.php`
- Create: `src/Room/Application/UseCase/GetRoomTypeAmenities/GetRoomTypeAmenitiesQueryHandler.php`
- Create: `tests/Room/Application/UseCase/GetRoomTypeAmenities/GetRoomTypeAmenitiesQueryHandlerTest.php`

- [ ] **Write the failing unit test**

```php
<?php
// tests/Room/Application/UseCase/GetRoomTypeAmenities/GetRoomTypeAmenitiesQueryHandlerTest.php
declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\GetRoomTypeAmenities;

use App\Room\Application\UseCase\GetRoomTypeAmenities\GetRoomTypeAmenitiesQuery;
use App\Room\Application\UseCase\GetRoomTypeAmenities\GetRoomTypeAmenitiesQueryHandler;
use App\Room\Domain\ValueObject\RoomAmenity;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetRoomTypeAmenitiesQueryHandlerTest extends TestCase
{
    #[Test]
    public function itReturnsAllRoomAmenityValues(): void
    {
        $handler = new GetRoomTypeAmenitiesQueryHandler();

        $result = ($handler)(new GetRoomTypeAmenitiesQuery());

        self::assertSame(RoomAmenity::values(), $result);
        self::assertContains('wifi', $result);
        self::assertContains('balcony', $result);
    }
}
```

- [ ] **Run test to verify it fails**

```bash
make unit-test
```
Expected: FAIL — class `GetRoomTypeAmenitiesQuery` not found.

- [ ] **Create the Query class**

```php
<?php
// src/Room/Application/UseCase/GetRoomTypeAmenities/GetRoomTypeAmenitiesQuery.php
declare(strict_types=1);

namespace App\Room\Application\UseCase\GetRoomTypeAmenities;

use App\Shared\Application\Bus\SyncQueryInterface;

/**
 * @implements SyncQueryInterface<string[]>
 */
final readonly class GetRoomTypeAmenitiesQuery implements SyncQueryInterface
{
}
```

- [ ] **Create the QueryHandler class**

```php
<?php
// src/Room/Application/UseCase/GetRoomTypeAmenities/GetRoomTypeAmenitiesQueryHandler.php
declare(strict_types=1);

namespace App\Room\Application\UseCase\GetRoomTypeAmenities;

use App\Room\Domain\ValueObject\RoomAmenity;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetRoomTypeAmenitiesQueryHandler implements SyncQueryHandlerInterface
{
    /** @return string[] */
    public function __invoke(GetRoomTypeAmenitiesQuery $query): array
    {
        return RoomAmenity::values();
    }
}
```

- [ ] **Run test to verify it passes**

```bash
make unit-test
```
Expected: PASS.

- [ ] **Commit**

```bash
git add src/Room/Application/UseCase/GetRoomTypeAmenities/ tests/Room/Application/UseCase/GetRoomTypeAmenities/
git commit -m "feat(room): add GetRoomTypeAmenitiesQuery and QueryHandler"
```

---

## Task 5: Room — Controller with functional test

**Files:**
- Create: `src/Room/UI/Http/Controller/GetRoomTypeAmenities/GetRoomTypeAmenitiesController.php`
- Create: `tests/Room/UI/Http/Controller/GetRoomTypeAmenities/GetRoomTypeAmenitiesControllerTest.php`

- [ ] **Write the failing functional test**

```php
<?php
// tests/Room/UI/Http/Controller/GetRoomTypeAmenities/GetRoomTypeAmenitiesControllerTest.php
declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\GetRoomTypeAmenities;

use App\Room\Domain\ValueObject\RoomAmenity;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetRoomTypeAmenitiesControllerTest extends WebTestCase
{
    #[Test]
    public function itReturnsAllRoomTypeAmenities(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/room-type-amenities');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));

        /** @var array{amenities: string[]} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('amenities', $body);
        self::assertEqualsCanonicalizing(RoomAmenity::values(), $body['amenities']);
    }
}
```

- [ ] **Run test to verify it fails**

```bash
make functional-test
```
Expected: FAIL — 404 (route not registered).

- [ ] **Create the Controller**

```php
<?php
// src/Room/UI/Http/Controller/GetRoomTypeAmenities/GetRoomTypeAmenitiesController.php
declare(strict_types=1);

namespace App\Room\UI\Http\Controller\GetRoomTypeAmenities;

use App\Room\Application\UseCase\GetRoomTypeAmenities\GetRoomTypeAmenitiesQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetRoomTypeAmenitiesController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
    ) {
    }

    #[Route(path: '/room-type-amenities', name: 'room_get_amenities_catalogue', methods: ['GET'])]
    #[OA\Get(
        summary: 'List all possible room type amenities',
        tags: ['Room Types'],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Amenities catalogue',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'amenities',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            example: ['wifi', 'tv', 'balcony'],
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(): Response
    {
        /** @var string[] $amenities */
        $amenities = $this->queryBus->ask(new GetRoomTypeAmenitiesQuery());

        return new JsonResponse(['amenities' => $amenities]);
    }
}
```

- [ ] **Run test to verify it passes**

```bash
make functional-test
```
Expected: PASS.

- [ ] **Commit**

```bash
git add src/Room/UI/Http/Controller/GetRoomTypeAmenities/ tests/Room/UI/Http/Controller/GetRoomTypeAmenities/
git commit -m "feat(room): add GET /room-type-amenities catalogue endpoint"
```

---

## Task 6: Full checks + regenerate OpenAPI

- [ ] **Run full lint suite**

```bash
make lint
```
Expected: no errors. If CS Fixer reports violations, run `make apply-cs` then re-run `make lint`.

- [ ] **Run full test suite**

```bash
make test
```
Expected: all tests pass.

- [ ] **Regenerate OpenAPI spec**

```bash
make openapi
```

- [ ] **Commit updated spec**

```bash
git add openapi.yaml
git commit -m "docs(openapi): regenerate spec with amenities catalogue endpoints"
```

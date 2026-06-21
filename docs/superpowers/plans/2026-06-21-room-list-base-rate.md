# Room List Base Rate Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show each Room's Base Rate (price in cents) in the back-office Room Catalogue table (`GET /api/v1/hotels/{hotelId}/rooms`, operationId `get_room_list_rooms`), without an N call-per-room round trip from the back office.

**Architecture:** Pricing publishes a per-room contract (`BaseRateFinderInterface` + `BaseRateView`, mirroring the existing `CancellationPolicyFinderInterface` pattern) per ADR 0015. Room defines its own `Domain\Port\RoomBaseRateFinderInterface` and an `Infrastructure\Service` adapter that delegates to Pricing's contract — the same shape as `Reservation\Infrastructure\Service\AvailabilityChecker` delegating to Availability's contract. The lookup is composed in `ListRoomsController` (UI layer) rather than inside `ListRoomsQueryHandler`: a `gitnexus_impact` check on `RoomPage` showed 8 direct importers (handler, both `RoomRepositoryInterface` implementations, the controller, the serializer) — touching the Domain model or the handler's return type would ripple into all of them and into `ListRoomsQueryHandlerTest`'s five existing tests for no benefit. Base rate here is a pure read-model enrichment for one HTTP response shape, not a business rule, and `UI → Domain Port` is an explicitly allowed dependency in `CLAUDE.md`'s layer table — so the controller can safely call the new port directly, leaving `RoomPage`, `ListRoomsQuery`, and `ListRoomsQueryHandler` completely untouched.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL, PHPUnit.

## Global Constraints

- `declare_strict_types=1` on every new file.
- Every new use case / contract class is `final` (and `readonly` where it has no mutable state), per existing codebase style.
- Cross-context imports only via `Application\Contract\*` (ADR 0015) — Room must never import `App\Pricing\Domain\*` or `App\Pricing\Application\UseCase\*`.
- `deptrac-contexts.yaml` must list `PricingContract` under `Room`'s allowed layers before Room can import Pricing's contract namespace, or `make deptrac` fails.
- Test groups: `TestCase` → `#[Group('unit')]`; `WebTestCase` → `#[Group('functional')]`. Test methods are `itDoesSomething(): void` with `#[Test]`.
- After adding/changing the route's response shape, run `make openapi` to regenerate `openapi.yaml`.

---

## Task 1: Pricing — publish a Base Rate contract

**Files:**
- Create: `src/Pricing/Application/Contract/BaseRateView.php`
- Create: `src/Pricing/Application/Contract/BaseRateFinderInterface.php`
- Create: `src/Pricing/Infrastructure/Contract/DoctrineBaseRateFinder.php`
- Test: `tests/Pricing/Infrastructure/Contract/DoctrineBaseRateFinderTest.php`

**Interfaces:**
- Consumes: `App\Pricing\Domain\Port\BaseRateRepositoryInterface::findByRoomId(RoomId $roomId): ?BaseRate` (existing, `src/Pricing/Domain/Port/BaseRateRepositoryInterface.php`); `App\Pricing\Domain\Model\BaseRate` has public readonly `roomId: RoomId`, `amountCents: int`, `updatedAt: \DateTimeImmutable` (existing, `src/Pricing/Domain/Model/BaseRate.php`).
- Produces: `App\Pricing\Application\Contract\BaseRateFinderInterface::find(string $roomId): ?BaseRateView` — consumed by Task 2.

- [ ] **Step 1: Write the failing test**

Create `tests/Pricing/Infrastructure/Contract/DoctrineBaseRateFinderTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\BaseRateFinderInterface;
use App\Pricing\Application\Contract\BaseRateView;
use App\Pricing\Domain\Model\BaseRate;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;
use App\Pricing\Infrastructure\Contract\DoctrineBaseRateFinder;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineBaseRateFinderTest extends TestCase
{
    private BaseRateRepositoryInterface&Stub $repository;
    private BaseRateFinderInterface $finder;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(BaseRateRepositoryInterface::class);
        $this->finder = new DoctrineBaseRateFinder($this->repository);
    }

    #[Test]
    public function itReturnsViewWhenBaseRateExists(): void
    {
        $baseRate = new BaseRate(new RoomId('room-1'), 12000, new \DateTimeImmutable());
        $this->repository->method('findByRoomId')->willReturn($baseRate);

        $view = $this->finder->find('room-1');

        self::assertInstanceOf(BaseRateView::class, $view);
        self::assertSame(12000, $view->amountCents);
    }

    #[Test]
    public function itReturnsNullWhenNoBaseRate(): void
    {
        $this->repository->method('findByRoomId')->willReturn(null);

        self::assertNull($this->finder->find('room-1'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `make unit-test`
Expected: FAIL — `BaseRateFinderInterface`, `BaseRateView`, `DoctrineBaseRateFinder` not found.

- [ ] **Step 3: Write minimal implementation**

Create `src/Pricing/Application/Contract/BaseRateView.php`:

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\Contract;

final readonly class BaseRateView
{
    public function __construct(public int $amountCents)
    {
    }
}
```

Create `src/Pricing/Application/Contract/BaseRateFinderInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application\Contract;

interface BaseRateFinderInterface
{
    public function find(string $roomId): ?BaseRateView;
}
```

Create `src/Pricing/Infrastructure/Contract/DoctrineBaseRateFinder.php`:

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\BaseRateFinderInterface;
use App\Pricing\Application\Contract\BaseRateView;
use App\Pricing\Domain\Port\BaseRateRepositoryInterface;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class DoctrineBaseRateFinder implements BaseRateFinderInterface
{
    public function __construct(private BaseRateRepositoryInterface $baseRates)
    {
    }

    public function find(string $roomId): ?BaseRateView
    {
        $baseRate = $this->baseRates->findByRoomId(new RoomId($roomId));

        if (null === $baseRate) {
            return null;
        }

        return new BaseRateView(amountCents: $baseRate->amountCents);
    }
}
```

No DI changes needed: `config/services/pricing.yaml` already autowires `App\Pricing\Infrastructure\` via `resource:`, and `DoctrineBaseRateFinder` is the sole implementation of `BaseRateFinderInterface`, so Symfony resolves the autowiring alias automatically (same as the existing, unbound `BaseRateRepositoryInterface` → `DoctrineBaseRateRepository`).

- [ ] **Step 4: Run test to verify it passes**

Run: `make unit-test`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Pricing/Application/Contract/BaseRateView.php src/Pricing/Application/Contract/BaseRateFinderInterface.php src/Pricing/Infrastructure/Contract/DoctrineBaseRateFinder.php tests/Pricing/Infrastructure/Contract/DoctrineBaseRateFinderTest.php
git commit -m "feat(pricing): publish BaseRateFinderInterface contract"
```

---

## Task 2: Room — consume the Base Rate contract

**Files:**
- Create: `src/Room/Domain/Port/RoomBaseRateFinderInterface.php`
- Create: `src/Room/Infrastructure/Service/BaseRateFinder.php`
- Modify: `config/services/room.yaml` — bind `RoomBaseRateFinderInterface`
- Modify: `deptrac-contexts.yaml` — allow Room to import `PricingContract`
- Test: `tests/Room/Infrastructure/Service/BaseRateFinderTest.php`

**Interfaces:**
- Consumes: `App\Pricing\Application\Contract\BaseRateFinderInterface::find(string $roomId): ?BaseRateView` (Task 1); `App\Shared\Domain\ValueObject\RoomId` (existing, has public `value: string`).
- Produces: `App\Room\Domain\Port\RoomBaseRateFinderInterface::find(RoomId $roomId): ?int` — consumed by Task 3.

- [ ] **Step 1: Write the failing test**

Create `tests/Room/Infrastructure/Service/BaseRateFinderTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Infrastructure\Service;

use App\Pricing\Application\Contract\BaseRateFinderInterface;
use App\Pricing\Application\Contract\BaseRateView;
use App\Room\Infrastructure\Service\BaseRateFinder;
use App\Shared\Domain\ValueObject\RoomId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BaseRateFinderTest extends TestCase
{
    private BaseRateFinderInterface&Stub $pricingFinder;
    private BaseRateFinder $finder;

    protected function setUp(): void
    {
        $this->pricingFinder = $this->createStub(BaseRateFinderInterface::class);
        $this->finder = new BaseRateFinder($this->pricingFinder);
    }

    #[Test]
    public function itReturnsAmountCentsWhenBaseRateExists(): void
    {
        $this->pricingFinder->method('find')->with('room-1')->willReturn(new BaseRateView(12000));

        self::assertSame(12000, $this->finder->find(new RoomId('room-1')));
    }

    #[Test]
    public function itReturnsNullWhenNoBaseRate(): void
    {
        $this->pricingFinder->method('find')->willReturn(null);

        self::assertNull($this->finder->find(new RoomId('room-1')));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `make unit-test`
Expected: FAIL — `RoomBaseRateFinderInterface` / `BaseRateFinder` not found.

- [ ] **Step 3: Write minimal implementation**

Create `src/Room/Domain/Port/RoomBaseRateFinderInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Shared\Domain\ValueObject\RoomId;

interface RoomBaseRateFinderInterface
{
    public function find(RoomId $roomId): ?int;
}
```

Create `src/Room/Infrastructure/Service/BaseRateFinder.php`:

```php
<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Service;

use App\Pricing\Application\Contract\BaseRateFinderInterface;
use App\Room\Domain\Port\RoomBaseRateFinderInterface;
use App\Shared\Domain\ValueObject\RoomId;

final readonly class BaseRateFinder implements RoomBaseRateFinderInterface
{
    public function __construct(private BaseRateFinderInterface $baseRateFinder)
    {
    }

    public function find(RoomId $roomId): ?int
    {
        return $this->baseRateFinder->find($roomId->value)?->amountCents;
    }
}
```

In `config/services/room.yaml`, add an explicit binding next to the other `App\Room\Domain\Port\*` entries (after the `RoomCapacityFinderInterface` line):

```yaml
    App\Room\Domain\Port\RoomBaseRateFinderInterface:
        class: App\Room\Infrastructure\Service\BaseRateFinder
```

In `deptrac-contexts.yaml`, find the `Room:` consumer block (around line 275):

```yaml
        Room:
            - RoomContract
            - HotelContract
            - Shared
            - Vendor
```

and add `PricingContract`:

```yaml
        Room:
            - RoomContract
            - HotelContract
            - PricingContract
            - Shared
            - Vendor
```

- [ ] **Step 4: Run test to verify it passes**

Run: `make unit-test`
Expected: PASS

Run: `make deptrac`
Expected: PASS — no violation for `App\Room\Infrastructure\Service\BaseRateFinder` importing `App\Pricing\Application\Contract\*`.

- [ ] **Step 5: Commit**

```bash
git add src/Room/Domain/Port/RoomBaseRateFinderInterface.php src/Room/Infrastructure/Service/BaseRateFinder.php config/services/room.yaml deptrac-contexts.yaml tests/Room/Infrastructure/Service/BaseRateFinderTest.php
git commit -m "feat(room): consume Pricing's BaseRateFinderInterface contract"
```

---

## Task 3: Room — show base rate in the Room Catalogue

**Files:**
- Modify: `src/Room/UI/Http/Controller/ListRooms/RoomCatalogueSerializer.php`
- Modify: `src/Room/UI/Http/Controller/ListRooms/ListRoomsController.php`
- Test: `tests/Room/UI/Http/Controller/ListRooms/RoomCatalogueSerializerTest.php` (new)
- Modify: `tests/Room/UI/Http/Controller/ListRooms/ListRoomsControllerTest.php`

**Interfaces:**
- Consumes: `App\Room\Domain\Port\RoomBaseRateFinderInterface::find(RoomId $roomId): ?int` (Task 2); existing `RoomCatalogueSerializer::serialize(RoomPage $roomPage, int $page, int $limit): array` and `RoomSerializer::serialize(Room $room): array` (`src/Room/UI/Http/Controller/RoomSerializer.php`) — left untouched, still used elsewhere (`GetRoomController`, `RegisterRoomController`, `BatchRegisterRoomsController`).
- Produces: `RoomCatalogueSerializer::serialize(RoomPage $roomPage, array $baseRateAmountCentsByRoomId, int $page, int $limit): array` — each `data[]` item gains a nullable `baseRateAmountCents` key.

- [ ] **Step 1: Write the failing test**

Create `tests/Room/UI/Http/Controller/ListRooms/RoomCatalogueSerializerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\ListRooms;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomPage;
use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use App\Room\UI\Http\Controller\ListRooms\RoomCatalogueSerializer;
use App\Room\UI\Http\Controller\RoomSerializer;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\RoomId;
use App\Shared\Domain\ValueObject\RoomTypeId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RoomCatalogueSerializerTest extends TestCase
{
    private RoomCatalogueSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new RoomCatalogueSerializer(new RoomSerializer());
    }

    #[Test]
    public function itIncludesBaseRateAmountCentsWhenProvided(): void
    {
        $room = $this->makeRoom('room-1');
        $roomPage = new RoomPage([$room], 1);

        $result = $this->serializer->serialize($roomPage, ['room-1' => 12000], 1, 20);

        self::assertSame(12000, $result['data'][0]['baseRateAmountCents']);
    }

    #[Test]
    public function itReturnsNullBaseRateAmountCentsWhenNotProvided(): void
    {
        $room = $this->makeRoom('room-1');
        $roomPage = new RoomPage([$room], 1);

        $result = $this->serializer->serialize($roomPage, [], 1, 20);

        self::assertNull($result['data'][0]['baseRateAmountCents']);
    }

    private function makeRoom(string $id): Room
    {
        return new Room(
            new RoomId($id),
            new HotelId('550e8400-e29b-41d4-a716-446655440000'),
            new RoomNumber('101'),
            new RoomFloor(1),
            new RoomTypeId('cccccccc-0000-4000-8000-000000000001'),
            new \DateTimeImmutable('2024-01-01'),
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `make unit-test`
Expected: FAIL — `serialize()` does not accept the new `$baseRateAmountCentsByRoomId` argument (`ArgumentCountError` or undefined array key `baseRateAmountCents`).

- [ ] **Step 3: Write minimal implementation**

Replace `src/Room/UI/Http/Controller/ListRooms/RoomCatalogueSerializer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\ListRooms;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomPage;
use App\Room\UI\Http\Controller\RoomSerializer;

final class RoomCatalogueSerializer
{
    public function __construct(private RoomSerializer $roomSerializer)
    {
    }

    /**
     * @param array<string, int> $baseRateAmountCentsByRoomId
     *
     * @return array{
     *     data: list<array{id: string, hotelId: string, number: string, floor: int, roomTypeId: string, createdAt: string, baseRateAmountCents: ?int}>,
     *     meta: array{page: int, limit: int, total: int, totalPages: int}
     * }
     */
    public function serialize(RoomPage $roomPage, array $baseRateAmountCentsByRoomId, int $page, int $limit): array
    {
        return [
            'data' => array_map(
                fn (Room $room) => [
                    ...$this->roomSerializer->serialize($room),
                    'baseRateAmountCents' => $baseRateAmountCentsByRoomId[$room->id->value] ?? null,
                ],
                $roomPage->rooms,
            ),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $roomPage->total,
                'totalPages' => (int) ceil($roomPage->total / $limit),
            ],
        ];
    }
}
```

Modify `src/Room/UI/Http/Controller/ListRooms/ListRoomsController.php` — add the `RoomBaseRateFinderInterface` dependency, build the lookup map, pass it to the serializer, and document the new field in the OA schema:

```php
<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\ListRooms;

use App\Room\Application\UseCase\ListRooms\ListRoomsQuery;
use App\Room\Domain\Port\RoomBaseRateFinderInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Shared\Domain\ValueObject\HotelId;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class ListRoomsController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private RoomBaseRateFinderInterface $baseRateFinder,
        private RoomCatalogueSerializer $serializer,
    ) {
    }

    #[Route('/hotels/{hotelId}/rooms', name: 'room_list_rooms', requirements: ['hotelId' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'List rooms of a hotel (paginated)',
        tags: ['Rooms'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Paginated room catalogue',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'hotelId', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'number', type: 'string'),
                                    new OA\Property(property: 'createdAt', type: 'integer'),
                                    new OA\Property(property: 'baseRateAmountCents', type: 'integer', nullable: true, example: 12000),
                                ],
                                type: 'object',
                            ),
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [
                                new OA\Property(property: 'page', type: 'integer', example: 1),
                                new OA\Property(property: 'limit', type: 'integer', example: 20),
                                new OA\Property(property: 'total', type: 'integer', example: 10),
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
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'),
                ),
            ),
        ],
    )]
    public function __invoke(
        string $hotelId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ListRoomsRequest $request = new ListRoomsRequest(),
    ): Response {
        $roomPage = $this->queryBus->ask(new ListRoomsQuery(
            new HotelId($hotelId),
            $request->page,
            $request->limit,
        ));

        $baseRateAmountCentsByRoomId = [];
        foreach ($roomPage->rooms as $room) {
            $amountCents = $this->baseRateFinder->find($room->id);
            if (null !== $amountCents) {
                $baseRateAmountCentsByRoomId[$room->id->value] = $amountCents;
            }
        }

        return new JsonResponse(
            $this->serializer->serialize($roomPage, $baseRateAmountCentsByRoomId, $request->page, $request->limit),
        );
    }
}
```

Add two functional tests to `tests/Room/UI/Http/Controller/ListRooms/ListRoomsControllerTest.php` (insert after `itReturnsRoomsSortedByNumberAscending`, keep all existing tests and helpers unchanged):

```php
    #[Test]
    public function itIncludesBaseRateAmountCentsWhenSet(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);
        $roomId = $this->registerRoomAndGetId($client, $hotelId, $roomTypeId, '101');

        $client->request(
            method: 'PUT',
            uri: "/api/v1/rooms/{$roomId}/base-rate",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amount' => 120.00], \JSON_THROW_ON_ERROR),
        );

        $client->request('GET', "/api/v1/hotels/{$hotelId}/rooms");

        /** @var array{data: list<array{baseRateAmountCents: ?int}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(12000, $body['data'][0]['baseRateAmountCents']);
    }

    #[Test]
    public function itReturnsNullBaseRateAmountCentsWhenNotSet(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);
        $this->registerRoom($client, $hotelId, $roomTypeId, '101');

        $client->request('GET', "/api/v1/hotels/{$hotelId}/rooms");

        /** @var array{data: list<array{baseRateAmountCents: ?int}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNull($body['data'][0]['baseRateAmountCents']);
    }
```

Add the `registerRoomAndGetId` helper next to the existing `registerRoom` helper in the same file:

```php
    private function registerRoomAndGetId(KernelBrowser $client, string $hotelId, string $roomTypeId, string $number): string
    {
        $this->registerRoom($client, $hotelId, $roomTypeId, $number);

        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `make unit-test`
Expected: PASS (`RoomCatalogueSerializerTest`)

Run: `make functional-test`
Expected: PASS (`ListRoomsControllerTest`, including the two new cases)

- [ ] **Step 5: Regenerate OpenAPI spec**

Run: `make openapi`
Expected: `openapi.yaml` updates to include `baseRateAmountCents` (nullable integer) in the `get_room_list_rooms` response schema. Inspect the diff and confirm no unrelated routes changed.

- [ ] **Step 6: Run full lint and impact check**

Run: `make lint`
Expected: PASS (CS Fixer, PHPStan, both deptrac analyses)

Run `gitnexus_detect_changes()` and confirm only `Room` (and the new `PricingContract` edge) and `Pricing`'s contract surface are affected — no unrelated execution flow should appear.

- [ ] **Step 7: Commit**

```bash
git add src/Room/UI/Http/Controller/ListRooms/RoomCatalogueSerializer.php src/Room/UI/Http/Controller/ListRooms/ListRoomsController.php tests/Room/UI/Http/Controller/ListRooms/RoomCatalogueSerializerTest.php tests/Room/UI/Http/Controller/ListRooms/ListRoomsControllerTest.php openapi.yaml
git commit -m "feat(room): show base rate in the room catalogue list"
```

---

## Final check

- [ ] Push the branch and open a PR (per `CLAUDE.md` branching policy — never commit directly to `main`). Use `superpowers:finishing-a-development-branch` to drive this step.

# Reservation Read Endpoints Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `GET /reservations/{id}` (single reservation) and `GET /reservations?bookerId={uuid}&page=1&limit=20` (paginated list) to the Reservation bounded context.

**Architecture:** Endpoint 1 reuses the existing `GetReservationQuery`/`GetReservationQueryHandler` — only a new controller is needed. Endpoint 2 follows the `Room` context pagination pattern: a `ReservationPage` domain model, a new `listByBooker` repository method, a query/handler pair, and a controller with `MapQueryString`. Both endpoints reuse `ReservationSerializer`.

**Tech Stack:** PHP 8.4 / Symfony 8.0 / Doctrine DBAL 4 / PostgreSQL 16 / PHPUnit functional tests (`#[Group('functional')]`)

---

## File Map

| Action | Path | Purpose |
|--------|------|---------|
| Create | `src/Reservation/Domain/Model/ReservationPage.php` | Pagination result DTO |
| Modify | `src/Reservation/Domain/Port/ReservationRepositoryInterface.php` | Add `listByBooker()` |
| Modify | `tests/Reservation/Infrastructure/Persistence/InMemory/InMemoryReservationRepository.php` | Implement `listByBooker()` |
| Create | `src/Reservation/Application/UseCase/ListBookerReservations/ListBookerReservationsQuery.php` | Query message |
| Create | `src/Reservation/Application/UseCase/ListBookerReservations/ListBookerReservationsQueryHandler.php` | Query handler |
| Modify | `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php` | Implement `listByBooker()` |
| Create | `src/Reservation/UI/Http/Controller/GetReservation/GetReservationController.php` | GET /reservations/{id} |
| Create | `tests/Reservation/UI/Http/Controller/GetReservation/GetReservationControllerTest.php` | Functional tests |
| Create | `src/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsRequest.php` | Query string DTO |
| Create | `src/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsController.php` | GET /reservations |
| Create | `tests/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsControllerTest.php` | Functional tests |

**No changes needed:**
- `config/services/reservation.yaml` — all resources are already auto-wired via `App\Reservation\UI\:`, `App\Reservation\Application\:`, `App\Reservation\Infrastructure\:`. Request DTOs already excluded via `**/*Request.php` glob.
- `config/services/exceptions.yaml` — `ReservationNotFoundException` is already mapped to 404.

---

### Task 1: Create branch

- [ ] **Create feature branch**

```bash
git checkout -b feat/reservation-read-endpoints
```

---

### Task 2: ReservationPage domain model

**Files:**
- Create: `src/Reservation/Domain/Model/ReservationPage.php`

- [ ] **Create ReservationPage**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Model;

final readonly class ReservationPage
{
    /** @param list<Reservation> $reservations */
    public function __construct(
        public array $reservations,
        public int $total,
    ) {
    }
}
```

- [ ] **Run static analysis to verify no issues**

```bash
make phpstan
```

Expected: no errors.

- [ ] **Commit**

```bash
git add src/Reservation/Domain/Model/ReservationPage.php
git commit -m "feat(reservation): add ReservationPage domain model"
```

---

### Task 3: Extend repository interface and in-memory test double

**Files:**
- Modify: `src/Reservation/Domain/Port/ReservationRepositoryInterface.php`
- Modify: `tests/Reservation/Infrastructure/Persistence/InMemory/InMemoryReservationRepository.php`

- [ ] **Add listByBooker to the interface**

In `src/Reservation/Domain/Port/ReservationRepositoryInterface.php`, add the method signature after `get()`:

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Port;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationPage;

interface ReservationRepositoryInterface
{
    public function add(Reservation $reservation): void;

    public function save(Reservation $reservation): void;

    public function get(string $id): ?Reservation;

    public function listByBooker(string $bookerId, int $page, int $limit): ReservationPage;
}
```

- [ ] **Implement listByBooker in the in-memory test double**

Full replacement of `tests/Reservation/Infrastructure/Persistence/InMemory/InMemoryReservationRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Persistence\InMemory;

use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationPage;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;

final class InMemoryReservationRepository implements ReservationRepositoryInterface
{
    /** @var array<string, Reservation> */
    private array $store = [];

    public function add(Reservation $reservation): void
    {
        $this->store[$reservation->id] = $reservation;
    }

    public function save(Reservation $reservation): void
    {
        $this->store[$reservation->id] = $reservation;
    }

    public function get(string $id): ?Reservation
    {
        return $this->store[$id] ?? null;
    }

    public function listByBooker(string $bookerId, int $page, int $limit): ReservationPage
    {
        $all = array_values(array_filter(
            $this->store,
            fn(Reservation $r) => $r->bookerId === $bookerId,
        ));

        usort($all, fn(Reservation $a, Reservation $b) => $b->createdAt <=> $a->createdAt);

        $total = count($all);
        $items = array_slice($all, ($page - 1) * $limit, $limit);

        return new ReservationPage($items, $total);
    }
}
```

- [ ] **Run tests to verify no regressions**

```bash
make test-unit && make test-integration
```

Expected: all pass (the interface change is now satisfied everywhere).

- [ ] **Commit**

```bash
git add src/Reservation/Domain/Port/ReservationRepositoryInterface.php \
        tests/Reservation/Infrastructure/Persistence/InMemory/InMemoryReservationRepository.php
git commit -m "feat(reservation): add listByBooker to repository interface and in-memory double"
```

---

### Task 4: ListBookerReservations query and handler

**Files:**
- Create: `src/Reservation/Application/UseCase/ListBookerReservations/ListBookerReservationsQuery.php`
- Create: `src/Reservation/Application/UseCase/ListBookerReservations/ListBookerReservationsQueryHandler.php`

- [ ] **Create the query**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\ListBookerReservations;

use App\Reservation\Domain\Model\ReservationPage;
use App\Shared\Application\Bus\SyncQueryInterface;

/** @implements SyncQueryInterface<ReservationPage> */
final readonly class ListBookerReservationsQuery implements SyncQueryInterface
{
    public function __construct(
        public string $bookerId,
        public int $page = 1,
        public int $limit = 20,
    ) {
    }
}
```

- [ ] **Create the handler**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Application\UseCase\ListBookerReservations;

use App\Reservation\Domain\Model\ReservationPage;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class ListBookerReservationsQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private ReservationRepositoryInterface $repository)
    {
    }

    public function __invoke(ListBookerReservationsQuery $query): ReservationPage
    {
        return $this->repository->listByBooker($query->bookerId, $query->page, $query->limit);
    }
}
```

- [ ] **Run static analysis**

```bash
make phpstan
```

Expected: no errors.

- [ ] **Commit**

```bash
git add src/Reservation/Application/UseCase/ListBookerReservations/
git commit -m "feat(reservation): add ListBookerReservations query and handler"
```

---

### Task 5: Implement listByBooker in Doctrine repository

**Files:**
- Modify: `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php`

- [ ] **Add the listByBooker method**

Add after the `get()` method, before `hydrate()`. Also add `use Doctrine\DBAL\ParameterType;` to the imports.

```php
public function listByBooker(string $bookerId, int $page, int $limit): ReservationPage
{
    $total = (int) $this->bookit->fetchOne(
        'SELECT COUNT(*) FROM reservation WHERE booker_id = :bookerId',
        ['bookerId' => $bookerId],
    );

    if (0 === $total) {
        return new ReservationPage([], 0);
    }

    $offset = ($page - 1) * $limit;

    /** @var list<array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, guest_count: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string, g_id: string|null, first_name: string|null, last_name: string|null, date_of_birth: string|null}> $rows */
    $rows = $this->bookit->fetchAllAssociative(
        'SELECT r.id, r.room_id, r.booker_id, r.check_in, r.check_out, r.total_price, r.guest_count,
                r.cancellation_terms_days_threshold, r.price_breakdown, r.status, r.created_at,
                rg.id AS g_id, rg.first_name, rg.last_name, rg.date_of_birth
           FROM reservation r
           LEFT JOIN reservation_guest rg ON rg.reservation_id = r.id
          WHERE r.id IN (
              SELECT id FROM reservation
               WHERE booker_id = :bookerId
               ORDER BY created_at DESC
               LIMIT :limit OFFSET :offset
          )
          ORDER BY r.created_at DESC, r.id, rg.id',
        ['bookerId' => $bookerId, 'limit' => $limit, 'offset' => $offset],
        ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
    );

    $byId = [];
    $guestsByReservationId = [];
    foreach ($rows as $row) {
        $rid = $row['id'];
        if (!isset($byId[$rid])) {
            $byId[$rid] = $row;
            $guestsByReservationId[$rid] = [];
        }
        if (null !== $row['g_id']) {
            $guestsByReservationId[$rid][] = $row;
        }
    }

    $reservations = [];
    foreach ($byId as $rid => $row) {
        $reservation = $this->hydrate($row);
        $reservation->guests = array_map(
            fn(array $g) => new Guest(
                id: $g['g_id'],
                firstName: (string) $g['first_name'],
                lastName: (string) $g['last_name'],
                dateOfBirth: new \DateTimeImmutable((string) $g['date_of_birth']),
            ),
            $guestsByReservationId[$rid],
        );
        $reservations[] = $reservation;
    }

    return new ReservationPage($reservations, $total);
}
```

The full import block for the file becomes (add the two new use statements):

```php
use App\Reservation\Domain\Model\Guest;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationPage;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
```

- [ ] **Run static analysis**

```bash
make phpstan
```

Expected: no errors.

- [ ] **Commit**

```bash
git add src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php
git commit -m "feat(reservation): implement listByBooker in Doctrine repository"
```

---

### Task 6: GET /reservations/{id} — controller and functional tests

**Files:**
- Create: `tests/Reservation/UI/Http/Controller/GetReservation/GetReservationControllerTest.php`
- Create: `src/Reservation/UI/Http/Controller/GetReservation/GetReservationController.php`

- [ ] **Write the failing functional test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\UI\Http\Controller\GetReservation;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetReservationControllerTest extends WebTestCase
{
    #[Test]
    public function itReturns200WithAllFields(): void
    {
        $client = static::createClient();
        $reservationId = $this->createReservation($client);

        $client->request('GET', "/api/v1/reservations/{$reservationId}");

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        /** @var array{id: string, roomId: string, bookerId: string, checkIn: string, checkOut: string, totalPrice: int, guestCount: int, status: string, cancellationTerms: array{daysThreshold: int|null}, priceBreakdown: list<mixed>, createdAt: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($reservationId, $body['id']);
        self::assertSame('pending', $body['status']);
        self::assertSame('2030-06-01', $body['checkIn']);
        self::assertSame('2030-06-03', $body['checkOut']);
        self::assertSame(20000, $body['totalPrice']); // 2 nights × 10000
        self::assertSame(1, $body['guestCount']);
        self::assertNull($body['cancellationTerms']['daysThreshold']);
        self::assertNotEmpty($body['priceBreakdown']);
        self::assertNotEmpty($body['createdAt']);
    }

    #[Test]
    public function itReturns404WhenReservationDoesNotExist(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/reservations/00000000-0000-4000-8000-000000000099');

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $client->getResponse()->headers->get('Content-Type'));

        /** @var array{type: string, status: int} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/reservation-not-found', $body['type']);
        self::assertSame(Response::HTTP_NOT_FOUND, $body['status']);
    }

    private function createReservation(KernelBrowser $client): string
    {
        $client->request('POST', '/api/v1/hotels', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Test Hotel',
            'streetAddress' => '1 rue de la Paix',
            'postalCode' => '75001',
            'city' => 'Paris',
            'country' => 'FR',
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $hotel */
        $hotel = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/hotels/{$hotel['id']}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Standard',
            'livingSpaceCount' => 1,
            'guestCapacity' => 2,
            'isAccessible' => false,
            'bedComposition' => [['type' => 'double', 'count' => 1]],
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $roomType */
        $roomType = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/hotels/{$hotel['id']}/rooms", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'number' => '101',
            'floor' => 1,
            'roomTypeId' => $roomType['id'],
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $room */
        $room = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request('PUT', "/api/v1/rooms/{$room['id']}/base-rate", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'amount' => 100,
        ], \JSON_THROW_ON_ERROR));

        $client->request('POST', '/api/v1/bookers', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'firstName' => 'Alice',
            'lastName' => 'Martin',
            'email' => 'alice.' . uniqid() . '@example.com',
            'phone' => '+33612345678',
            'dateOfBirth' => '1990-01-01',
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $booker */
        $booker = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/v1/reservations', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'roomId' => $room['id'],
            'bookerId' => $booker['id'],
            'checkIn' => '2030-06-01',
            'checkOut' => '2030-06-03',
            'guestCount' => 1,
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $reservation */
        $reservation = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $reservation['id'];
    }
}
```

- [ ] **Run the test to verify it fails (route not found)**

```bash
make test-functional -- --filter GetReservationControllerTest
```

Expected: FAIL — `Expected status code 200, got 404` (route does not exist yet).

- [ ] **Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\GetReservation;

use App\Reservation\Application\UseCase\GetReservation\GetReservationQuery;
use App\Reservation\Domain\Exception\ReservationNotFoundException;
use App\Reservation\UI\Http\Controller\ReservationSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetReservationController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private ReservationSerializer $serializer,
    ) {
    }

    #[Route(
        path: '/reservations/{id}',
        name: 'reservation_get',
        requirements: ['id' => Requirement::UUID_V4],
        methods: ['GET'],
    )]
    #[OA\Get(
        summary: 'Get a reservation by ID',
        tags: ['Reservation'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Reservation found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                new OA\Property(property: 'bookerId', type: 'string', format: 'uuid'),
                new OA\Property(property: 'checkIn', type: 'string', format: 'date', example: '2026-06-01'),
                new OA\Property(property: 'checkOut', type: 'string', format: 'date', example: '2026-06-05'),
                new OA\Property(property: 'totalPrice', type: 'integer', example: 42000),
                new OA\Property(property: 'guestCount', type: 'integer', example: 2),
                new OA\Property(property: 'status', type: 'string', example: 'pending'),
                new OA\Property(
                    property: 'cancellationTerms',
                    properties: [new OA\Property(property: 'daysThreshold', type: 'integer', nullable: true, example: 7)],
                    type: 'object',
                ),
                new OA\Property(
                    property: 'priceBreakdown',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-06-01'),
                            new OA\Property(property: 'rateAmountCents', type: 'integer', example: 10000),
                            new OA\Property(property: 'discountPercent', type: 'integer', nullable: true, example: null),
                            new OA\Property(property: 'effectiveAmountCents', type: 'integer', example: 10000),
                        ],
                        type: 'object',
                    ),
                ),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
            ],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_NOT_FOUND,
        description: 'Reservation not found',
        content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail')),
    )]
    public function __invoke(string $id): Response
    {
        $reservation = $this->queryBus->ask(new GetReservationQuery($id));

        if (null === $reservation) {
            throw new ReservationNotFoundException($id);
        }

        return new JsonResponse($this->serializer->serialize($reservation));
    }
}
```

- [ ] **Run the tests to verify they pass**

```bash
make test-functional -- --filter GetReservationControllerTest
```

Expected: 2 tests pass.

- [ ] **Run lint to verify code style**

```bash
make lint
```

Expected: no errors.

- [ ] **Commit**

```bash
git add src/Reservation/UI/Http/Controller/GetReservation/GetReservationController.php \
        tests/Reservation/UI/Http/Controller/GetReservation/GetReservationControllerTest.php
git commit -m "feat(reservation): add GET /reservations/{id} endpoint"
```

---

### Task 7: GET /reservations — list by booker controller and functional tests

**Files:**
- Create: `tests/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsControllerTest.php`
- Create: `src/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsRequest.php`
- Create: `src/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsController.php`

- [ ] **Write the failing functional tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\UI\Http\Controller\ListBookerReservations;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class ListBookerReservationsControllerTest extends WebTestCase
{
    #[Test]
    public function itReturnsPaginatedReservationsForBooker(): void
    {
        $client = static::createClient();
        [$bookerId, $roomId] = $this->setupBookerAndRoom($client);
        $this->createReservation($client, $bookerId, $roomId, '2030-06-01', '2030-06-03');
        $this->createReservation($client, $bookerId, $roomId, '2030-07-01', '2030-07-03');

        $client->request('GET', "/api/v1/reservations?bookerId={$bookerId}&page=1&limit=10");

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        /** @var array{data: list<array{id: string, status: string}>, meta: array{page: int, limit: int, total: int, totalPages: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['data']);
        self::assertSame(1, $body['meta']['page']);
        self::assertSame(10, $body['meta']['limit']);
        self::assertSame(2, $body['meta']['total']);
        self::assertSame(1, $body['meta']['totalPages']);
        self::assertSame('pending', $body['data'][0]['status']);
    }

    #[Test]
    public function itReturnsEmptyDataWhenPageExceedsTotal(): void
    {
        $client = static::createClient();
        [$bookerId, $roomId] = $this->setupBookerAndRoom($client);
        $this->createReservation($client, $bookerId, $roomId, '2030-06-01', '2030-06-03');

        $client->request('GET', "/api/v1/reservations?bookerId={$bookerId}&page=2&limit=10");

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        /** @var array{data: list<mixed>, meta: array{total: int, totalPages: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(0, $body['data']);
        self::assertSame(1, $body['meta']['total']);
        self::assertSame(1, $body['meta']['totalPages']);
    }

    #[Test]
    public function itReturnsEmptyListForUnknownBooker(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/reservations?bookerId=00000000-0000-4000-8000-000000000099&page=1&limit=20');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        /** @var array{data: list<mixed>, meta: array{total: int, totalPages: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(0, $body['data']);
        self::assertSame(0, $body['meta']['total']);
        self::assertSame(0, $body['meta']['totalPages']);
    }

    #[Test]
    public function itReturns422WhenBookerIdIsMissing(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/reservations?page=1&limit=20');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $client->getResponse()->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns422WhenBookerIdIsNotAUuid(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/reservations?bookerId=not-a-uuid');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenLimitExceeds100(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/reservations?bookerId=00000000-0000-4000-8000-000000000001&limit=101');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenPageIsZero(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/reservations?bookerId=00000000-0000-4000-8000-000000000001&page=0');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    /** @return array{string, string} [bookerId, roomId] */
    private function setupBookerAndRoom(KernelBrowser $client): array
    {
        $client->request('POST', '/api/v1/hotels', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Test Hotel',
            'streetAddress' => '1 rue de la Paix',
            'postalCode' => '75001',
            'city' => 'Paris',
            'country' => 'FR',
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $hotel */
        $hotel = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/hotels/{$hotel['id']}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Standard',
            'livingSpaceCount' => 1,
            'guestCapacity' => 2,
            'isAccessible' => false,
            'bedComposition' => [['type' => 'double', 'count' => 1]],
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $roomType */
        $roomType = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/hotels/{$hotel['id']}/rooms", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'number' => '101',
            'floor' => 1,
            'roomTypeId' => $roomType['id'],
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $room */
        $room = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $client->request('PUT', "/api/v1/rooms/{$room['id']}/base-rate", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'amount' => 100,
        ], \JSON_THROW_ON_ERROR));

        $client->request('POST', '/api/v1/bookers', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'firstName' => 'Alice',
            'lastName' => 'Martin',
            'email' => 'alice.' . uniqid() . '@example.com',
            'phone' => '+33612345678',
            'dateOfBirth' => '1990-01-01',
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $booker */
        $booker = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return [$booker['id'], $room['id']];
    }

    private function createReservation(KernelBrowser $client, string $bookerId, string $roomId, string $checkIn, string $checkOut): void
    {
        $client->request('POST', '/api/v1/reservations', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'roomId' => $roomId,
            'bookerId' => $bookerId,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'guestCount' => 1,
        ], \JSON_THROW_ON_ERROR));
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    }
}
```

- [ ] **Run the tests to verify they fail**

```bash
make test-functional -- --filter ListBookerReservationsControllerTest
```

Expected: FAIL — routes not registered yet.

- [ ] **Create the request DTO**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\ListBookerReservations;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListBookerReservationsRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
        #[OA\Parameter(name: 'bookerId', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
        public ?string $bookerId = null,
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

- [ ] **Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Reservation\UI\Http\Controller\ListBookerReservations;

use App\Reservation\Application\UseCase\ListBookerReservations\ListBookerReservationsQuery;
use App\Reservation\UI\Http\Controller\ReservationSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ListBookerReservationsController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private ReservationSerializer $serializer,
    ) {
    }

    #[Route('/reservations', name: 'reservation_list_by_booker', methods: ['GET'])]
    #[OA\Get(
        summary: 'List reservations for a booker (paginated)',
        tags: ['Reservation'],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Paginated reservation list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'bookerId', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'checkIn', type: 'string', format: 'date'),
                                    new OA\Property(property: 'checkOut', type: 'string', format: 'date'),
                                    new OA\Property(property: 'totalPrice', type: 'integer'),
                                    new OA\Property(property: 'guestCount', type: 'integer'),
                                    new OA\Property(property: 'status', type: 'string'),
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
                                new OA\Property(property: 'total', type: 'integer', example: 42),
                                new OA\Property(property: 'totalPages', type: 'integer', example: 3),
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
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListBookerReservationsRequest $request = new ListBookerReservationsRequest(),
    ): Response {
        $page = $this->queryBus->ask(new ListBookerReservationsQuery(
            (string) $request->bookerId,
            $request->page,
            $request->limit,
        ));

        return new JsonResponse([
            'data' => array_map($this->serializer->serialize(...), $page->reservations),
            'meta' => [
                'page' => $request->page,
                'limit' => $request->limit,
                'total' => $page->total,
                'totalPages' => (int) ceil($page->total / $request->limit),
            ],
        ]);
    }
}
```

- [ ] **Run tests to verify they pass**

```bash
make test-functional -- --filter ListBookerReservationsControllerTest
```

Expected: 7 tests pass.

- [ ] **Run lint**

```bash
make lint
```

Expected: no errors.

- [ ] **Commit**

```bash
git add src/Reservation/UI/Http/Controller/ListBookerReservations/ \
        tests/Reservation/UI/Http/Controller/ListBookerReservations/ListBookerReservationsControllerTest.php
git commit -m "feat(reservation): add GET /reservations list by booker endpoint"
```

---

### Task 8: Regenerate OpenAPI spec and full test suite

- [ ] **Regenerate OpenAPI spec**

```bash
make openapi
```

Expected: no errors. The spec file is updated with the two new endpoints.

- [ ] **Run the full test suite**

```bash
make test
```

Expected: all tests pass.

- [ ] **Commit OpenAPI changes**

```bash
git add public/api/openapi.yaml   # or whatever path make openapi writes to
git commit -m "chore: regenerate OpenAPI spec after adding reservation read endpoints"
```

---

## Self-Review Notes

- **Spec coverage:** Both endpoints covered — single by ID (Task 6) and paginated list by booker (Task 7). All 422/404 cases have explicit test steps.
- **No placeholders:** All code is complete and compiles as-is.
- **Type consistency:** `ReservationPage` defined in Task 2, used in handler (Task 4), repository (Task 5), and controller (Task 7) — consistent throughout.
- **DI:** No changes needed — all controllers and handlers are auto-wired by existing resource declarations in `config/services/reservation.yaml`.
- **Route conflict check:** `GET /reservations` (list) and `GET /reservations/{id}` (get by ID) are distinct Symfony routes — no conflict with existing `POST /reservations`.
- **totalPages when total=0:** `ceil(0 / limit) = 0` — correct, covered by `itReturnsEmptyListForUnknownBooker` test.

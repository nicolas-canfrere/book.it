# Get Hotel By ID Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose `GET /api/hotels/{id}` returning a single Hotel, and extract a shared `HotelSerializer` to eliminate duplication between `RegisteredHotelSerializer` and `HotelCatalogueSerializer`.

**Architecture:** `GetHotelQuery` + `GetHotelQueryHandler` already exist — only the HTTP layer is missing. A shared `HotelSerializer` replaces the two separate serializers and is injected wherever a single `Hotel` is serialized to JSON.

**Tech Stack:** PHP 8.4, Symfony 8.0, PHPUnit, `WebTestCase` for functional tests.

---

## File Map

| Action | Path |
|--------|------|
| Create | `src/Hotel/UI/Http/Controller/HotelSerializer.php` |
| Modify | `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelController.php` |
| Delete | `src/Hotel/UI/Http/Controller/RegisterHotel/RegisteredHotelSerializer.php` |
| Modify | `src/Hotel/UI/Http/Controller/ListHotels/HotelCatalogueSerializer.php` |
| Create | `src/Hotel/UI/Http/Controller/GetHotel/GetHotelController.php` |
| Create | `tests/Hotel/UI/Http/Controller/GetHotel/GetHotelControllerTest.php` |

---

## Task 1 — Extract shared `HotelSerializer`

This is a pure refactor. No behavior changes — existing functional tests prove correctness throughout.

**Files:**
- Create: `src/Hotel/UI/Http/Controller/HotelSerializer.php`
- Modify: `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelController.php`
- Delete: `src/Hotel/UI/Http/Controller/RegisterHotel/RegisteredHotelSerializer.php`
- Modify: `src/Hotel/UI/Http/Controller/ListHotels/HotelCatalogueSerializer.php`

- [ ] **Step 1: Create `HotelSerializer`**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller;

use App\Hotel\Domain\Model\Hotel;

final class HotelSerializer
{
    /**
     * @return array{id: string, name: string, streetAddress: string, postalCode: string, city: string, country: string, createdAt: int}
     */
    public function serialize(Hotel $hotel): array
    {
        return [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'streetAddress' => $hotel->address->streetAddress,
            'postalCode' => $hotel->address->postalCode,
            'city' => $hotel->address->city,
            'country' => $hotel->address->country,
            'createdAt' => $hotel->createdAt->getTimestamp(),
        ];
    }
}
```

- [ ] **Step 2: Update `RegisterHotelController` to use `HotelSerializer`**

Full file after modification:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\RegisterHotel;

use App\Hotel\Application\Service\RegisterHotelCommandFactory;
use App\Hotel\Application\UseCase\GetHotel\GetHotelQuery;
use App\Hotel\UI\Http\Controller\HotelSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RegisterHotelController
{
    public function __construct(
        private RegisterHotelCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private HotelSerializer $hotelSerializer,
    ) {
    }

    #[Route('/api/hotels', name: 'hotel_register_hotel', methods: ['POST'])]
    #[OA\Post(
        summary: 'Register a new hotel',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: RegisterHotelRequest::class)),
        ),
        tags: ['Hotels'],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Hotel registered',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'name', type: 'string', example: 'Hotel Ibis Paris'),
                        new OA\Property(property: 'streetAddress', type: 'string', example: '15 rue de Rivoli'),
                        new OA\Property(property: 'postalCode', type: 'string', example: '75001'),
                        new OA\Property(property: 'city', type: 'string', example: 'Paris'),
                        new OA\Property(property: 'country', type: 'string', example: 'FR'),
                        new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Not found',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
                ),
            ),
            new OA\Response(
                response: Response::HTTP_CONFLICT,
                description: 'Hotel already exists',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
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
        #[MapRequestPayload(acceptFormat: 'json')]
        RegisterHotelRequest $request,
    ): Response {
        $command = $this->commandFactory->create(
            $request->name,
            $request->streetAddress,
            $request->postalCode,
            $request->city,
            $request->country,
        );
        $this->commandBus->execute($command);

        $hotel = $this->queryBus->ask(new GetHotelQuery($command->id));
        if (null === $hotel) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse(
            $this->hotelSerializer->serialize($hotel),
            Response::HTTP_CREATED
        );
    }
}
```

- [ ] **Step 3: Delete `RegisteredHotelSerializer`**

```bash
rm src/Hotel/UI/Http/Controller/RegisterHotel/RegisteredHotelSerializer.php
```

- [ ] **Step 4: Update `HotelCatalogueSerializer` to use `HotelSerializer`**

Full file after modification:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\ListHotels;

use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\UI\Http\Controller\HotelSerializer;

final class HotelCatalogueSerializer
{
    public function __construct(
        private HotelSerializer $hotelSerializer,
    ) {
    }

    /**
     * @return array{
     *     data: list<array{id: string, name: string, streetAddress: string, postalCode: string, city: string, country: string, createdAt: int}>,
     *     meta: array{page: int, limit: int, total: int, totalPages: int}
     * }
     */
    public function serialize(HotelPage $hotelPage, int $page, int $limit): array
    {
        return [
            'data' => array_map(
                fn(Hotel $hotel) => $this->hotelSerializer->serialize($hotel),
                $hotelPage->hotels,
            ),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $hotelPage->total,
                'totalPages' => (int) ceil($hotelPage->total / $limit),
            ],
        ];
    }
}
```

- [ ] **Step 5: Run the full functional test suite — must pass**

```bash
make functional-test
```

Expected: all 17 tests pass (RegisterHotel + ListHotels).

- [ ] **Step 6: Run lint**

```bash
make lint
```

Expected: no errors, Fixed 0 files.

- [ ] **Step 7: Commit**

```bash
git add \
  src/Hotel/UI/Http/Controller/HotelSerializer.php \
  src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelController.php \
  src/Hotel/UI/Http/Controller/ListHotels/HotelCatalogueSerializer.php
git rm src/Hotel/UI/Http/Controller/RegisterHotel/RegisteredHotelSerializer.php
git commit -m "refactor(hotel): extract shared HotelSerializer, remove RegisteredHotelSerializer"
```

---

## Task 2 — Write failing functional tests for `GetHotelController`

**Files:**
- Create: `tests/Hotel/UI/Http/Controller/GetHotel/GetHotelControllerTest.php`

The controller doesn't exist yet — tests will fail with 404.

- [ ] **Step 1: Create the test file**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\UI\Http\Controller\GetHotel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetHotelControllerTest extends WebTestCase
{
    private const array VALID_PAYLOAD = [
        'name' => 'Hotel Ibis Paris',
        'streetAddress' => '15 rue de Rivoli',
        'postalCode' => '75001',
        'city' => 'Paris',
        'country' => 'FR',
    ];

    #[Test]
    public function itReturns200WithCorrectHotelShape(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        /** @var array{id: string} $registered */
        $registered = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $id = $registered['id'];

        $client->request('GET', "/api/hotels/{$id}");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{id: string, name: string, streetAddress: string, postalCode: string, city: string, country: string, createdAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($id, $body['id']);
        self::assertSame('Hotel Ibis Paris', $body['name']);
        self::assertSame('15 rue de Rivoli', $body['streetAddress']);
        self::assertSame('75001', $body['postalCode']);
        self::assertSame('Paris', $body['city']);
        self::assertSame('FR', $body['country']);
        self::assertGreaterThan(0, $body['createdAt']);
    }

    #[Test]
    public function itReturns404WhenHotelDoesNotExist(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/hotels/00000000-0000-0000-0000-000000000000');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }
}
```

- [ ] **Step 2: Run tests — must fail with 404 (route not found)**

```bash
make functional-test ARGS="--filter GetHotelControllerTest"
```

Expected: 2 failures — route `GET /api/hotels/{id}` does not exist.

---

## Task 3 — Implement `GetHotelController`

**Files:**
- Create: `src/Hotel/UI/Http/Controller/GetHotel/GetHotelController.php`

- [ ] **Step 1: Create `GetHotelController`**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\GetHotel;

use App\Hotel\Application\UseCase\GetHotel\GetHotelQuery;
use App\Hotel\UI\Http\Controller\HotelSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class GetHotelController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private HotelSerializer $hotelSerializer,
    ) {
    }

    #[Route('/api/hotels/{id}', name: 'hotel_get_hotel', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a hotel by ID',
        tags: ['Hotels'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Hotel found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'name', type: 'string', example: 'Hotel Ibis Paris'),
                        new OA\Property(property: 'streetAddress', type: 'string', example: '15 rue de Rivoli'),
                        new OA\Property(property: 'postalCode', type: 'string', example: '75001'),
                        new OA\Property(property: 'city', type: 'string', example: 'Paris'),
                        new OA\Property(property: 'country', type: 'string', example: 'FR'),
                        new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Hotel not found',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
                ),
            ),
        ],
    )]
    public function __invoke(string $id): Response
    {
        $hotel = $this->queryBus->ask(new GetHotelQuery($id));

        if (null === $hotel) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->hotelSerializer->serialize($hotel));
    }
}
```

- [ ] **Step 2: Run functional tests for `GetHotelController` — must pass**

```bash
make functional-test ARGS="--filter GetHotelControllerTest"
```

Expected: 2/2 pass.

- [ ] **Step 3: Run the full test suite**

```bash
make functional-test
```

Expected: all 19 tests pass (17 existing + 2 new).

- [ ] **Step 4: Run lint**

```bash
make lint
```

Expected: no errors, Fixed 0 files.

- [ ] **Step 5: Regenerate OpenAPI spec**

```bash
make openapi
```

Expected: `openapi.yaml` updated with `GET /api/hotels/{id}`, no warnings.

- [ ] **Step 6: Commit**

```bash
git add \
  src/Hotel/UI/Http/Controller/GetHotel/GetHotelController.php \
  tests/Hotel/UI/Http/Controller/GetHotel/GetHotelControllerTest.php \
  openapi.yaml
git commit -m "feat(hotel): expose GET /api/hotels/{id}"
```

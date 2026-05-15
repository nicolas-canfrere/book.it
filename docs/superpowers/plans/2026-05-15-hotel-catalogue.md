# Hotel Catalogue Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `GET /api/hotels` — a public, paginated, filterable list of registered Hotels sorted by name.

**Architecture:** Follow the existing Query/QueryHandler pattern. A new `ListHotelsQuery` dispatched via `SyncQueryBus` returns a `HotelPage` value object (list + total). The controller maps query parameters via `#[MapQueryString]` to a typed request DTO, then builds the JSON envelope with `data` + `meta`.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL (raw SQL), PHPUnit, `#[MapQueryString]`, `WebTestCase` for functional tests.

---

## File Map

| Action | Path |
|--------|------|
| Create | `src/Hotel/Domain/Model/HotelPage.php` |
| Modify | `src/Hotel/Domain/Port/HotelRepositoryInterface.php` |
| Modify | `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php` |
| Modify | `tests/Hotel/Infrastructure/Persistence/InMemory/InMemoryHotelRepository.php` |
| Create | `src/Hotel/Application/UseCase/ListHotels/ListHotelsQuery.php` |
| Create | `src/Hotel/Application/UseCase/ListHotels/ListHotelsQueryHandler.php` |
| Create | `tests/Hotel/Application/UseCase/ListHotels/ListHotelsQueryHandlerTest.php` |
| Create | `src/Hotel/UI/Http/Controller/ListHotels/ListHotelsRequest.php` |
| Create | `src/Hotel/UI/Http/Controller/ListHotels/ListHotelsController.php` |
| Create | `tests/Hotel/UI/Http/Controller/ListHotels/ListHotelsControllerTest.php` |

---

## Task 1 — `HotelPage` value object + repository contract

**Files:**
- Create: `src/Hotel/Domain/Model/HotelPage.php`
- Modify: `src/Hotel/Domain/Port/HotelRepositoryInterface.php`

- [ ] **Step 1: Create `HotelPage`**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Model;

final readonly class HotelPage
{
    /** @param list<Hotel> $hotels */
    public function __construct(
        public array $hotels,
        public int $total,
    ) {
    }
}
```

- [ ] **Step 2: Add `list()` to `HotelRepositoryInterface`**

Add this method to the interface (keep existing methods):

```php
public function list(int $page, int $limit, ?string $city, ?string $country): HotelPage;
```

Full file after modification:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Port;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;

interface HotelRepositoryInterface
{
    public function add(Hotel $hotel): void;

    public function get(string $id): ?Hotel;

    public function existsByNameAndAddress(string $name, Address $address): bool;

    public function list(int $page, int $limit, ?string $city, ?string $country): HotelPage;
}
```

- [ ] **Step 3: Run static analysis to verify the interface compiles**

```bash
make static-code-analysis
```

Expected: PHPStan errors on `InMemoryHotelRepository` and `HotelRepository` (not yet implementing `list()`). That's fine — we'll fix them in subsequent tasks.

---

## Task 2 — Implement `list()` in `InMemoryHotelRepository` (test double)

**Files:**
- Modify: `tests/Hotel/Infrastructure/Persistence/InMemory/InMemoryHotelRepository.php`

- [ ] **Step 1: Add `list()` to `InMemoryHotelRepository`**

Add the import at the top and implement the method. Full file after modification:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Infrastructure\Persistence\InMemory;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\Domain\Port\HotelRepositoryInterface;

final class InMemoryHotelRepository implements HotelRepositoryInterface
{
    /** @var array<string, Hotel> */
    private array $hotels = [];

    public function add(Hotel $hotel): void
    {
        $this->hotels[$hotel->id] = $hotel;
    }

    public function get(string $id): ?Hotel
    {
        return $this->hotels[$id] ?? null;
    }

    public function existsByNameAndAddress(string $name, Address $address): bool
    {
        $key = $this->normalize($name, $address);

        foreach ($this->hotels as $hotel) {
            if ($this->normalize($hotel->name, $hotel->address) === $key) {
                return true;
            }
        }

        return false;
    }

    public function list(int $page, int $limit, ?string $city, ?string $country): HotelPage
    {
        $filtered = array_values(array_filter(
            $this->hotels,
            static fn(Hotel $h) =>
                (null === $city || strtolower($h->address->city) === strtolower($city)) &&
                (null === $country || strtolower($h->address->country) === strtolower($country)),
        ));

        usort($filtered, static fn(Hotel $a, Hotel $b) => strcmp($a->name, $b->name));

        $total = count($filtered);
        $hotels = array_slice($filtered, ($page - 1) * $limit, $limit);

        return new HotelPage($hotels, $total);
    }

    private function normalize(string $name, Address $address): string
    {
        return implode('|', array_map(
            static fn(string $s) => strtolower(trim($s)),
            [$name, $address->streetAddress, $address->postalCode, $address->city, $address->country],
        ));
    }
}
```

- [ ] **Step 2: Run static analysis**

```bash
make static-code-analysis
```

Expected: PHPStan error only on `HotelRepository` (Doctrine implementation still missing `list()`).

---

## Task 3 — Write failing unit tests for `ListHotelsQueryHandler`

**Files:**
- Create: `tests/Hotel/Application/UseCase/ListHotels/ListHotelsQueryHandlerTest.php`

The `ListHotelsQuery` and `ListHotelsQueryHandler` don't exist yet — the test file will fail to load at this step. That's the expected "red" state.

- [ ] **Step 1: Create the test file**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\ListHotels;

use App\Hotel\Application\UseCase\ListHotels\ListHotelsQuery;
use App\Hotel\Application\UseCase\ListHotels\ListHotelsQueryHandler;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Tests\Hotel\Infrastructure\Persistence\InMemory\InMemoryHotelRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListHotelsQueryHandlerTest extends TestCase
{
    private InMemoryHotelRepository $repository;
    private ListHotelsQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryHotelRepository();
        $this->handler = new ListHotelsQueryHandler($this->repository);
    }

    #[Test]
    public function itReturnsEmptyPageWhenNoHotelsExist(): void
    {
        $result = ($this->handler)(new ListHotelsQuery());

        self::assertCount(0, $result->hotels);
        self::assertSame(0, $result->total);
    }

    #[Test]
    public function itReturnsAllHotelsSortedByNameAscending(): void
    {
        $this->repository->add($this->makeHotel('1', 'Zara Hotel', 'Paris', 'FR'));
        $this->repository->add($this->makeHotel('2', 'Alpha Hotel', 'Paris', 'FR'));

        $result = ($this->handler)(new ListHotelsQuery());

        self::assertCount(2, $result->hotels);
        self::assertSame(2, $result->total);
        self::assertSame('Alpha Hotel', $result->hotels[0]->name);
        self::assertSame('Zara Hotel', $result->hotels[1]->name);
    }

    #[Test]
    public function itFiltersByCity(): void
    {
        $this->repository->add($this->makeHotel('1', 'Hotel Paris', 'Paris', 'FR'));
        $this->repository->add($this->makeHotel('2', 'Hotel Lyon', 'Lyon', 'FR'));

        $result = ($this->handler)(new ListHotelsQuery(city: 'Paris'));

        self::assertCount(1, $result->hotels);
        self::assertSame('Hotel Paris', $result->hotels[0]->name);
    }

    #[Test]
    public function itFiltersByCountry(): void
    {
        $this->repository->add($this->makeHotel('1', 'Hotel FR', 'Paris', 'FR'));
        $this->repository->add($this->makeHotel('2', 'Hotel DE', 'Berlin', 'DE'));

        $result = ($this->handler)(new ListHotelsQuery(country: 'DE'));

        self::assertCount(1, $result->hotels);
        self::assertSame('Hotel DE', $result->hotels[0]->name);
    }

    #[Test]
    public function itFiltersByCityAndCountrySimultaneously(): void
    {
        $this->repository->add($this->makeHotel('1', 'Hotel Paris FR', 'Paris', 'FR'));
        $this->repository->add($this->makeHotel('2', 'Hotel Paris DE', 'Paris', 'DE'));
        $this->repository->add($this->makeHotel('3', 'Hotel Lyon FR', 'Lyon', 'FR'));

        $result = ($this->handler)(new ListHotelsQuery(city: 'Paris', country: 'FR'));

        self::assertCount(1, $result->hotels);
        self::assertSame('Hotel Paris FR', $result->hotels[0]->name);
    }

    #[Test]
    public function itPaginatesResults(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->repository->add($this->makeHotel((string) $i, "Hotel {$i}", 'Paris', 'FR'));
        }

        $result = ($this->handler)(new ListHotelsQuery(page: 2, limit: 2));

        self::assertCount(2, $result->hotels);
        self::assertSame(5, $result->total);
    }

    #[Test]
    public function itReturnsCorrectTotalWhenPageExceedsResults(): void
    {
        $this->repository->add($this->makeHotel('1', 'Only Hotel', 'Paris', 'FR'));

        $result = ($this->handler)(new ListHotelsQuery(page: 99, limit: 20));

        self::assertCount(0, $result->hotels);
        self::assertSame(1, $result->total);
    }

    private function makeHotel(string $id, string $name, string $city, string $country): Hotel
    {
        return new Hotel(
            $id,
            $name,
            new Address('1 rue Test', '75000', $city, $country),
            new \DateTimeImmutable('2024-01-01'),
        );
    }
}
```

- [ ] **Step 2: Run the tests to confirm they fail**

```bash
make unit-test-quiet ARGS="--filter ListHotelsQueryHandlerTest"
```

Expected: error — class `ListHotelsQuery` not found.

---

## Task 4 — Implement `ListHotelsQuery` and `ListHotelsQueryHandler`

**Files:**
- Create: `src/Hotel/Application/UseCase/ListHotels/ListHotelsQuery.php`
- Create: `src/Hotel/Application/UseCase/ListHotels/ListHotelsQueryHandler.php`

- [ ] **Step 1: Create `ListHotelsQuery`**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\ListHotels;

use App\Hotel\Domain\Model\HotelPage;
use App\Shared\Application\Bus\SyncQueryInterface;

/**
 * @implements SyncQueryInterface<HotelPage>
 */
final readonly class ListHotelsQuery implements SyncQueryInterface
{
    public function __construct(
        public int $page = 1,
        public int $limit = 20,
        public ?string $city = null,
        public ?string $country = null,
    ) {
    }
}
```

- [ ] **Step 2: Create `ListHotelsQueryHandler`**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\ListHotels;

use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class ListHotelsQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private HotelRepositoryInterface $hotelRepository,
    ) {
    }

    public function __invoke(ListHotelsQuery $query): HotelPage
    {
        return $this->hotelRepository->list(
            $query->page,
            $query->limit,
            $query->city,
            $query->country,
        );
    }
}
```

- [ ] **Step 3: Run unit tests — must pass**

```bash
make unit-test-quiet ARGS="--filter ListHotelsQueryHandlerTest"
```

Expected: all 7 tests pass.

- [ ] **Step 4: Commit**

```bash
git add \
  src/Hotel/Domain/Model/HotelPage.php \
  src/Hotel/Domain/Port/HotelRepositoryInterface.php \
  src/Hotel/Application/UseCase/ListHotels/ListHotelsQuery.php \
  src/Hotel/Application/UseCase/ListHotels/ListHotelsQueryHandler.php \
  tests/Hotel/Infrastructure/Persistence/InMemory/InMemoryHotelRepository.php \
  tests/Hotel/Application/UseCase/ListHotels/ListHotelsQueryHandlerTest.php
git commit -m "feat(hotel): add ListHotels query handler with pagination and filtering"
```

---

## Task 5 — Implement `list()` in Doctrine `HotelRepository`

**Files:**
- Modify: `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php`

- [ ] **Step 1: Add `list()` to `HotelRepository`**

Add the import at the top and append the method. Full file after modification:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class HotelRepository implements HotelRepositoryInterface
{
    public function __construct(
        private Connection $bookit,
        private SluggerInterface $slugger,
    ) {
    }

    public function add(Hotel $hotel): void
    {
        $this->bookit->insert('hotel', [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'street_address' => $hotel->address->streetAddress,
            'postal_code' => $hotel->address->postalCode,
            'city' => $hotel->address->city,
            'country' => $hotel->address->country,
            'search_key' => $this->buildSearchKey($hotel->name, $hotel->address),
            'created_at' => $hotel->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $id): ?Hotel
    {
        /** @var array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, name, street_address, postal_code, city, country, created_at FROM hotel WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return new Hotel(
            $row['id'],
            $row['name'],
            new Address($row['street_address'], $row['postal_code'], $row['city'], $row['country']),
            new \DateTimeImmutable($row['created_at']),
        );
    }

    public function existsByNameAndAddress(string $name, Address $address): bool
    {
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM hotel WHERE search_key = :key',
            ['key' => $this->buildSearchKey($name, $address)],
        );

        return $count > 0;
    }

    public function list(int $page, int $limit, ?string $city, ?string $country): HotelPage
    {
        $conditions = [];
        $params = [];

        if (null !== $city) {
            $conditions[] = 'city = :city';
            $params['city'] = $city;
        }

        if (null !== $country) {
            $conditions[] = 'country = :country';
            $params['country'] = $country;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $total = (int) $this->bookit->fetchOne(
            "SELECT COUNT(*) FROM hotel {$where}",
            $params,
        );

        $params['limit'] = $limit;
        $params['offset'] = ($page - 1) * $limit;

        /** @var list<array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string}> $rows */
        $rows = $this->bookit->fetchAllAssociative(
            "SELECT id, name, street_address, postal_code, city, country, created_at FROM hotel {$where} ORDER BY name ASC LIMIT :limit OFFSET :offset",
            $params,
        );

        $hotels = array_map(
            fn(array $row) => new Hotel(
                $row['id'],
                $row['name'],
                new Address($row['street_address'], $row['postal_code'], $row['city'], $row['country']),
                new \DateTimeImmutable($row['created_at']),
            ),
            $rows,
        );

        return new HotelPage($hotels, $total);
    }

    private function buildSearchKey(string $name, Address $address): string
    {
        return implode('|', [
            $this->slugger->slug($name)->lower()->toString(),
            $this->slugger->slug($address->streetAddress)->lower()->toString(),
            strtolower($address->postalCode),
            $this->slugger->slug($address->city)->lower()->toString(),
            strtolower($address->country),
        ]);
    }
}
```

- [ ] **Step 2: Run static analysis — must pass cleanly**

```bash
make static-code-analysis
```

Expected: no errors.

---

## Task 6 — Write failing functional tests for `ListHotelsController`

**Files:**
- Create: `tests/Hotel/UI/Http/Controller/ListHotels/ListHotelsControllerTest.php`

The controller doesn't exist yet — tests will fail with a 404.

- [ ] **Step 1: Create the test file**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\UI\Http\Controller\ListHotels;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class ListHotelsControllerTest extends WebTestCase
{
    private const array HOTEL_PARIS = [
        'name' => 'Hotel Ibis Paris',
        'streetAddress' => '15 rue de Rivoli',
        'postalCode' => '75001',
        'city' => 'Paris',
        'country' => 'FR',
    ];

    private const array HOTEL_LYON = [
        'name' => 'Hotel Lyon Centre',
        'streetAddress' => '3 place Bellecour',
        'postalCode' => '69002',
        'city' => 'Lyon',
        'country' => 'FR',
    ];

    private const array HOTEL_BERLIN = [
        'name' => 'Hotel Berlin Mitte',
        'streetAddress' => '10 Unter den Linden',
        'postalCode' => '10117',
        'city' => 'Berlin',
        'country' => 'DE',
    ];

    #[Test]
    public function itReturns200WithEmptyDataWhenNoHotelsExist(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/hotels');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<mixed>, meta: array{page: int, limit: int, total: int, totalPages: int}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([], $body['data']);
        self::assertSame(1, $body['meta']['page']);
        self::assertSame(20, $body['meta']['limit']);
        self::assertSame(0, $body['meta']['total']);
        self::assertSame(0, $body['meta']['totalPages']);
    }

    #[Test]
    public function itReturnsRegisteredHotelsSortedByName(): void
    {
        $client = static::createClient();

        $this->registerHotel($client, self::HOTEL_PARIS);
        $this->registerHotel($client, self::HOTEL_LYON);

        $client->request('GET', '/api/hotels');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{name: string}>, meta: array{total: int, totalPages: int}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(2, $body['meta']['total']);
        self::assertSame(1, $body['meta']['totalPages']);
        self::assertSame('Hotel Ibis Paris', $body['data'][0]['name']);
        self::assertSame('Hotel Lyon Centre', $body['data'][1]['name']);
    }

    #[Test]
    public function itReturnsCorrectHotelShape(): void
    {
        $client = static::createClient();
        $this->registerHotel($client, self::HOTEL_PARIS);

        $client->request('GET', '/api/hotels');

        /** @var array{data: list<array{id: string, name: string, streetAddress: string, postalCode: string, city: string, country: string, createdAt: int}>} $body */
        $body = json_decode((string) $response = $client->getResponse(), true, 512, \JSON_THROW_ON_ERROR);
        $hotel = $body['data'][0];

        self::assertNotEmpty($hotel['id']);
        self::assertSame('Hotel Ibis Paris', $hotel['name']);
        self::assertSame('15 rue de Rivoli', $hotel['streetAddress']);
        self::assertSame('75001', $hotel['postalCode']);
        self::assertSame('Paris', $hotel['city']);
        self::assertSame('FR', $hotel['country']);
        self::assertGreaterThan(0, $hotel['createdAt']);
    }

    #[Test]
    public function itPaginatesWithDefaultPageSize(): void
    {
        $client = static::createClient();

        for ($i = 1; $i <= 25; ++$i) {
            $this->registerHotel($client, [
                'name' => sprintf('Hotel %02d', $i),
                'streetAddress' => "{$i} rue Test",
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
            ]);
        }

        $client->request('GET', '/api/hotels');

        /** @var array{data: list<mixed>, meta: array{page: int, limit: int, total: int, totalPages: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(20, $body['data']);
        self::assertSame(25, $body['meta']['total']);
        self::assertSame(2, $body['meta']['totalPages']);
    }

    #[Test]
    public function itReturnsSecondPage(): void
    {
        $client = static::createClient();

        for ($i = 1; $i <= 5; ++$i) {
            $this->registerHotel($client, [
                'name' => sprintf('Hotel %02d', $i),
                'streetAddress' => "{$i} rue Test",
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
            ]);
        }

        $client->request('GET', '/api/hotels?page=2&limit=2');

        /** @var array{data: list<array{name: string}>, meta: array{page: int, total: int, totalPages: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['data']);
        self::assertSame(2, $body['meta']['page']);
        self::assertSame(5, $body['meta']['total']);
        self::assertSame(3, $body['meta']['totalPages']);
        self::assertSame('Hotel 03', $body['data'][0]['name']);
        self::assertSame('Hotel 04', $body['data'][1]['name']);
    }

    #[Test]
    public function itFiltersByCity(): void
    {
        $client = static::createClient();

        $this->registerHotel($client, self::HOTEL_PARIS);
        $this->registerHotel($client, self::HOTEL_LYON);

        $client->request('GET', '/api/hotels?city=Lyon');

        /** @var array{data: list<array{city: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['meta']['total']);
        self::assertSame('Lyon', $body['data'][0]['city']);
    }

    #[Test]
    public function itFiltersByCountry(): void
    {
        $client = static::createClient();

        $this->registerHotel($client, self::HOTEL_PARIS);
        $this->registerHotel($client, self::HOTEL_BERLIN);

        $client->request('GET', '/api/hotels?country=DE');

        /** @var array{data: list<array{country: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['meta']['total']);
        self::assertSame('DE', $body['data'][0]['country']);
    }

    #[Test]
    public function itFiltersByCityAndCountry(): void
    {
        $client = static::createClient();

        $this->registerHotel($client, self::HOTEL_PARIS);
        $this->registerHotel($client, self::HOTEL_BERLIN);

        $client->request('GET', '/api/hotels?city=Paris&country=FR');

        /** @var array{data: list<array{name: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['meta']['total']);
        self::assertSame('Hotel Ibis Paris', $body['data'][0]['name']);
    }

    #[Test]
    public function itReturns422WhenPageIsZero(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/hotels?page=0');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns422WhenLimitExceedsMaximum(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/hotels?limit=101');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    /**
     * @param array<string, string> $payload
     */
    private function registerHotel(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, array $payload): void
    {
        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }
}
```

- [ ] **Step 2: Run functional tests — must fail with 404**

```bash
make functional-test ARGS="--filter ListHotelsControllerTest"
```

Expected: failures — route does not exist yet.

---

## Task 7 — Implement `ListHotelsController`

**Files:**
- Create: `src/Hotel/UI/Http/Controller/ListHotels/ListHotelsRequest.php`
- Create: `src/Hotel/UI/Http/Controller/ListHotels/ListHotelsController.php`

- [ ] **Step 1: Create `ListHotelsRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\ListHotels;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListHotelsRequest
{
    public function __construct(
        #[Assert\GreaterThanOrEqual(1)]
        #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1))]
        public int $page = 1,
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(100)]
        #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100))]
        public int $limit = 20,
        #[Assert\Length(min: 1, max: 255)]
        #[OA\Parameter(name: 'city', in: 'query', schema: new OA\Schema(type: 'string', nullable: true))]
        public ?string $city = null,
        #[Assert\Country]
        #[OA\Parameter(name: 'country', in: 'query', schema: new OA\Schema(type: 'string', nullable: true, example: 'FR'))]
        public ?string $country = null,
    ) {
    }
}
```

- [ ] **Step 2: Create `ListHotelsController`**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\ListHotels;

use App\Hotel\Application\UseCase\ListHotels\ListHotelsQuery;
use App\Hotel\Domain\Model\Hotel;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ListHotelsController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
    ) {
    }

    #[Route('/api/hotels', name: 'hotel_list_hotels', methods: ['GET'])]
    #[OA\Get(
        summary: 'List hotels (paginated)',
        tags: ['Hotels'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'city', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'country', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'FR')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Paginated hotel catalogue',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'name', type: 'string'),
                                    new OA\Property(property: 'streetAddress', type: 'string'),
                                    new OA\Property(property: 'postalCode', type: 'string'),
                                    new OA\Property(property: 'city', type: 'string'),
                                    new OA\Property(property: 'country', type: 'string'),
                                    new OA\Property(property: 'createdAt', type: 'integer'),
                                ],
                            ),
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [
                                new OA\Property(property: 'page', type: 'integer', example: 1),
                                new OA\Property(property: 'limit', type: 'integer', example: 20),
                                new OA\Property(property: 'total', type: 'integer', example: 143),
                                new OA\Property(property: 'totalPages', type: 'integer', example: 8),
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
        #[MapQueryString] ListHotelsRequest $request = new ListHotelsRequest(),
    ): Response {
        $hotelPage = $this->queryBus->ask(new ListHotelsQuery(
            $request->page,
            $request->limit,
            $request->city,
            $request->country,
        ));

        return new JsonResponse([
            'data' => array_map(
                fn(Hotel $hotel) => [
                    'id' => $hotel->id,
                    'name' => $hotel->name,
                    'streetAddress' => $hotel->address->streetAddress,
                    'postalCode' => $hotel->address->postalCode,
                    'city' => $hotel->address->city,
                    'country' => $hotel->address->country,
                    'createdAt' => $hotel->createdAt->getTimestamp(),
                ],
                $hotelPage->hotels,
            ),
            'meta' => [
                'page' => $request->page,
                'limit' => $request->limit,
                'total' => $hotelPage->total,
                'totalPages' => (int) ceil($hotelPage->total / $request->limit),
            ],
        ]);
    }
}
```

- [ ] **Step 3: Run functional tests — must pass**

```bash
make functional-test ARGS="--filter ListHotelsControllerTest"
```

Expected: all tests pass.

- [ ] **Step 4: Run the full test suite**

```bash
make unit-test-quiet
make functional-test
```

Expected: all tests pass, no regressions.

- [ ] **Step 5: Run linting**

```bash
make lint
```

Expected: no errors.

- [ ] **Step 6: Regenerate OpenAPI spec**

```bash
make openapi
```

Expected: `openapi.yaml` updated, no warnings.

- [ ] **Step 7: Commit**

```bash
git add \
  src/Hotel/UI/Http/Controller/ListHotels/ListHotelsRequest.php \
  src/Hotel/UI/Http/Controller/ListHotels/ListHotelsController.php \
  src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php \
  tests/Hotel/UI/Http/Controller/ListHotels/ListHotelsControllerTest.php \
  openapi.yaml
git commit -m "feat(hotel): expose GET /api/hotels — paginated hotel catalogue with city/country filters"
```

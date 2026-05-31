# Hotel Amenity Declaration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an operator declare or replace the full list of Hotel Amenities, expose them in Hotel read responses, and filter the Hotel Catalogue by amenity.

**Architecture:** Hotel Amenity is a PHP `BackedEnum` (string) in the Domain. `Hotel` carries `array<HotelAmenity> $amenities = []` and a `withAmenities()` mutator; `withStarRating()` is updated to preserve amenities. Persistence uses a `text[]` column on `hotel.hotel`; filtering uses PostgreSQL's `@>` operator (AND semantics).

**Tech Stack:** PHP 8.4, Symfony 8.0, PostgreSQL 16, Doctrine DBAL (no ORM), Symfony Messenger (sync bus), PHPUnit 11

---

## File Map

| Action | Path | Purpose |
|--------|------|---------|
| Create | `src/Hotel/Domain/ValueObject/HotelAmenity.php` | BackedEnum: 28 values + `values()` helper |
| Modify | `src/Hotel/Domain/Model/Hotel.php` | Add `$amenities`, `withAmenities()`, fix `withStarRating()` |
| Modify | `src/Hotel/Domain/Port/HotelRepositoryInterface.php` | Add `?array $amenities = null` to `list()` |
| Create | migration file (see Task 4) | Add `amenities text[]` column to `hotel.hotel` |
| Modify | `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php` | Persist/query amenities |
| Create | `src/Hotel/Application/UseCase/DeclareHotelAmenities/DeclareHotelAmenitiesCommand.php` | Command DTO |
| Create | `src/Hotel/Application/UseCase/DeclareHotelAmenities/DeclareHotelAmenitiesCommandHandler.php` | Handler |
| Modify | `src/Hotel/Application/UseCase/ListHotels/ListHotelsQuery.php` | Add `?array $amenities = null` |
| Modify | `src/Hotel/Application/UseCase/ListHotels/ListHotelsQueryHandler.php` | Forward `$amenities` to repo |
| Create | `src/Hotel/UI/Http/Controller/DeclareHotelAmenities/DeclareHotelAmenitiesRequest.php` | Request DTO |
| Create | `src/Hotel/UI/Http/Controller/DeclareHotelAmenities/DeclareHotelAmenitiesController.php` | `PATCH /hotels/{id}/amenities` |
| Modify | `src/Hotel/UI/Http/Controller/ListHotels/ListHotelsRequest.php` | Add `?array $amenities = null` |
| Modify | `src/Hotel/UI/Http/Controller/ListHotels/ListHotelsController.php` | Forward `$amenities` to query |
| Modify | `src/Hotel/UI/Http/Controller/HotelSerializer.php` | Add `amenities` field to output |
| Modify | `src/Hotel/UI/Http/Controller/ListHotels/HotelCatalogueSerializer.php` | Update `@return` type hint |
| Create | `tests/Hotel/Application/UseCase/DeclareHotelAmenities/DeclareHotelAmenitiesCommandHandlerTest.php` | Unit tests |
| Create/Modify | `tests/Hotel/Infrastructure/Persistence/Doctrine/HotelRepositoryAmenitiesTest.php` | Integration tests |
| Create | `tests/Hotel/UI/Http/Controller/DeclareHotelAmenities/DeclareHotelAmenitiesControllerTest.php` | Functional tests |
| Modify | `tests/Hotel/UI/Http/Controller/ListHotels/ListHotelsControllerTest.php` | Add amenity filter test |
| Modify | `tests/Hotel/UI/Http/Controller/GetHotel/GetHotelControllerTest.php` | Assert `amenities` in response |

---

### Task 1: Create feature branch

- [ ] **Step 1: Create and switch to branch**

```bash
git checkout -b feat/hotel-amenity-declaration
```

Expected: `Switched to a new branch 'feat/hotel-amenity-declaration'`

---

### Task 2: HotelAmenity enum

**Files:**
- Create: `src/Hotel/Domain/ValueObject/HotelAmenity.php`

- [ ] **Step 1: Create the enum**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Domain\ValueObject;

enum HotelAmenity: string
{
    case Concierge = 'concierge';
    case RoomService = 'room_service';
    case Laundry = 'laundry';
    case AirportShuttle = 'airport_shuttle';
    case LuggageStorage = 'luggage_storage';
    case Restaurant = 'restaurant';
    case Bar = 'bar';
    case Pool = 'pool';
    case Spa = 'spa';
    case Sauna = 'sauna';
    case Gym = 'gym';
    case Jacuzzi = 'jacuzzi';
    case Playground = 'playground';
    case KidsClub = 'kids_club';
    case Babysitting = 'babysitting';
    case Parking = 'parking';
    case EvCharging = 'ev_charging';
    case Elevator = 'elevator';
    case WheelchairAccessible = 'wheelchair_accessible';
    case ConferenceRoom = 'conference_room';
    case BusinessCenter = 'business_center';
    case PetsAllowed = 'pets_allowed';
    case Garden = 'garden';
    case Terrace = 'terrace';
    case BeachAccess = 'beach_access';
    case SkiAccess = 'ski_access';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

- [ ] **Step 2: Run static analysis**

```bash
make static-code-analysis
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/Hotel/Domain/ValueObject/HotelAmenity.php
git commit -m "feat(hotel): add HotelAmenity backed enum"
```

---

### Task 3: Extend Hotel domain model

**Files:**
- Modify: `src/Hotel/Domain/Model/Hotel.php`

- [ ] **Step 1: Replace Hotel.php**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Model;

use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Hotel\Domain\ValueObject\StarRating;

final readonly class Hotel
{
    /**
     * @param array<HotelAmenity> $amenities
     */
    public function __construct(
        public string $id,
        public string $name,
        public Address $address,
        public \DateTimeImmutable $createdAt,
        public ?StarRating $starRating = null,
        public array $amenities = [],
    ) {
    }

    public function withStarRating(?StarRating $starRating): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            address: $this->address,
            createdAt: $this->createdAt,
            starRating: $starRating,
            amenities: $this->amenities,
        );
    }

    /**
     * @param array<HotelAmenity> $amenities
     */
    public function withAmenities(array $amenities): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            address: $this->address,
            createdAt: $this->createdAt,
            starRating: $this->starRating,
            amenities: $amenities,
        );
    }
}
```

- [ ] **Step 2: Run static analysis**

```bash
make static-code-analysis
```

Expected: no errors. (Existing call sites pass positional args up to `$starRating`; `$amenities` defaults to `[]` so nothing breaks.)

- [ ] **Step 3: Run unit tests to confirm nothing regressed**

```bash
make unit-test
```

Expected: all green.

- [ ] **Step 4: Commit**

```bash
git add src/Hotel/Domain/Model/Hotel.php
git commit -m "feat(hotel): extend Hotel model with amenities"
```

---

### Task 4: Database migration

**Files:**
- Create: `migrations/Version<timestamp>.php` (timestamp generated by the tool)

- [ ] **Step 1: Generate migration stub**

```bash
make generate-migration
```

This creates a new file in `migrations/` with an auto-generated timestamp name (e.g. `Version20260531120000.php`).

- [ ] **Step 2: Fill in the migration content**

Open the generated file and replace its body with:

```php
public function getDescription(): string
{
    return 'Add amenities column to hotel.hotel table';
}

public function up(Schema $schema): void
{
    $this->addSql("ALTER TABLE hotel.hotel ADD COLUMN amenities text[] NOT NULL DEFAULT '{}'");
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE hotel.hotel DROP COLUMN amenities');
}
```

- [ ] **Step 3: Run the migration (dev + test DB)**

```bash
make migrate
```

Expected: `[OK] 1 migration executed.`

- [ ] **Step 4: Commit**

```bash
git add migrations/
git commit -m "feat(hotel): add amenities text[] column to hotel.hotel"
```

---

### Task 5: HotelRepository — persist and query amenities

**Files:**
- Modify: `src/Hotel/Domain/Port/HotelRepositoryInterface.php`
- Modify: `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php`
- Create: `tests/Hotel/Infrastructure/Persistence/Doctrine/HotelRepositoryAmenitiesTest.php`

- [ ] **Step 1: Write the failing integration test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Hotel\Infrastructure\Persistence\Doctrine\HotelRepository;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class HotelRepositoryAmenitiesTest extends KernelTestCase
{
    private HotelRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(HotelRepository::class);
    }

    public function test_save_and_reload_amenities(): void
    {
        $hotel = new Hotel(
            'test-amen-id-1',
            'Hotel Amenity Test',
            new Address('1 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
        );
        $this->repository->add($hotel);

        $withAmenities = $hotel->withAmenities([HotelAmenity::Pool, HotelAmenity::Gym]);
        $this->repository->save($withAmenities);

        $reloaded = $this->repository->get('test-amen-id-1');
        self::assertNotNull($reloaded);
        self::assertSame([HotelAmenity::Pool, HotelAmenity::Gym], $reloaded->amenities);
    }

    public function test_save_empty_amenities(): void
    {
        $hotel = new Hotel(
            'test-amen-id-2',
            'Hotel Empty Amenities',
            new Address('2 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
            amenities: [HotelAmenity::Pool],
        );
        $this->repository->add($hotel);
        $this->repository->save($hotel->withAmenities([]));

        $reloaded = $this->repository->get('test-amen-id-2');
        self::assertNotNull($reloaded);
        self::assertSame([], $reloaded->amenities);
    }

    public function test_list_filters_by_amenities_and_semantics(): void
    {
        $hotelA = new Hotel(
            'test-amen-id-3',
            'Hotel Pool Gym',
            new Address('3 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
        );
        $this->repository->add($hotelA);
        $this->repository->save($hotelA->withAmenities([HotelAmenity::Pool, HotelAmenity::Gym]));

        $hotelB = new Hotel(
            'test-amen-id-4',
            'Hotel Pool Only',
            new Address('4 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
        );
        $this->repository->add($hotelB);
        $this->repository->save($hotelB->withAmenities([HotelAmenity::Pool]));

        // Filter pool only — both match
        $pagePool = $this->repository->list(1, 100, null, null, null, [HotelAmenity::Pool]);
        $ids = array_column($pagePool->hotels, 'id');
        self::assertContains('test-amen-id-3', $ids);
        self::assertContains('test-amen-id-4', $ids);

        // Filter pool+gym — only hotelA matches (AND semantics)
        $pageBoth = $this->repository->list(1, 100, null, null, null, [HotelAmenity::Pool, HotelAmenity::Gym]);
        $ids = array_column($pageBoth->hotels, 'id');
        self::assertContains('test-amen-id-3', $ids);
        self::assertNotContains('test-amen-id-4', $ids);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
make functional-test -- --filter HotelRepositoryAmenitiesTest
```

Expected: FAIL (method `list()` signature mismatch or `amenities` column missing from SELECT).

- [ ] **Step 3: Update HotelRepositoryInterface**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Port;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\Domain\ValueObject\HotelAmenity;

interface HotelRepositoryInterface
{
    public function add(Hotel $hotel): void;

    public function save(Hotel $hotel): void;

    public function get(string $id): ?Hotel;

    public function existsByNameAndAddress(string $name, Address $address): bool;

    /**
     * @param array<HotelAmenity>|null $amenities
     */
    public function list(int $page, int $limit, ?string $city, ?string $country, ?int $minStars = null, ?array $amenities = null): HotelPage;
}
```

- [ ] **Step 4: Update HotelRepository**

Replace `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php` with:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Hotel\Domain\ValueObject\StarRating;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class HotelRepository implements HotelRepositoryInterface
{
    public function __construct(
        private Connection $hotelConnection,
        private SluggerInterface $slugger,
    ) {
    }

    public function add(Hotel $hotel): void
    {
        $this->hotelConnection->insert('hotel', [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'street_address' => $hotel->address->streetAddress,
            'postal_code' => $hotel->address->postalCode,
            'city' => $hotel->address->city,
            'country' => $hotel->address->country,
            'search_key' => $this->buildSearchKey($hotel->name, $hotel->address),
            'created_at' => $hotel->createdAt->format('Y-m-d H:i:s'),
            'stars' => $hotel->starRating?->stars,
            'superior' => null !== $hotel->starRating ? $hotel->starRating->superior : false,
            'amenities' => $this->serializeAmenities($hotel->amenities),
        ], [
            'superior' => Types::BOOLEAN,
        ]);
    }

    public function save(Hotel $hotel): void
    {
        $this->hotelConnection->update('hotel', [
            'stars' => $hotel->starRating?->stars,
            'superior' => null !== $hotel->starRating ? $hotel->starRating->superior : false,
            'amenities' => $this->serializeAmenities($hotel->amenities),
        ], ['id' => $hotel->id], [
            'superior' => Types::BOOLEAN,
        ]);
    }

    public function get(string $id): ?Hotel
    {
        /** @var array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string, stars: int|null, superior: string|bool, amenities: string}|false $row */
        $row = $this->hotelConnection->fetchAssociative(
            'SELECT id, name, street_address, postal_code, city, country, created_at, stars, superior, amenities FROM hotel WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function existsByNameAndAddress(string $name, Address $address): bool
    {
        $count = $this->hotelConnection->fetchOne(
            'SELECT COUNT(*) FROM hotel WHERE search_key = :key',
            ['key' => $this->buildSearchKey($name, $address)],
        );

        return $count > 0;
    }

    /**
     * @param array<HotelAmenity>|null $amenities
     */
    public function list(int $page, int $limit, ?string $city, ?string $country, ?int $minStars = null, ?array $amenities = null): HotelPage
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

        if (null !== $minStars) {
            $conditions[] = 'stars >= :minStars';
            $params['minStars'] = $minStars;
        }

        if (null !== $amenities && [] !== $amenities) {
            $conditions[] = 'amenities @> :amenities::text[]';
            $params['amenities'] = $this->serializeAmenities($amenities);
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        /** @var int|string $count */
        $count = $this->hotelConnection->fetchOne(
            "SELECT COUNT(*) FROM hotel {$where}",
            $params,
        );
        $total = (int) $count;

        $params['limit'] = $limit;
        $params['offset'] = ($page - 1) * $limit;

        /** @var list<array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string, stars: int|null, superior: string|bool, amenities: string}> $rows */
        $rows = $this->hotelConnection->fetchAllAssociative(
            "SELECT id, name, street_address, postal_code, city, country, created_at, stars, superior, amenities FROM hotel {$where} ORDER BY name ASC LIMIT :limit OFFSET :offset",
            $params,
        );

        return new HotelPage(array_map($this->hydrate(...), $rows), $total);
    }

    /**
     * @param array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string, stars: int|null, superior: string|bool, amenities: string} $row
     */
    private function hydrate(array $row): Hotel
    {
        $starRating = null !== $row['stars']
            ? new StarRating((int) $row['stars'], 't' === $row['superior'] || true === $row['superior'])
            : null;

        return new Hotel(
            $row['id'],
            $row['name'],
            new Address($row['street_address'], $row['postal_code'], $row['city'], $row['country']),
            new \DateTimeImmutable($row['created_at']),
            $starRating,
            $this->parseAmenities($row['amenities']),
        );
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

    /** @return array<HotelAmenity> */
    private function parseAmenities(string $raw): array
    {
        if ('{}' === $raw) {
            return [];
        }

        return array_map(HotelAmenity::from(...), explode(',', trim($raw, '{}')));
    }

    /** @param array<HotelAmenity> $amenities */
    private function serializeAmenities(array $amenities): string
    {
        if ([] === $amenities) {
            return '{}';
        }

        return '{' . implode(',', array_map(static fn(HotelAmenity $a) => $a->value, $amenities)) . '}';
    }
}
```

- [ ] **Step 5: Run the integration test**

```bash
make functional-test -- --filter HotelRepositoryAmenitiesTest
```

Expected: all 3 tests green.

- [ ] **Step 6: Run static analysis**

```bash
make static-code-analysis
```

Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add src/Hotel/Domain/Port/HotelRepositoryInterface.php \
        src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php \
        tests/Hotel/Infrastructure/Persistence/Doctrine/HotelRepositoryAmenitiesTest.php
git commit -m "feat(hotel): persist and query amenities in HotelRepository"
```

---

### Task 6: DeclareHotelAmenities use case

**Files:**
- Create: `src/Hotel/Application/UseCase/DeclareHotelAmenities/DeclareHotelAmenitiesCommand.php`
- Create: `src/Hotel/Application/UseCase/DeclareHotelAmenities/DeclareHotelAmenitiesCommandHandler.php`
- Create: `tests/Hotel/Application/UseCase/DeclareHotelAmenities/DeclareHotelAmenitiesCommandHandlerTest.php`

- [ ] **Step 1: Write the failing unit test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\DeclareHotelAmenities;

use App\Hotel\Application\UseCase\DeclareHotelAmenities\DeclareHotelAmenitiesCommand;
use App\Hotel\Application\UseCase\DeclareHotelAmenities\DeclareHotelAmenitiesCommandHandler;
use App\Hotel\Domain\Exception\HotelNotFoundException;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeclareHotelAmenitiesCommandHandlerTest extends TestCase
{
    private HotelRepositoryInterface&MockObject $repository;
    private DeclareHotelAmenitiesCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(HotelRepositoryInterface::class);
        $this->handler = new DeclareHotelAmenitiesCommandHandler($this->repository);
    }

    public function test_throws_when_hotel_not_found(): void
    {
        $this->repository->method('get')->willReturn(null);

        $this->expectException(HotelNotFoundException::class);

        ($this->handler)(new DeclareHotelAmenitiesCommand('unknown-id', []));
    }

    public function test_saves_declared_amenities(): void
    {
        $hotel = new Hotel(
            'hotel-id',
            'Test Hotel',
            new Address('1 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable(),
        );
        $this->repository->method('get')->willReturn($hotel);
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(
                static fn(Hotel $h) => $h->amenities === [HotelAmenity::Pool, HotelAmenity::Gym]
            ));

        ($this->handler)(new DeclareHotelAmenitiesCommand('hotel-id', ['pool', 'gym']));
    }

    public function test_saves_empty_list(): void
    {
        $hotel = new Hotel(
            'hotel-id',
            'Test Hotel',
            new Address('1 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable(),
            amenities: [HotelAmenity::Pool],
        );
        $this->repository->method('get')->willReturn($hotel);
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(
                static fn(Hotel $h) => [] === $h->amenities
            ));

        ($this->handler)(new DeclareHotelAmenitiesCommand('hotel-id', []));
    }
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
make unit-test -- --filter DeclareHotelAmenitiesCommandHandlerTest
```

Expected: FAIL (class not found).

- [ ] **Step 3: Create the Command**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\DeclareHotelAmenities;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class DeclareHotelAmenitiesCommand implements SyncCommandInterface
{
    /**
     * @param string[] $amenities
     */
    public function __construct(
        public string $hotelId,
        public array $amenities,
    ) {
    }
}
```

- [ ] **Step 4: Create the Handler**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\DeclareHotelAmenities;

use App\Hotel\Domain\Exception\HotelNotFoundException;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class DeclareHotelAmenitiesCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private HotelRepositoryInterface $hotelRepository,
    ) {
    }

    public function __invoke(DeclareHotelAmenitiesCommand $command): void
    {
        $hotel = $this->hotelRepository->get($command->hotelId);

        if (null === $hotel) {
            throw new HotelNotFoundException($command->hotelId);
        }

        $amenities = array_map(HotelAmenity::from(...), $command->amenities);

        $this->hotelRepository->save($hotel->withAmenities($amenities));
    }
}
```

- [ ] **Step 5: Run unit tests**

```bash
make unit-test -- --filter DeclareHotelAmenitiesCommandHandlerTest
```

Expected: all 3 tests green.

- [ ] **Step 6: Run static analysis**

```bash
make static-code-analysis
```

Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add src/Hotel/Application/UseCase/DeclareHotelAmenities/ \
        tests/Hotel/Application/UseCase/DeclareHotelAmenities/
git commit -m "feat(hotel): add DeclareHotelAmenities command and handler"
```

---

### Task 7: DeclareHotelAmenities HTTP endpoint

**Files:**
- Create: `src/Hotel/UI/Http/Controller/DeclareHotelAmenities/DeclareHotelAmenitiesRequest.php`
- Create: `src/Hotel/UI/Http/Controller/DeclareHotelAmenities/DeclareHotelAmenitiesController.php`
- Create: `tests/Hotel/UI/Http/Controller/DeclareHotelAmenities/DeclareHotelAmenitiesControllerTest.php`

- [ ] **Step 1: Write the failing functional test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\UI\Http\Controller\DeclareHotelAmenities;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class DeclareHotelAmenitiesControllerTest extends WebTestCase
{
    private function registerHotel(KernelBrowser $client, string $name = 'Test Hotel'): string
    {
        $client->request('POST', '/hotels', content: json_encode([
            'name' => $name,
            'streetAddress' => '1 rue Test',
            'postalCode' => '75001',
            'city' => 'Paris',
            'country' => 'FR',
        ]), server: ['CONTENT_TYPE' => 'application/json']);
        $data = json_decode($client->getResponse()->getContent(), true);

        return $data['id'];
    }

    public function test_declares_amenities_on_existing_hotel(): void
    {
        $client = self::createClient();
        $id = $this->registerHotel($client);

        $client->request('PATCH', "/hotels/{$id}/amenities", content: json_encode([
            'amenities' => ['pool', 'gym'],
        ]), server: ['CONTENT_TYPE' => 'application/json']);

        self::assertResponseStatusCodeSame(204);
    }

    public function test_replaces_amenities_with_empty_list(): void
    {
        $client = self::createClient();
        $id = $this->registerHotel($client, 'Empty Amenities Hotel');

        $client->request('PATCH', "/hotels/{$id}/amenities", content: json_encode([
            'amenities' => ['pool'],
        ]), server: ['CONTENT_TYPE' => 'application/json']);
        self::assertResponseStatusCodeSame(204);

        $client->request('PATCH', "/hotels/{$id}/amenities", content: json_encode([
            'amenities' => [],
        ]), server: ['CONTENT_TYPE' => 'application/json']);
        self::assertResponseStatusCodeSame(204);
    }

    public function test_returns_404_for_unknown_hotel(): void
    {
        $client = self::createClient();

        $client->request('PATCH', '/hotels/00000000-0000-4000-a000-000000000000/amenities', content: json_encode([
            'amenities' => ['pool'],
        ]), server: ['CONTENT_TYPE' => 'application/json']);

        self::assertResponseStatusCodeSame(404);
    }

    public function test_returns_422_for_unknown_amenity_value(): void
    {
        $client = self::createClient();
        $id = $this->registerHotel($client, 'Invalid Amenity Hotel');

        $client->request('PATCH', "/hotels/{$id}/amenities", content: json_encode([
            'amenities' => ['not_a_real_amenity'],
        ]), server: ['CONTENT_TYPE' => 'application/json']);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_returns_422_for_duplicate_values(): void
    {
        $client = self::createClient();
        $id = $this->registerHotel($client, 'Duplicate Amenity Hotel');

        $client->request('PATCH', "/hotels/{$id}/amenities", content: json_encode([
            'amenities' => ['pool', 'pool'],
        ]), server: ['CONTENT_TYPE' => 'application/json']);

        self::assertResponseStatusCodeSame(422);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
make functional-test -- --filter DeclareHotelAmenitiesControllerTest
```

Expected: FAIL (route not found, 404 or 405).

- [ ] **Step 3: Create the Request DTO**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\DeclareHotelAmenities;

use App\Hotel\Domain\ValueObject\HotelAmenity;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class DeclareHotelAmenitiesRequest
{
    /**
     * @param string[] $amenities
     */
    public function __construct(
        #[Assert\All(constraints: [new Assert\Choice(callback: [HotelAmenity::class, 'values'])])]
        #[Assert\Unique]
        #[OA\Property(
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['pool', 'gym', 'parking'],
        )]
        public array $amenities = [],
    ) {
    }
}
```

- [ ] **Step 4: Create the Controller**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\DeclareHotelAmenities;

use App\Hotel\Application\UseCase\DeclareHotelAmenities\DeclareHotelAmenitiesCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class DeclareHotelAmenitiesController
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    #[Route(
        path: '/hotels/{id}/amenities',
        name: 'hotel_declare_amenities',
        requirements: ['id' => Requirement::UUID_V4],
        methods: ['PATCH'],
    )]
    #[OA\Patch(
        summary: 'Declare or replace the Hotel Amenity list',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: DeclareHotelAmenitiesRequest::class)),
        ),
        tags: ['Hotels'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Amenities declared'),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Hotel not found',
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
        string $id,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        DeclareHotelAmenitiesRequest $request,
    ): Response {
        $this->commandBus->execute(new DeclareHotelAmenitiesCommand(
            hotelId: $id,
            amenities: $request->amenities,
        ));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 5: Run functional tests**

```bash
make functional-test -- --filter DeclareHotelAmenitiesControllerTest
```

Expected: all 5 tests green.

- [ ] **Step 6: Run static analysis**

```bash
make static-code-analysis
```

Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add src/Hotel/UI/Http/Controller/DeclareHotelAmenities/ \
        tests/Hotel/UI/Http/Controller/DeclareHotelAmenities/
git commit -m "feat(hotel): add PATCH /hotels/{id}/amenities endpoint"
```

---

### Task 8: ListHotels amenity filter

**Files:**
- Modify: `src/Hotel/Application/UseCase/ListHotels/ListHotelsQuery.php`
- Modify: `src/Hotel/Application/UseCase/ListHotels/ListHotelsQueryHandler.php`
- Modify: `src/Hotel/UI/Http/Controller/ListHotels/ListHotelsRequest.php`
- Modify: `src/Hotel/UI/Http/Controller/ListHotels/ListHotelsController.php`
- Modify: `tests/Hotel/UI/Http/Controller/ListHotels/ListHotelsControllerTest.php`

- [ ] **Step 1: Add a failing test to ListHotelsControllerTest**

Open `tests/Hotel/UI/Http/Controller/ListHotels/ListHotelsControllerTest.php` and add this method:

```php
public function test_filters_by_amenities(): void
{
    $client = self::createClient();

    // Register two hotels
    $client->request('POST', '/hotels', content: json_encode([
        'name' => 'Pool Gym Hotel',
        'streetAddress' => '10 rue Filter',
        'postalCode' => '75002',
        'city' => 'Lyon',
        'country' => 'FR',
    ]), server: ['CONTENT_TYPE' => 'application/json']);
    $idA = json_decode($client->getResponse()->getContent(), true)['id'];

    $client->request('POST', '/hotels', content: json_encode([
        'name' => 'Pool Only Hotel',
        'streetAddress' => '11 rue Filter',
        'postalCode' => '75002',
        'city' => 'Lyon',
        'country' => 'FR',
    ]), server: ['CONTENT_TYPE' => 'application/json']);
    $idB = json_decode($client->getResponse()->getContent(), true)['id'];

    // Declare amenities
    $client->request('PATCH', "/hotels/{$idA}/amenities", content: json_encode(['amenities' => ['pool', 'gym']]), server: ['CONTENT_TYPE' => 'application/json']);
    $client->request('PATCH', "/hotels/{$idB}/amenities", content: json_encode(['amenities' => ['pool']]), server: ['CONTENT_TYPE' => 'application/json']);

    // Filter pool only — both match
    $client->request('GET', '/hotels?amenities[]=pool&city=Lyon');
    self::assertResponseIsSuccessful();
    $data = json_decode($client->getResponse()->getContent(), true);
    $ids = array_column($data['data'], 'id');
    self::assertContains($idA, $ids);
    self::assertContains($idB, $ids);

    // Filter pool+gym — only idA
    $client->request('GET', '/hotels?amenities[]=pool&amenities[]=gym&city=Lyon');
    self::assertResponseIsSuccessful();
    $data = json_decode($client->getResponse()->getContent(), true);
    $ids = array_column($data['data'], 'id');
    self::assertContains($idA, $ids);
    self::assertNotContains($idB, $ids);

    // Unknown amenity — 422
    $client->request('GET', '/hotels?amenities[]=not_an_amenity');
    self::assertResponseStatusCodeSame(422);
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
make functional-test -- --filter ListHotelsControllerTest::test_filters_by_amenities
```

Expected: FAIL.

- [ ] **Step 3: Update ListHotelsQuery**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\ListHotels;

use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Shared\Application\Bus\SyncQueryInterface;

/**
 * @implements SyncQueryInterface<HotelPage>
 */
final readonly class ListHotelsQuery implements SyncQueryInterface
{
    /**
     * @param array<HotelAmenity>|null $amenities
     */
    public function __construct(
        public int $page = 1,
        public int $limit = 20,
        public ?string $city = null,
        public ?string $country = null,
        public ?int $minStars = null,
        public ?array $amenities = null,
    ) {
    }
}
```

- [ ] **Step 4: Update ListHotelsQueryHandler**

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
            $query->minStars,
            $query->amenities,
        );
    }
}
```

- [ ] **Step 5: Update ListHotelsRequest**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\ListHotels;

use App\Hotel\Domain\ValueObject\HotelAmenity;
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
        #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1))]
        public int $limit = 20,
        #[Assert\Length(min: 1, max: 255)]
        #[OA\Parameter(name: 'city', in: 'query', schema: new OA\Schema(type: 'string', nullable: true))]
        public ?string $city = null,
        #[Assert\Country]
        #[OA\Parameter(name: 'country', in: 'query', schema: new OA\Schema(type: 'string', example: 'FR', nullable: true))]
        public ?string $country = null,
        #[Assert\Range(min: 1, max: 5)]
        #[OA\Parameter(name: 'minStars', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 5, nullable: true))]
        public ?int $minStars = null,
        #[Assert\All(constraints: [new Assert\Choice(callback: [HotelAmenity::class, 'values'])])]
        #[OA\Parameter(
            name: 'amenities[]',
            in: 'query',
            schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string')),
        )]
        public ?array $amenities = null,
    ) {
    }
}
```

- [ ] **Step 6: Update ListHotelsController**

Open `src/Hotel/UI/Http/Controller/ListHotels/ListHotelsController.php` and update the query construction to pass `amenities`. Find where `ListHotelsQuery` is instantiated and add the `amenities` argument:

```php
new ListHotelsQuery(
    page: $request->page,
    limit: $request->limit,
    city: $request->city,
    country: $request->country,
    minStars: $request->minStars,
    amenities: null !== $request->amenities
        ? array_map(\App\Hotel\Domain\ValueObject\HotelAmenity::from(...), $request->amenities)
        : null,
)
```

- [ ] **Step 7: Run functional tests**

```bash
make functional-test -- --filter ListHotelsControllerTest::test_filters_by_amenities
```

Expected: green.

- [ ] **Step 8: Run static analysis**

```bash
make static-code-analysis
```

Expected: no errors.

- [ ] **Step 9: Commit**

```bash
git add src/Hotel/Application/UseCase/ListHotels/ \
        src/Hotel/UI/Http/Controller/ListHotels/ListHotelsRequest.php \
        src/Hotel/UI/Http/Controller/ListHotels/ListHotelsController.php \
        tests/Hotel/UI/Http/Controller/ListHotels/ListHotelsControllerTest.php
git commit -m "feat(hotel): add amenity filter to Hotel Catalogue"
```

---

### Task 9: Add amenities to Hotel read responses

**Files:**
- Modify: `src/Hotel/UI/Http/Controller/HotelSerializer.php`
- Modify: `src/Hotel/UI/Http/Controller/ListHotels/HotelCatalogueSerializer.php`
- Modify: `tests/Hotel/UI/Http/Controller/GetHotel/GetHotelControllerTest.php`

- [ ] **Step 1: Add a failing assertion to GetHotelControllerTest**

Open `tests/Hotel/UI/Http/Controller/GetHotel/GetHotelControllerTest.php` and add or extend a test to assert:

```php
public function test_response_includes_amenities_field(): void
{
    $client = self::createClient();

    // Register hotel
    $client->request('POST', '/hotels', content: json_encode([
        'name' => 'Amenities Response Hotel',
        'streetAddress' => '5 rue Serializer',
        'postalCode' => '75003',
        'city' => 'Paris',
        'country' => 'FR',
    ]), server: ['CONTENT_TYPE' => 'application/json']);
    $id = json_decode($client->getResponse()->getContent(), true)['id'];

    // Declare amenities
    $client->request('PATCH', "/hotels/{$id}/amenities", content: json_encode([
        'amenities' => ['pool', 'parking'],
    ]), server: ['CONTENT_TYPE' => 'application/json']);

    // Get hotel and assert amenities
    $client->request('GET', "/hotels/{$id}");
    self::assertResponseIsSuccessful();
    $data = json_decode($client->getResponse()->getContent(), true);
    self::assertArrayHasKey('amenities', $data);
    self::assertEqualsCanonicalizing(['pool', 'parking'], $data['amenities']);
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
make functional-test -- --filter GetHotelControllerTest::test_response_includes_amenities_field
```

Expected: FAIL (no `amenities` key in response).

- [ ] **Step 3: Update HotelSerializer**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller;

use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\ValueObject\HotelAmenity;

final class HotelSerializer
{
    /**
     * @return array{id: string, name: string, streetAddress: string, postalCode: string, city: string, country: string, createdAt: int, starRating: array{stars: int, superior: bool}|null, amenities: string[]}
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
            'starRating' => null !== $hotel->starRating
                ? ['stars' => $hotel->starRating->stars, 'superior' => $hotel->starRating->superior]
                : null,
            'amenities' => array_map(static fn(HotelAmenity $a) => $a->value, $hotel->amenities),
        ];
    }
}
```

- [ ] **Step 4: Update HotelCatalogueSerializer `@return` type hint**

In `HotelCatalogueSerializer.php`, update the `@return` docblock to include `amenities: string[]` in the hotel shape:

```php
/**
 * @return array{
 *     data: list<array{id: string, name: string, streetAddress: string, postalCode: string, city: string, country: string, createdAt: int, starRating: array{stars: int, superior: bool}|null, amenities: string[]}>,
 *     meta: array{page: int, limit: int, total: int, totalPages: int}
 * }
 */
```

(The serialization logic itself does not change — it still delegates to `HotelSerializer::serialize()`.)

- [ ] **Step 5: Run functional tests**

```bash
make functional-test -- --filter GetHotelControllerTest::test_response_includes_amenities_field
```

Expected: green.

- [ ] **Step 6: Run full test suite**

```bash
make test
```

Expected: all green.

- [ ] **Step 7: Run static analysis**

```bash
make static-code-analysis
```

Expected: no errors.

- [ ] **Step 8: Commit**

```bash
git add src/Hotel/UI/Http/Controller/HotelSerializer.php \
        src/Hotel/UI/Http/Controller/ListHotels/HotelCatalogueSerializer.php \
        tests/Hotel/UI/Http/Controller/GetHotel/GetHotelControllerTest.php
git commit -m "feat(hotel): include amenities in Hotel read responses"
```

---

### Task 10: OpenAPI spec + final lint

- [ ] **Step 1: Regenerate OpenAPI**

```bash
make openapi
```

Expected: `openapi.yaml` updated with the new `PATCH /hotels/{id}/amenities` route and the `amenities` field in hotel schemas. No errors.

- [ ] **Step 2: Full lint pass**

```bash
make lint
```

Expected: CS Fixer, PHPStan, and Deptrac all green.

- [ ] **Step 3: Commit**

```bash
git add openapi.yaml
git commit -m "chore(hotel): regenerate OpenAPI spec after amenity declaration"
```

---

## Completion

All tasks done. The branch `feat/hotel-amenity-declaration` is ready. Use `superpowers:finishing-a-development-branch` to open the PR.

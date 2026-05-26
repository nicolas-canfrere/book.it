# Hotel Star Rating Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an optional Star Rating (1–5 stars + Superior boolean, European Hotelstars Union) to the Hotel model, exposable via all Hotel endpoints and filterable in the Hotel Catalogue via `minStars`.

**Architecture:** New `StarRating` value object in the Hotel domain. Hotel model extended with optional `?StarRating`, updated immutably via `withStarRating()`. Two use cases impacted: `RegisterHotel` (optional stars at creation) and new `ClassifyHotel` (post-registration update). Pure DBAL repository — no Doctrine ORM entity mapping required. Hotel Catalogue gains a `minStars` query filter.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL (raw SQL for Hotel), PHPUnit

---

## File Map

| Action | Path | Purpose |
|--------|------|---------|
| Create | `src/Hotel/Domain/ValueObject/StarRating.php` | Value object: stars (1–5) + superior boolean |
| Modify | `src/Hotel/Domain/Model/Hotel.php` | Add `?StarRating $starRating`, add `withStarRating()` |
| Create | `migrations/VersionXXXXXX.php` | Add `stars`, `superior` columns to `hotel` table |
| Modify | `src/Hotel/Domain/Port/HotelRepositoryInterface.php` | Add `save()`, update `list()` signature |
| Modify | `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php` | Implement `save()`, update all SQL queries |
| Modify | `src/Hotel/UI/Http/Controller/HotelSerializer.php` | Add `starRating` to serialized output |
| Modify | `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommand.php` | Add `?StarRating $starRating` |
| Modify | `src/Hotel/Application/Service/RegisterHotelCommandFactory.php` | Accept `?int $stars`, `bool $superior` |
| Modify | `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandler.php` | Pass starRating to Hotel constructor |
| Modify | `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelRequest.php` | Add `stars`, `superior` fields |
| Modify | `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelController.php` | Pass stars/superior to factory |
| Create | `src/Hotel/Domain/Exception/HotelNotFoundException.php` | Domain exception for missing Hotel |
| Modify | `config/services/exceptions.yaml` | Map `HotelNotFoundException` → 404 |
| Create | `src/Hotel/Application/UseCase/ClassifyHotel/ClassifyHotelCommand.php` | Command: hotelId + stars + superior |
| Create | `src/Hotel/Application/UseCase/ClassifyHotel/ClassifyHotelCommandHandler.php` | Load hotel, apply starRating, save |
| Create | `src/Hotel/UI/Http/Controller/ClassifyHotel/ClassifyHotelRequest.php` | Request DTO with cross-field validation |
| Create | `src/Hotel/UI/Http/Controller/ClassifyHotel/ClassifyHotelController.php` | `PATCH /hotels/{id}/star-rating` |
| Modify | `src/Hotel/Application/UseCase/ListHotels/ListHotelsQuery.php` | Add `?int $minStars` |
| Modify | `src/Hotel/Application/UseCase/ListHotels/ListHotelsQueryHandler.php` | Pass minStars to repository |
| Modify | `src/Hotel/UI/Http/Controller/ListHotels/ListHotelsRequest.php` | Add `minStars` query param |
| Modify | `src/Hotel/UI/Http/Controller/ListHotels/ListHotelsController.php` | Pass minStars to query |

---

### Task 1: StarRating value object

**Files:**
- Create: `src/Hotel/Domain/ValueObject/StarRating.php`
- Create: `tests/Hotel/Unit/Domain/ValueObject/StarRatingTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Hotel/Unit/Domain/ValueObject/StarRatingTest.php
declare(strict_types=1);

namespace App\Tests\Hotel\Unit\Domain\ValueObject;

use App\Hotel\Domain\ValueObject\StarRating;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class StarRatingTest extends TestCase
{
    public function test_it_creates_a_basic_star_rating(): void
    {
        $rating = new StarRating(3, false);

        self::assertSame(3, $rating->stars);
        self::assertFalse($rating->superior);
    }

    public function test_it_creates_a_superior_star_rating(): void
    {
        $rating = new StarRating(4, true);

        self::assertSame(4, $rating->stars);
        self::assertTrue($rating->superior);
    }

    public function test_it_rejects_stars_below_1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stars must be between 1 and 5');

        new StarRating(0, false);
    }

    public function test_it_rejects_stars_above_5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stars must be between 1 and 5');

        new StarRating(6, false);
    }

    public function test_boundary_1_star_is_valid(): void
    {
        $rating = new StarRating(1, false);
        self::assertSame(1, $rating->stars);
    }

    public function test_boundary_5_stars_is_valid(): void
    {
        $rating = new StarRating(5, true);
        self::assertSame(5, $rating->stars);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
make unit-test
```
Expected: FAIL — `StarRating` class not found.

- [ ] **Step 3: Write the implementation**

```php
<?php
// src/Hotel/Domain/ValueObject/StarRating.php
declare(strict_types=1);

namespace App\Hotel\Domain\ValueObject;

final readonly class StarRating
{
    public function __construct(
        public int $stars,
        public bool $superior,
    ) {
        if ($stars < 1 || $stars > 5) {
            throw new \InvalidArgumentException(
                sprintf('Stars must be between 1 and 5, %d given.', $stars)
            );
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
make unit-test
```
Expected: all StarRatingTest cases pass.

- [ ] **Step 5: Lint**

```bash
make lint
```

- [ ] **Step 6: Commit**

```bash
git add src/Hotel/Domain/ValueObject/StarRating.php tests/Hotel/Unit/Domain/ValueObject/StarRatingTest.php
git commit -m "feat(hotel): add StarRating value object (1-5 stars + superior)"
```

---

### Task 2: Hotel model — add StarRating

**Files:**
- Modify: `src/Hotel/Domain/Model/Hotel.php`

- [ ] **Step 1: Update Hotel**

Replace the entire file:

```php
<?php
// src/Hotel/Domain/Model/Hotel.php
declare(strict_types=1);

namespace App\Hotel\Domain\Model;

use App\Hotel\Domain\ValueObject\StarRating;

final readonly class Hotel
{
    public function __construct(
        public string $id,
        public string $name,
        public Address $address,
        public \DateTimeImmutable $createdAt,
        public ?StarRating $starRating = null,
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
        );
    }
}
```

- [ ] **Step 2: Lint and run tests**

```bash
make lint && make unit-test
```
Expected: all tests pass — `starRating` defaults to `null`, existing callers unaffected.

- [ ] **Step 3: Commit**

```bash
git add src/Hotel/Domain/Model/Hotel.php
git commit -m "feat(hotel): add optional StarRating to Hotel model"
```

---

### Task 3: Database migration

**Files:**
- Create: new migration file in `migrations/` (filename generated by command)

- [ ] **Step 1: Generate an empty migration file**

```bash
docker compose run --rm php bin/console doctrine:migrations:generate
```
Expected output: `Generated new migration class to "migrations/VersionYYYYMMDDHHIISS.php"`.

- [ ] **Step 2: Fill in the SQL**

Open the generated file and replace the empty `up()` and `down()` methods:

```php
public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE hotel ADD COLUMN stars SMALLINT DEFAULT NULL');
    $this->addSql('ALTER TABLE hotel ADD COLUMN superior BOOLEAN NOT NULL DEFAULT FALSE');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE hotel DROP COLUMN superior');
    $this->addSql('ALTER TABLE hotel DROP COLUMN stars');
}
```

- [ ] **Step 3: Apply the migration**

```bash
make migrate
```
Expected: migration runs without error.

- [ ] **Step 4: Commit**

```bash
git add migrations/
git commit -m "feat(hotel): add stars and superior columns to hotel table"
```

---

### Task 4: HotelRepository — save(), update SQL queries, minStars filter

**Files:**
- Modify: `src/Hotel/Domain/Port/HotelRepositoryInterface.php`
- Modify: `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php`

- [ ] **Step 1: Update the interface**

```php
<?php
// src/Hotel/Domain/Port/HotelRepositoryInterface.php
declare(strict_types=1);

namespace App\Hotel\Domain\Port;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;

interface HotelRepositoryInterface
{
    public function add(Hotel $hotel): void;

    public function save(Hotel $hotel): void;

    public function get(string $id): ?Hotel;

    public function existsByNameAndAddress(string $name, Address $address): bool;

    public function list(int $page, int $limit, ?string $city, ?string $country, ?int $minStars = null): HotelPage;
}
```

- [ ] **Step 2: Update the Doctrine implementation**

Replace the entire file:

```php
<?php
// src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php
declare(strict_types=1);

namespace App\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\StarRating;
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
            'stars' => $hotel->starRating?->stars,
            'superior' => $hotel->starRating?->superior ?? false,
        ]);
    }

    public function save(Hotel $hotel): void
    {
        $this->bookit->update('hotel', [
            'stars' => $hotel->starRating?->stars,
            'superior' => $hotel->starRating?->superior ?? false,
        ], ['id' => $hotel->id]);
    }

    public function get(string $id): ?Hotel
    {
        /** @var array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string, stars: int|null, superior: bool}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, name, street_address, postal_code, city, country, created_at, stars, superior FROM hotel WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function existsByNameAndAddress(string $name, Address $address): bool
    {
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM hotel WHERE search_key = :key',
            ['key' => $this->buildSearchKey($name, $address)],
        );

        return $count > 0;
    }

    public function list(int $page, int $limit, ?string $city, ?string $country, ?int $minStars = null): HotelPage
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

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        /** @var int|string $count */
        $count = $this->bookit->fetchOne(
            "SELECT COUNT(*) FROM hotel {$where}",
            $params,
        );
        $total = (int) $count;

        $params['limit'] = $limit;
        $params['offset'] = ($page - 1) * $limit;

        /** @var list<array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string, stars: int|null, superior: bool}> $rows */
        $rows = $this->bookit->fetchAllAssociative(
            "SELECT id, name, street_address, postal_code, city, country, created_at, stars, superior FROM hotel {$where} ORDER BY name ASC LIMIT :limit OFFSET :offset",
            $params,
        );

        return new HotelPage(array_map($this->hydrate(...), $rows), $total);
    }

    /**
     * @param array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string, stars: int|null, superior: bool} $row
     */
    private function hydrate(array $row): Hotel
    {
        $starRating = null !== $row['stars']
            ? new StarRating((int) $row['stars'], (bool) $row['superior'])
            : null;

        return new Hotel(
            $row['id'],
            $row['name'],
            new Address($row['street_address'], $row['postal_code'], $row['city'], $row['country']),
            new \DateTimeImmutable($row['created_at']),
            $starRating,
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
}
```

- [ ] **Step 3: Lint**

```bash
make lint
```

- [ ] **Step 4: Commit**

```bash
git add src/Hotel/Domain/Port/HotelRepositoryInterface.php src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php
git commit -m "feat(hotel): add save(), star rating columns to HotelRepository"
```

---

### Task 5: HotelSerializer — add starRating

**Files:**
- Modify: `src/Hotel/UI/Http/Controller/HotelSerializer.php`

- [ ] **Step 1: Update the serializer**

```php
<?php
// src/Hotel/UI/Http/Controller/HotelSerializer.php
declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller;

use App\Hotel\Domain\Model\Hotel;

final class HotelSerializer
{
    /**
     * @return array{id: string, name: string, streetAddress: string, postalCode: string, city: string, country: string, createdAt: int, starRating: array{stars: int, superior: bool}|null}
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
        ];
    }
}
```

- [ ] **Step 2: Lint and run functional tests**

```bash
make lint && make functional-test
```
Expected: existing hotel tests still pass — `starRating: null` is now returned for unrated hotels.

- [ ] **Step 3: Commit**

```bash
git add src/Hotel/UI/Http/Controller/HotelSerializer.php
git commit -m "feat(hotel): expose starRating in Hotel serializer"
```

---

### Task 6: RegisterHotel — optional Star Rating at registration

**Files:**
- Modify: `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommand.php`
- Modify: `src/Hotel/Application/Service/RegisterHotelCommandFactory.php`
- Modify: `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandler.php`
- Modify: `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelRequest.php`
- Modify: `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelController.php`

- [ ] **Step 1: Update RegisterHotelCommand**

```php
<?php
// src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommand.php
declare(strict_types=1);

namespace App\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class RegisterHotelCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public string $name,
        public Address $address,
        public \DateTimeImmutable $createdAt,
        public ?StarRating $starRating = null,
    ) {
    }
}
```

- [ ] **Step 2: Update RegisterHotelCommandFactory**

```php
<?php
// src/Hotel/Application/Service/RegisterHotelCommandFactory.php
declare(strict_types=1);

namespace App\Hotel\Application\Service;

use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommand;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Port\HotelIdGeneratorInterface;
use App\Hotel\Domain\ValueObject\StarRating;
use Psr\Clock\ClockInterface;

final readonly class RegisterHotelCommandFactory
{
    public function __construct(
        private HotelIdGeneratorInterface $hotelIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(
        ?string $name,
        ?string $streetAddress,
        ?string $postalCode,
        ?string $city,
        ?string $country,
        ?int $stars = null,
        bool $superior = false,
    ): RegisterHotelCommand {
        if (null === $name || null === $streetAddress || null === $postalCode || null === $city || null === $country) {
            throw new \InvalidArgumentException('All hotel fields are required.');
        }

        $starRating = null !== $stars ? new StarRating($stars, $superior) : null;

        return new RegisterHotelCommand(
            $this->hotelIdGenerator->generate(),
            $name,
            new Address($streetAddress, $postalCode, $city, $country),
            $this->clock->now(),
            $starRating,
        );
    }
}
```

- [ ] **Step 3: Update RegisterHotelCommandHandler**

```php
<?php
// src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandler.php
declare(strict_types=1);

namespace App\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Domain\Exception\HotelAlreadyExistsException;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class RegisterHotelCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private HotelRepositoryInterface $hotelRepository,
    ) {
    }

    public function __invoke(RegisterHotelCommand $command): void
    {
        if ($this->hotelRepository->existsByNameAndAddress($command->name, $command->address)) {
            throw new HotelAlreadyExistsException($command->name, $command->address->city);
        }

        $hotel = new Hotel(
            $command->id,
            $command->name,
            $command->address,
            $command->createdAt,
            $command->starRating,
        );

        $this->hotelRepository->add($hotel);
    }
}
```

- [ ] **Step 4: Update RegisterHotelRequest**

```php
<?php
// src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelRequest.php
declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\RegisterHotel;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Expression(
    expression: 'not (this.superior === true and this.stars === null)',
    message: 'stars must be provided when superior is true.',
)]
final readonly class RegisterHotelRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        #[OA\Property(type: 'string', example: 'Hotel Ibis Paris', maxLength: 255, minLength: 2)]
        public ?string $name = null,
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        #[OA\Property(type: 'string', example: '15 rue de Rivoli', maxLength: 255, minLength: 2)]
        public ?string $streetAddress = null,
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 20)]
        #[OA\Property(type: 'string', example: '75001', maxLength: 20, minLength: 1)]
        public ?string $postalCode = null,
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 255)]
        #[OA\Property(type: 'string', example: 'Paris', maxLength: 255, minLength: 1)]
        public ?string $city = null,
        #[Assert\NotBlank]
        #[Assert\Length(exactly: 2)]
        #[Assert\Country]
        #[OA\Property(type: 'string', example: 'FR', maxLength: 2, minLength: 2)]
        public ?string $country = null,
        #[Assert\Range(min: 1, max: 5)]
        #[OA\Property(type: 'integer', example: 4, minimum: 1, maximum: 5, nullable: true)]
        public ?int $stars = null,
        #[OA\Property(type: 'boolean', example: false)]
        public bool $superior = false,
    ) {
    }
}
```

- [ ] **Step 5: Update RegisterHotelController to pass stars/superior to factory**

In `RegisterHotelController::__invoke()`, change the factory call:

```php
$command = $this->commandFactory->create(
    $request->name,
    $request->streetAddress,
    $request->postalCode,
    $request->city,
    $request->country,
    $request->stars,
    $request->superior,
);
```

- [ ] **Step 6: Lint**

```bash
make lint
```

- [ ] **Step 7: Run functional tests**

```bash
make functional-test
```
Expected: existing RegisterHotel tests pass — `stars` and `superior` default to null/false.

- [ ] **Step 8: Run openapi**

```bash
make openapi
```

- [ ] **Step 9: Commit**

```bash
git add src/Hotel/Application/UseCase/RegisterHotel/ src/Hotel/Application/Service/RegisterHotelCommandFactory.php src/Hotel/UI/Http/Controller/RegisterHotel/
git commit -m "feat(hotel): accept optional Star Rating at Hotel Registration"
```

---

### Task 7: HotelNotFoundException + exceptions.yaml

**Files:**
- Create: `src/Hotel/Domain/Exception/HotelNotFoundException.php`
- Modify: `config/services/exceptions.yaml`

- [ ] **Step 1: Create the exception**

```php
<?php
// src/Hotel/Domain/Exception/HotelNotFoundException.php
declare(strict_types=1);

namespace App\Hotel\Domain\Exception;

final class HotelNotFoundException extends \DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Hotel "%s" not found.', $id));
    }
}
```

- [ ] **Step 2: Map the exception to HTTP 404**

In `config/services/exceptions.yaml`, add inside the `$map` arguments block alongside other entries:

```yaml
App\Hotel\Domain\Exception\HotelNotFoundException:
    type: 'https://book.it/problems/hotel-not-found'
    title: 'Hotel Not Found'
    status: 404
```

- [ ] **Step 3: Lint**

```bash
make lint
```

- [ ] **Step 4: Commit**

```bash
git add src/Hotel/Domain/Exception/HotelNotFoundException.php config/services/exceptions.yaml
git commit -m "feat(hotel): add HotelNotFoundException mapped to HTTP 404"
```

---

### Task 8: ClassifyHotel use case

**Files:**
- Create: `src/Hotel/Application/UseCase/ClassifyHotel/ClassifyHotelCommand.php`
- Create: `src/Hotel/Application/UseCase/ClassifyHotel/ClassifyHotelCommandHandler.php`
- Create: `tests/Hotel/Unit/Application/UseCase/ClassifyHotel/ClassifyHotelCommandHandlerTest.php`

- [ ] **Step 1: Write the failing unit tests**

```php
<?php
// tests/Hotel/Unit/Application/UseCase/ClassifyHotel/ClassifyHotelCommandHandlerTest.php
declare(strict_types=1);

namespace App\Tests\Hotel\Unit\Application\UseCase\ClassifyHotel;

use App\Hotel\Application\UseCase\ClassifyHotel\ClassifyHotelCommand;
use App\Hotel\Application\UseCase\ClassifyHotel\ClassifyHotelCommandHandler;
use App\Hotel\Domain\Exception\HotelNotFoundException;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\StarRating;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ClassifyHotelCommandHandlerTest extends TestCase
{
    private HotelRepositoryInterface $repository;
    private ClassifyHotelCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new class implements HotelRepositoryInterface {
            /** @var array<string, Hotel> */
            public array $hotels = [];
            /** @var array<string, Hotel> */
            public array $saved = [];

            public function add(Hotel $hotel): void
            {
                $this->hotels[$hotel->id] = $hotel;
            }

            public function save(Hotel $hotel): void
            {
                $this->saved[$hotel->id] = $hotel;
            }

            public function get(string $id): ?Hotel
            {
                return $this->hotels[$id] ?? null;
            }

            public function existsByNameAndAddress(string $name, Address $address): bool
            {
                return false;
            }

            public function list(int $page, int $limit, ?string $city, ?string $country, ?int $minStars = null): HotelPage
            {
                return new HotelPage([], 0);
            }
        };

        $this->handler = new ClassifyHotelCommandHandler($this->repository);
    }

    private function aHotel(string $id = 'e4e1c9b0-1234-4a2b-9c3f-aabbccddeeff'): Hotel
    {
        return new Hotel(
            $id,
            'Hotel Test',
            new Address('1 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
        );
    }

    public function test_it_sets_a_star_rating(): void
    {
        $hotel = $this->aHotel('hotel-1');
        $this->repository->hotels['hotel-1'] = $hotel;

        ($this->handler)(new ClassifyHotelCommand('hotel-1', 4, false));

        self::assertArrayHasKey('hotel-1', $this->repository->saved);
        $saved = $this->repository->saved['hotel-1'];
        self::assertNotNull($saved->starRating);
        self::assertSame(4, $saved->starRating->stars);
        self::assertFalse($saved->starRating->superior);
    }

    public function test_it_sets_a_superior_star_rating(): void
    {
        $this->repository->hotels['hotel-1'] = $this->aHotel('hotel-1');

        ($this->handler)(new ClassifyHotelCommand('hotel-1', 5, true));

        $saved = $this->repository->saved['hotel-1'];
        self::assertNotNull($saved->starRating);
        self::assertSame(5, $saved->starRating->stars);
        self::assertTrue($saved->starRating->superior);
    }

    public function test_it_removes_a_star_rating_when_stars_is_null(): void
    {
        $hotel = $this->aHotel('hotel-1')->withStarRating(new StarRating(3, false));
        $this->repository->hotels['hotel-1'] = $hotel;

        ($this->handler)(new ClassifyHotelCommand('hotel-1', null, false));

        $saved = $this->repository->saved['hotel-1'];
        self::assertNull($saved->starRating);
    }

    public function test_it_throws_when_hotel_not_found(): void
    {
        $this->expectException(HotelNotFoundException::class);

        ($this->handler)(new ClassifyHotelCommand('unknown-id', 3, false));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
make unit-test
```
Expected: FAIL — `ClassifyHotelCommand` and `ClassifyHotelCommandHandler` not found.

- [ ] **Step 3: Create ClassifyHotelCommand**

```php
<?php
// src/Hotel/Application/UseCase/ClassifyHotel/ClassifyHotelCommand.php
declare(strict_types=1);

namespace App\Hotel\Application\UseCase\ClassifyHotel;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class ClassifyHotelCommand implements SyncCommandInterface
{
    public function __construct(
        public string $hotelId,
        public ?int $stars,
        public bool $superior,
    ) {
    }
}
```

- [ ] **Step 4: Create ClassifyHotelCommandHandler**

```php
<?php
// src/Hotel/Application/UseCase/ClassifyHotel/ClassifyHotelCommandHandler.php
declare(strict_types=1);

namespace App\Hotel\Application\UseCase\ClassifyHotel;

use App\Hotel\Domain\Exception\HotelNotFoundException;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class ClassifyHotelCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private HotelRepositoryInterface $hotelRepository,
    ) {
    }

    public function __invoke(ClassifyHotelCommand $command): void
    {
        $hotel = $this->hotelRepository->get($command->hotelId);

        if (null === $hotel) {
            throw HotelNotFoundException::withId($command->hotelId);
        }

        $starRating = null !== $command->stars
            ? new StarRating($command->stars, $command->superior)
            : null;

        $this->hotelRepository->save($hotel->withStarRating($starRating));
    }
}
```

- [ ] **Step 5: Run unit tests to verify they pass**

```bash
make unit-test
```
Expected: all ClassifyHotelCommandHandlerTest cases pass.

- [ ] **Step 6: Lint**

```bash
make lint
```

- [ ] **Step 7: Commit**

```bash
git add src/Hotel/Application/UseCase/ClassifyHotel/ tests/Hotel/Unit/Application/UseCase/ClassifyHotel/
git commit -m "feat(hotel): add ClassifyHotel use case (Star Rating Classification)"
```

---

### Task 9: ClassifyHotel HTTP endpoint

**Files:**
- Create: `src/Hotel/UI/Http/Controller/ClassifyHotel/ClassifyHotelRequest.php`
- Create: `src/Hotel/UI/Http/Controller/ClassifyHotel/ClassifyHotelController.php`
- Create: `tests/Hotel/Functional/ClassifyHotelTest.php`

- [ ] **Step 1: Write the failing functional test**

```php
<?php
// tests/Hotel/Functional/ClassifyHotelTest.php
declare(strict_types=1);

namespace App\Tests\Hotel\Functional;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class ClassifyHotelTest extends WebTestCase
{
    private function registerHotel(KernelBrowser $client): string
    {
        $client->request('POST', '/api/v1/hotels', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Hotel Test',
            'streetAddress' => '1 rue Test',
            'postalCode' => '75001',
            'city' => 'Paris',
            'country' => 'FR',
        ]));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true);

        return $body['id'];
    }

    public function test_it_sets_a_star_rating_on_a_hotel(): void
    {
        $client = static::createClient();
        $id = $this->registerHotel($client);

        $client->request('PATCH', "/api/v1/hotels/{$id}/star-rating", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => 4,
            'superior' => false,
        ]));

        self::assertResponseStatusCodeSame(204);

        $client->request('GET', "/api/v1/hotels/{$id}");
        /** @var array{starRating: array{stars: int, superior: bool}|null} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(['stars' => 4, 'superior' => false], $body['starRating']);
    }

    public function test_it_sets_a_superior_star_rating(): void
    {
        $client = static::createClient();
        $id = $this->registerHotel($client);

        $client->request('PATCH', "/api/v1/hotels/{$id}/star-rating", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => 5,
            'superior' => true,
        ]));

        self::assertResponseStatusCodeSame(204);

        $client->request('GET', "/api/v1/hotels/{$id}");
        /** @var array{starRating: array{stars: int, superior: bool}|null} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(['stars' => 5, 'superior' => true], $body['starRating']);
    }

    public function test_it_removes_a_star_rating_when_stars_is_null(): void
    {
        $client = static::createClient();
        $id = $this->registerHotel($client);

        $client->request('PATCH', "/api/v1/hotels/{$id}/star-rating", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => 3,
            'superior' => false,
        ]));
        $client->request('PATCH', "/api/v1/hotels/{$id}/star-rating", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => null,
            'superior' => false,
        ]));

        self::assertResponseStatusCodeSame(204);

        $client->request('GET', "/api/v1/hotels/{$id}");
        /** @var array{starRating: null} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertNull($body['starRating']);
    }

    public function test_it_returns_404_for_unknown_hotel(): void
    {
        $client = static::createClient();

        $client->request('PATCH', '/api/v1/hotels/00000000-0000-4000-a000-000000000000/star-rating', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => 3,
            'superior' => false,
        ]));

        self::assertResponseStatusCodeSame(404);
    }

    public function test_it_returns_422_when_superior_true_without_stars(): void
    {
        $client = static::createClient();
        $id = $this->registerHotel($client);

        $client->request('PATCH', "/api/v1/hotels/{$id}/star-rating", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => null,
            'superior' => true,
        ]));

        self::assertResponseStatusCodeSame(422);
    }

    public function test_it_returns_422_when_stars_out_of_range(): void
    {
        $client = static::createClient();
        $id = $this->registerHotel($client);

        $client->request('PATCH', "/api/v1/hotels/{$id}/star-rating", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'stars' => 6,
            'superior' => false,
        ]));

        self::assertResponseStatusCodeSame(422);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
make functional-test
```
Expected: FAIL — route `hotel_classify` not found (404 on all requests).

- [ ] **Step 3: Create ClassifyHotelRequest**

```php
<?php
// src/Hotel/UI/Http/Controller/ClassifyHotel/ClassifyHotelRequest.php
declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\ClassifyHotel;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Expression(
    expression: 'not (this.superior === true and this.stars === null)',
    message: 'stars must be provided when superior is true.',
)]
final readonly class ClassifyHotelRequest
{
    public function __construct(
        #[Assert\Range(min: 1, max: 5)]
        #[OA\Property(type: 'integer', example: 4, minimum: 1, maximum: 5, nullable: true)]
        public ?int $stars = null,
        #[OA\Property(type: 'boolean', example: false)]
        public bool $superior = false,
    ) {
    }
}
```

- [ ] **Step 4: Create ClassifyHotelController**

```php
<?php
// src/Hotel/UI/Http/Controller/ClassifyHotel/ClassifyHotelController.php
declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\ClassifyHotel;

use App\Hotel\Application\UseCase\ClassifyHotel\ClassifyHotelCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class ClassifyHotelController
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    #[Route(
        path: '/hotels/{id}/star-rating',
        name: 'hotel_classify',
        requirements: ['id' => Requirement::UUID_V4],
        methods: ['PATCH'],
    )]
    #[OA\Patch(
        path: '/hotels/{id}/star-rating',
        summary: 'Set or update the Star Rating of a Hotel',
        tags: ['Hotels'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: ClassifyHotelRequest::class),
    )]
    #[OA\Response(response: 204, description: 'Star Rating updated')]
    #[OA\Response(response: 404, description: 'Hotel not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail')))]
    #[OA\Response(response: 422, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail')))]
    public function __invoke(
        string $id,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ClassifyHotelRequest $request,
    ): Response {
        $this->commandBus->execute(new ClassifyHotelCommand(
            hotelId: $id,
            stars: $request->stars,
            superior: $request->superior,
        ));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 5: Run functional tests**

```bash
make functional-test
```
Expected: all ClassifyHotelTest cases pass.

- [ ] **Step 6: Lint and update OpenAPI spec**

```bash
make lint && make openapi
```

- [ ] **Step 7: Commit**

```bash
git add src/Hotel/UI/Http/Controller/ClassifyHotel/ tests/Hotel/Functional/ClassifyHotelTest.php
git commit -m "feat(hotel): add PATCH /hotels/{id}/star-rating endpoint"
```

---

### Task 10: ListHotels — minStars filter

**Files:**
- Modify: `src/Hotel/Application/UseCase/ListHotels/ListHotelsQuery.php`
- Modify: `src/Hotel/Application/UseCase/ListHotels/ListHotelsQueryHandler.php`
- Modify: `src/Hotel/UI/Http/Controller/ListHotels/ListHotelsRequest.php`
- Modify: `src/Hotel/UI/Http/Controller/ListHotels/ListHotelsController.php`

- [ ] **Step 1: Update ListHotelsQuery**

```php
<?php
// src/Hotel/Application/UseCase/ListHotels/ListHotelsQuery.php
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
        public ?int $minStars = null,
    ) {
    }
}
```

- [ ] **Step 2: Update ListHotelsQueryHandler**

```php
<?php
// src/Hotel/Application/UseCase/ListHotels/ListHotelsQueryHandler.php
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
        );
    }
}
```

- [ ] **Step 3: Update ListHotelsRequest**

```php
<?php
// src/Hotel/UI/Http/Controller/ListHotels/ListHotelsRequest.php
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
    ) {
    }
}
```

- [ ] **Step 4: Update ListHotelsController to pass minStars**

Change the `ask()` call in `ListHotelsController::__invoke()`:

```php
$hotelPage = $this->queryBus->ask(new ListHotelsQuery(
    $request->page,
    $request->limit,
    $request->city,
    $request->country,
    $request->minStars,
));
```

Also update the `#[OA\Get]` response schema to include `starRating` on each item:

```php
new OA\Property(
    property: 'starRating',
    properties: [
        new OA\Property(property: 'stars', type: 'integer', minimum: 1, maximum: 5),
        new OA\Property(property: 'superior', type: 'boolean'),
    ],
    type: 'object',
    nullable: true,
),
```

- [ ] **Step 5: Verify HotelCatalogueSerializer includes starRating**

Open `src/Hotel/UI/Http/Controller/ListHotels/HotelCatalogueSerializer.php` and check whether it delegates to `HotelSerializer`. If it does, `starRating` is already included. If it maps fields manually, add `starRating` using the same pattern as `HotelSerializer`.

- [ ] **Step 6: Run all tests**

```bash
make test
```
Expected: all unit and functional tests pass.

- [ ] **Step 7: Lint and update OpenAPI spec**

```bash
make lint && make openapi
```

- [ ] **Step 8: Commit**

```bash
git add src/Hotel/Application/UseCase/ListHotels/ src/Hotel/UI/Http/Controller/ListHotels/
git commit -m "feat(hotel): add minStars filter to Hotel Catalogue"
```

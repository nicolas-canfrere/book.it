# Room Type Amenity Declaration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `PATCH /room-types/{id}/amenities` to declare or replace the full Room Amenity list on a Room Type.

**Architecture:** Mirror of the existing `DeclareHotelAmenities` feature — new `RoomAmenity` enum, `withAmenities()` on `RoomType`, dedicated `DeclareRoomTypeAmenities` use case, and a `save()` method on `RoomTypeRepositoryInterface` that the Doctrine repo implements as a targeted UPDATE. Amenities are stored as `text[]` in PostgreSQL (same as `hotel.hotel`).

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL (raw, no ORM), PostgreSQL `text[]`, Symfony Validator, NelmioApiDocBundle.

---

## File Map

| Action | Path | Purpose |
|--------|------|---------|
| Create | `src/Room/Domain/ValueObject/RoomAmenity.php` | 27-case backed enum + `values()` helper |
| Modify | `src/Room/Domain/Model/RoomType.php` | Add `amenities` field + `withAmenities()` |
| Modify | `src/Room/Domain/Port/RoomTypeRepositoryInterface.php` | Add `save(RoomType): void` |
| Modify | `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomTypeRepository.php` | Implement `save()` |
| Create | `src/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommand.php` | Command DTO |
| Create | `src/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommandHandler.php` | Handler |
| Create | `migrations/VersionYYYYMMDDHHIISS.php` | Add `amenities text[]` to `room.room_type` |
| Modify | `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeRepository.php` | Implement `save()`, update `add()`, `update()`, `get()`, `list()`, `hydrate()` |
| Create | `src/Room/UI/Http/Controller/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesRequest.php` | Request DTO with validation |
| Create | `src/Room/UI/Http/Controller/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesController.php` | PATCH controller |
| Create | `tests/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommandHandlerTest.php` | Unit tests |
| Create | `tests/Room/UI/Http/Controller/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesControllerTest.php` | Functional tests |

---

### Task 1: Create feature branch

**Files:** none

- [ ] **Step 1: Create and checkout branch**

```bash
git checkout -b feat/room-type-amenity-declaration
```

Expected: `Switched to a new branch 'feat/room-type-amenity-declaration'`

---

### Task 2: RoomAmenity enum

**Files:**
- Create: `src/Room/Domain/ValueObject/RoomAmenity.php`

- [ ] **Step 1: Create the enum**

```php
<?php

declare(strict_types=1);

namespace App\Room\Domain\ValueObject;

enum RoomAmenity: string
{
    // Connectivity
    case Wifi = 'wifi';
    case Ethernet = 'ethernet';
    case Tv = 'tv';
    case Telephone = 'telephone';

    // Climate
    case AirConditioning = 'air_conditioning';
    case Heating = 'heating';
    case CeilingFan = 'ceiling_fan';
    case Fireplace = 'fireplace';

    // Bathroom
    case Bathtub = 'bathtub';
    case Shower = 'shower';
    case Jacuzzi = 'jacuzzi';
    case Hairdryer = 'hairdryer';
    case Bidet = 'bidet';

    // Kitchen
    case Minibar = 'minibar';
    case Kettle = 'kettle';
    case CoffeeMachine = 'coffee_machine';
    case Microwave = 'microwave';
    case Kitchenette = 'kitchenette';
    case Refrigerator = 'refrigerator';

    // Workspace
    case Desk = 'desk';
    case Safe = 'safe';

    // Bedding & storage
    case BlackoutCurtains = 'blackout_curtains';
    case Wardrobe = 'wardrobe';

    // Misc
    case Balcony = 'balcony';
    case Terrace = 'terrace';
    case Iron = 'iron';
    case WashingMachine = 'washing_machine';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Room/Domain/ValueObject/RoomAmenity.php
git commit -m "feat(room): add RoomAmenity enum"
```

---

### Task 3: Extend RoomType model with amenities

**Files:**
- Modify: `src/Room/Domain/Model/RoomType.php`

- [ ] **Step 1: Replace the file content**

```php
<?php

declare(strict_types=1);

namespace App\Room\Domain\Model;

use App\Room\Domain\ValueObject\BedComposition;
use App\Room\Domain\ValueObject\RoomAmenity;

final readonly class RoomType
{
    /**
     * @param array<RoomAmenity> $amenities
     */
    public function __construct(
        public string $id,
        public string $hotelId,
        public string $name,
        public int $livingSpaceCount,
        public ?int $surfaceM2,
        public int $guestCapacity,
        public bool $isAccessible,
        public BedComposition $bedComposition,
        public \DateTimeImmutable $createdAt,
        public array $amenities = [],
    ) {
    }

    /**
     * @param array<RoomAmenity> $amenities
     */
    public function withAmenities(array $amenities): self
    {
        return new self(
            id: $this->id,
            hotelId: $this->hotelId,
            name: $this->name,
            livingSpaceCount: $this->livingSpaceCount,
            surfaceM2: $this->surfaceM2,
            guestCapacity: $this->guestCapacity,
            isAccessible: $this->isAccessible,
            bedComposition: $this->bedComposition,
            createdAt: $this->createdAt,
            amenities: $amenities,
        );
    }
}
```

- [ ] **Step 2: Run lint to confirm no issues**

```bash
make lint
```

Expected: no errors (existing code passes unchanged since `amenities` has a default value).

- [ ] **Step 3: Commit**

```bash
git add src/Room/Domain/Model/RoomType.php
git commit -m "feat(room): add amenities field and withAmenities() to RoomType"
```

---

### Task 4: Add save() to interface and InMemoryRoomTypeRepository

**Files:**
- Modify: `src/Room/Domain/Port/RoomTypeRepositoryInterface.php`
- Modify: `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomTypeRepository.php`

- [ ] **Step 1: Add save() to the interface**

In `src/Room/Domain/Port/RoomTypeRepositoryInterface.php`, add after `update()`:

```php
public function save(RoomType $roomType): void;
```

Full file after change:

```php
<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Model\RoomTypePage;

interface RoomTypeRepositoryInterface
{
    public function add(RoomType $roomType): void;

    public function get(string $id): ?RoomType;

    public function existsByHotelIdAndName(string $hotelId, string $name): bool;

    public function update(RoomType $roomType): void;

    public function save(RoomType $roomType): void;

    public function delete(string $id): void;

    public function list(string $hotelId, int $page, int $limit): RoomTypePage;
}
```

- [ ] **Step 2: Implement save() in InMemoryRoomTypeRepository**

In `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomTypeRepository.php`, add after `update()`:

```php
public function save(RoomType $roomType): void
{
    $this->roomTypes[$roomType->id] = $roomType;
}
```

- [ ] **Step 3: Run unit tests to confirm all pass**

```bash
make unit-test
```

Expected: all green (existing tests unchanged; `InMemoryRoomTypeRepository` now satisfies the interface).

- [ ] **Step 4: Commit**

```bash
git add src/Room/Domain/Port/RoomTypeRepositoryInterface.php \
        tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomTypeRepository.php
git commit -m "feat(room): add save() to RoomTypeRepositoryInterface"
```

---

### Task 5: DeclareRoomTypeAmenities use case (TDD)

**Files:**
- Create: `src/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommand.php`
- Create: `src/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommandHandler.php`
- Create: `tests/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommandHandlerTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommandHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\DeclareRoomTypeAmenities;

use App\Room\Application\UseCase\DeclareRoomTypeAmenities\DeclareRoomTypeAmenitiesCommand;
use App\Room\Application\UseCase\DeclareRoomTypeAmenities\DeclareRoomTypeAmenitiesCommandHandler;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommandHandler;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Room\Domain\ValueObject\RoomAmenity;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeclareRoomTypeAmenitiesCommandHandlerTest extends TestCase
{
    private const string HOTEL_ID = '550e8400-e29b-41d4-a716-446655440000';
    private const string ROOM_TYPE_ID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    private InMemoryRoomTypeRepository $repository;
    private DeclareRoomTypeAmenitiesCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRoomTypeRepository();
        $this->handler = new DeclareRoomTypeAmenitiesCommandHandler($this->repository);

        $registerHandler = new RegisterRoomTypeCommandHandler($this->repository, new FakeHotelExistenceChecker());
        ($registerHandler)(new RegisterRoomTypeCommand(
            id: self::ROOM_TYPE_ID,
            hotelId: self::HOTEL_ID,
            name: 'Standard',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 2,
            isAccessible: false,
            bedEntries: [['type' => 'double', 'count' => 1]],
            createdAt: new \DateTimeImmutable('2024-01-01'),
        ));
    }

    #[Test]
    public function itDeclaresSetsAmenities(): void
    {
        ($this->handler)(new DeclareRoomTypeAmenitiesCommand(
            roomTypeId: self::ROOM_TYPE_ID,
            amenities: ['wifi', 'tv', 'minibar'],
        ));

        $updated = $this->repository->get(self::ROOM_TYPE_ID);
        self::assertNotNull($updated);
        self::assertCount(3, $updated->amenities);
        self::assertSame(RoomAmenity::Wifi, $updated->amenities[0]);
        self::assertSame(RoomAmenity::Tv, $updated->amenities[1]);
        self::assertSame(RoomAmenity::Minibar, $updated->amenities[2]);
    }

    #[Test]
    public function itDeclaresEmptyList(): void
    {
        ($this->handler)(new DeclareRoomTypeAmenitiesCommand(
            roomTypeId: self::ROOM_TYPE_ID,
            amenities: [],
        ));

        $updated = $this->repository->get(self::ROOM_TYPE_ID);
        self::assertNotNull($updated);
        self::assertSame([], $updated->amenities);
    }

    #[Test]
    public function itThrowsWhenRoomTypeNotFound(): void
    {
        $this->expectException(RoomTypeNotFoundException::class);

        ($this->handler)(new DeclareRoomTypeAmenitiesCommand(
            roomTypeId: '00000000-0000-4000-8000-000000000000',
            amenities: ['wifi'],
        ));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
make unit-test
```

Expected: FAIL — `DeclareRoomTypeAmenitiesCommand` and `DeclareRoomTypeAmenitiesCommandHandler` not found.

- [ ] **Step 3: Create the Command**

Create `src/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\DeclareRoomTypeAmenities;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class DeclareRoomTypeAmenitiesCommand implements SyncCommandInterface
{
    /**
     * @param string[] $amenities
     */
    public function __construct(
        public string $roomTypeId,
        public array $amenities,
    ) {
    }
}
```

- [ ] **Step 4: Create the Handler**

Create `src/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\DeclareRoomTypeAmenities;

use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\RoomAmenity;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class DeclareRoomTypeAmenitiesCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomTypeRepositoryInterface $roomTypeRepository,
    ) {
    }

    public function __invoke(DeclareRoomTypeAmenitiesCommand $command): void
    {
        $roomType = $this->roomTypeRepository->get($command->roomTypeId);

        if (null === $roomType) {
            throw new RoomTypeNotFoundException($command->roomTypeId);
        }

        $amenities = array_map(RoomAmenity::from(...), $command->amenities);

        $this->roomTypeRepository->save($roomType->withAmenities($amenities));
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
make unit-test
```

Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add src/Room/Application/UseCase/DeclareRoomTypeAmenities/ \
        tests/Room/Application/UseCase/DeclareRoomTypeAmenities/
git commit -m "feat(room): add DeclareRoomTypeAmenities use case"
```

---

### Task 6: Migration and Doctrine repository update

**Files:**
- Create: `migrations/VersionYYYYMMDDHHIISS.php` (generated)
- Modify: `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeRepository.php`

- [ ] **Step 1: Generate a blank migration**

```bash
make generate-migration
```

Expected: a new file `migrations/VersionYYYYMMDDHHIISS.php` created (the exact name will contain the current timestamp).

- [ ] **Step 2: Fill in the migration SQL**

Open the newly generated file and replace its `up()` and `down()` bodies, and set a description:

```php
public function getDescription(): string
{
    return 'Add amenities column to room.room_type table';
}

public function up(Schema $schema): void
{
    $this->addSql("ALTER TABLE room.room_type ADD COLUMN amenities text[] NOT NULL DEFAULT '{}'");
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE room.room_type DROP COLUMN amenities');
}
```

- [ ] **Step 3: Run the migration**

```bash
make migrate
```

Expected: migration runs without error.

- [ ] **Step 4: Update RoomTypeRepository**

Replace `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeRepository.php` with:

```php
<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Model\RoomTypePage;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\BedComposition;
use App\Room\Domain\ValueObject\RoomAmenity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

final readonly class RoomTypeRepository implements RoomTypeRepositoryInterface
{
    public function __construct(private Connection $roomConnection)
    {
    }

    public function add(RoomType $roomType): void
    {
        $this->roomConnection->insert('room_type', [
            'id' => $roomType->id,
            'hotel_id' => $roomType->hotelId,
            'name' => $roomType->name,
            'living_space_count' => $roomType->livingSpaceCount,
            'surface_m2' => $roomType->surfaceM2,
            'guest_capacity' => $roomType->guestCapacity,
            'is_accessible' => $roomType->isAccessible,
            'bed_composition' => json_encode($roomType->bedComposition->toArray(), \JSON_THROW_ON_ERROR),
            'amenities' => $this->serializeAmenities($roomType->amenities),
            'created_at' => $roomType->createdAt->format('Y-m-d H:i:s'),
        ], [
            'is_accessible' => Types::BOOLEAN,
        ]);
    }

    public function get(string $id): ?RoomType
    {
        /** @var array{id: string, hotel_id: string, name: string, living_space_count: int|string, surface_m2: int|string|null, guest_capacity: int|string, is_accessible: string|bool, bed_composition: string, amenities: string, created_at: string}|false $row */
        $row = $this->roomConnection->fetchAssociative(
            'SELECT id, hotel_id, name, living_space_count, surface_m2, guest_capacity, is_accessible, bed_composition, amenities, created_at FROM room_type WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function existsByHotelIdAndName(string $hotelId, string $name): bool
    {
        $count = $this->roomConnection->fetchOne(
            'SELECT COUNT(*) FROM room_type WHERE hotel_id = :hotelId AND name = :name',
            ['hotelId' => $hotelId, 'name' => $name],
        );

        return $count > 0;
    }

    public function update(RoomType $roomType): void
    {
        $this->roomConnection->update('room_type', [
            'name' => $roomType->name,
            'living_space_count' => $roomType->livingSpaceCount,
            'surface_m2' => $roomType->surfaceM2,
            'guest_capacity' => $roomType->guestCapacity,
            'is_accessible' => $roomType->isAccessible,
            'bed_composition' => json_encode($roomType->bedComposition->toArray(), \JSON_THROW_ON_ERROR),
            'amenities' => $this->serializeAmenities($roomType->amenities),
        ], ['id' => $roomType->id], [
            'is_accessible' => Types::BOOLEAN,
        ]);
    }

    public function save(RoomType $roomType): void
    {
        $this->roomConnection->update('room_type', [
            'amenities' => $this->serializeAmenities($roomType->amenities),
        ], ['id' => $roomType->id]);
    }

    public function delete(string $id): void
    {
        $this->roomConnection->delete('room_type', ['id' => $id]);
    }

    public function list(string $hotelId, int $page, int $limit): RoomTypePage
    {
        /** @var int|string $count */
        $count = $this->roomConnection->fetchOne(
            'SELECT COUNT(*) FROM room_type WHERE hotel_id = :hotelId',
            ['hotelId' => $hotelId],
        );
        $total = (int) $count;

        /** @var list<array{id: string, hotel_id: string, name: string, living_space_count: int|string, surface_m2: int|string|null, guest_capacity: int|string, is_accessible: string|bool, bed_composition: string, amenities: string, created_at: string}> $rows */
        $rows = $this->roomConnection->fetchAllAssociative(
            'SELECT id, hotel_id, name, living_space_count, surface_m2, guest_capacity, is_accessible, bed_composition, amenities, created_at FROM room_type WHERE hotel_id = :hotelId ORDER BY name ASC LIMIT :limit OFFSET :offset',
            ['hotelId' => $hotelId, 'limit' => $limit, 'offset' => ($page - 1) * $limit],
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

    /** @param array<RoomAmenity> $amenities */
    private function serializeAmenities(array $amenities): string
    {
        if ([] === $amenities) {
            return '{}';
        }

        return '{' . implode(',', array_map(static fn(RoomAmenity $a) => $a->value, $amenities)) . '}';
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

- [ ] **Step 5: Run full test suite**

```bash
make test
```

Expected: all green (functional tests pass against the migrated schema).

- [ ] **Step 6: Run lint**

```bash
make lint
```

Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add migrations/ src/Room/Infrastructure/Persistence/Doctrine/RoomTypeRepository.php
git commit -m "feat(room): add amenities column to room_type + update repository"
```

---

### Task 7: Controller (TDD)

**Files:**
- Create: `src/Room/UI/Http/Controller/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesRequest.php`
- Create: `src/Room/UI/Http/Controller/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesController.php`
- Create: `tests/Room/UI/Http/Controller/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesControllerTest.php`

- [ ] **Step 1: Write the failing functional test**

Create `tests/Room/UI/Http/Controller/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\DeclareRoomTypeAmenities;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class DeclareRoomTypeAmenitiesControllerTest extends WebTestCase
{
    private const array HOTEL_PAYLOAD = [
        'name' => 'Hotel Test',
        'streetAddress' => '1 rue de la Paix',
        'postalCode' => '75001',
        'city' => 'Paris',
        'country' => 'FR',
    ];
    private const array ROOM_TYPE_PAYLOAD = [
        'name' => 'Standard',
        'livingSpaceCount' => 1,
        'guestCapacity' => 2,
        'isAccessible' => false,
        'bedComposition' => [['type' => 'double', 'count' => 1]],
    ];

    #[Test]
    public function itReturns204WithValidAmenities(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            'PATCH',
            "/api/v1/room-types/{$roomTypeId}/amenities",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['wifi', 'tv', 'minibar']], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns204WithEmptyList(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            'PATCH',
            "/api/v1/room-types/{$roomTypeId}/amenities",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => []], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns404ForUnknownRoomType(): void
    {
        $client = static::createClient();

        $client->request(
            'PATCH',
            '/api/v1/room-types/00000000-0000-4000-8000-000000000000/amenities',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['wifi']], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422ForUnknownAmenityValue(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            'PATCH',
            "/api/v1/room-types/{$roomTypeId}/amenities",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['not_a_real_amenity']], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422ForDuplicateAmenityValue(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            'PATCH',
            "/api/v1/room-types/{$roomTypeId}/amenities",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['amenities' => ['wifi', 'wifi']], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request(
            'POST',
            '/api/v1/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    private function registerRoomTypeAndGetId(KernelBrowser $client, string $hotelId): string
    {
        $client->request(
            'POST',
            "/api/v1/hotels/{$hotelId}/room-types",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::ROOM_TYPE_PAYLOAD, \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
make functional-test
```

Expected: FAIL — route `/api/v1/room-types/{id}/amenities` not found (404).

- [ ] **Step 3: Create the Request DTO**

Create `src/Room/UI/Http/Controller/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\DeclareRoomTypeAmenities;

use App\Room\Domain\ValueObject\RoomAmenity;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class DeclareRoomTypeAmenitiesRequest
{
    /**
     * @param string[] $amenities
     */
    public function __construct(
        #[Assert\All(constraints: [new Assert\Choice(callback: [RoomAmenity::class, 'values'])])]
        #[Assert\Unique]
        #[OA\Property(
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['wifi', 'tv', 'minibar'],
        )]
        public array $amenities = [],
    ) {
    }
}
```

- [ ] **Step 4: Create the Controller**

Create `src/Room/UI/Http/Controller/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\DeclareRoomTypeAmenities;

use App\Room\Application\UseCase\DeclareRoomTypeAmenities\DeclareRoomTypeAmenitiesCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class DeclareRoomTypeAmenitiesController
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    #[Route(
        path: '/room-types/{id}/amenities',
        name: 'room_type_declare_amenities',
        requirements: ['id' => Requirement::UUID_V4],
        methods: ['PATCH'],
    )]
    #[OA\Patch(
        summary: 'Declare or replace the Room Type Amenity list',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: DeclareRoomTypeAmenitiesRequest::class)),
        ),
        tags: ['Room Types'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Amenities declared'),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Room type not found',
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
        DeclareRoomTypeAmenitiesRequest $request,
    ): Response {
        $this->commandBus->execute(new DeclareRoomTypeAmenitiesCommand(
            roomTypeId: $id,
            amenities: $request->amenities,
        ));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 5: Run functional tests to verify they pass**

```bash
make functional-test
```

Expected: all green.

- [ ] **Step 6: Run full lint**

```bash
make lint
```

Expected: no errors.

- [ ] **Step 7: Regenerate OpenAPI spec**

```bash
make openapi
```

Expected: `openapi.yaml` updated with the new `PATCH /room-types/{id}/amenities` endpoint.

- [ ] **Step 8: Commit**

```bash
git add src/Room/UI/Http/Controller/DeclareRoomTypeAmenities/ \
        tests/Room/UI/Http/Controller/DeclareRoomTypeAmenities/ \
        openapi.yaml
git commit -m "feat(room): add DeclareRoomTypeAmenities controller and functional tests"
```

---

### Task 8: Open Pull Request

- [ ] **Step 1: Push branch**

```bash
git push -u origin feat/room-type-amenity-declaration
```

- [ ] **Step 2: Open PR**

```bash
gh pr create \
  --title "feat(room): Room Type Amenity Declaration" \
  --body "Adds PATCH /room-types/{id}/amenities to declare or replace the full Room Amenity list on a Room Type.

## Changes
- New \`RoomAmenity\` enum (27 cases)
- \`RoomType\` model: \`amenities\` field + \`withAmenities()\`
- \`DeclareRoomTypeAmenities\` use case
- Migration: \`amenities text[]\` on \`room.room_type\`
- \`RoomTypeRepository\`: \`save()\`, updated \`add/update/get/list/hydrate\`
- Controller + request DTO
- Unit + functional tests
- OpenAPI spec regenerated

Closes: n/a — See design spec \`docs/superpowers/specs/2026-05-31-room-type-amenity-declaration-design.md\`"
```

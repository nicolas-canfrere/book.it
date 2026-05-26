# Room Types — Plan A: New Room Type Entities

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce the full Room Type catalog (domain, application, infrastructure, HTTP) as a standalone feature. Does not yet bind Room to RoomType — that is Plan B.

**Architecture:** New entities live entirely within the existing `Room` bounded context. Room Type is a per-hotel catalog entry described by name, physical attributes, and a bed composition. All layers follow existing Room context patterns (DBAL, sync command/query buses, RFC 7807 errors).

**Tech Stack:** PHP 8.4, Symfony 8.0, PostgreSQL 16 (DBAL), Doctrine Migrations, PHPUnit

---

## File Map

**Create:**
- `src/Room/Domain/ValueObject/BedType.php`
- `src/Room/Domain/ValueObject/BedEntry.php`
- `src/Room/Domain/ValueObject/BedComposition.php`
- `src/Room/Domain/Model/RoomType.php`
- `src/Room/Domain/Model/RoomTypePage.php`
- `src/Room/Domain/Exception/RoomTypeAlreadyExistsException.php`
- `src/Room/Domain/Exception/RoomTypeNotFoundException.php`
- `src/Room/Domain/Exception/RoomTypeHasRoomsException.php`
- `src/Room/Domain/Port/RoomTypeRepositoryInterface.php`
- `src/Room/Domain/Port/RoomTypeIdGeneratorInterface.php`
- `src/Room/Domain/Port/RoomTypeHasRoomsInterface.php`
- `src/Room/Application/UseCase/RegisterRoomType/RegisterRoomTypeCommand.php`
- `src/Room/Application/UseCase/RegisterRoomType/RegisterRoomTypeCommandHandler.php`
- `src/Room/Application/Service/RegisterRoomTypeCommandFactory.php`
- `src/Room/Application/UseCase/GetRoomType/GetRoomTypeQuery.php`
- `src/Room/Application/UseCase/GetRoomType/GetRoomTypeQueryHandler.php`
- `src/Room/Application/UseCase/ListRoomTypes/ListRoomTypesQuery.php`
- `src/Room/Application/UseCase/ListRoomTypes/ListRoomTypesQueryHandler.php`
- `src/Room/Application/UseCase/UpdateRoomType/UpdateRoomTypeCommand.php`
- `src/Room/Application/UseCase/UpdateRoomType/UpdateRoomTypeCommandHandler.php`
- `src/Room/Application/UseCase/DeleteRoomType/DeleteRoomTypeCommand.php`
- `src/Room/Application/UseCase/DeleteRoomType/DeleteRoomTypeCommandHandler.php`
- `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeRepository.php`
- `src/Room/Infrastructure/Service/RoomTypeIdGenerator.php`
- `src/Room/UI/Http/Controller/RoomTypeSerializer.php`
- `src/Room/UI/Http/Controller/RegisterRoomType/RegisterRoomTypeController.php`
- `src/Room/UI/Http/Controller/RegisterRoomType/RegisterRoomTypeRequest.php`
- `src/Room/UI/Http/Controller/GetRoomType/GetRoomTypeController.php`
- `src/Room/UI/Http/Controller/ListRoomTypes/ListRoomTypesController.php`
- `src/Room/UI/Http/Controller/ListRoomTypes/ListRoomTypesRequest.php`
- `src/Room/UI/Http/Controller/ListRoomTypes/RoomTypeCatalogueSerializer.php`
- `src/Room/UI/Http/Controller/UpdateRoomType/UpdateRoomTypeController.php`
- `src/Room/UI/Http/Controller/UpdateRoomType/UpdateRoomTypeRequest.php`
- `src/Room/UI/Http/Controller/DeleteRoomType/DeleteRoomTypeController.php`
- `tests/Room/Domain/ValueObject/BedCompositionTest.php`
- `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomTypeRepository.php`
- `tests/Room/Infrastructure/FakeRoomTypeHasRooms.php`
- `tests/Room/Application/UseCase/RegisterRoomType/RegisterRoomTypeCommandHandlerTest.php`
- `tests/Room/Application/UseCase/GetRoomType/GetRoomTypeQueryHandlerTest.php`
- `tests/Room/Application/UseCase/ListRoomTypes/ListRoomTypesQueryHandlerTest.php`
- `tests/Room/Application/UseCase/UpdateRoomType/UpdateRoomTypeCommandHandlerTest.php`
- `tests/Room/Application/UseCase/DeleteRoomType/DeleteRoomTypeCommandHandlerTest.php`
- `tests/Room/UI/Http/Controller/RegisterRoomType/RegisterRoomTypeControllerTest.php`
- `tests/Room/UI/Http/Controller/GetRoomType/GetRoomTypeControllerTest.php`
- `tests/Room/UI/Http/Controller/ListRoomTypes/ListRoomTypesControllerTest.php`
- `tests/Room/UI/Http/Controller/UpdateRoomType/UpdateRoomTypeControllerTest.php`
- `tests/Room/UI/Http/Controller/DeleteRoomType/DeleteRoomTypeControllerTest.php`
- `migrations/VersionYYYYMMDDHHmmss.php` (room_type table — generate with `make generate-migration` after Task 8)

**Modify:**
- `config/services/room.yaml`
- `config/services/exceptions.yaml`

---

### Task 1: BedType enum + BedEntry + BedComposition value objects

**Files:**
- Create: `src/Room/Domain/ValueObject/BedType.php`
- Create: `src/Room/Domain/ValueObject/BedEntry.php`
- Create: `src/Room/Domain/ValueObject/BedComposition.php`
- Test: `tests/Room/Domain/ValueObject/BedCompositionTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Room/Domain/ValueObject/BedCompositionTest.php
declare(strict_types=1);
namespace App\Tests\Room\Domain\ValueObject;

use App\Room\Domain\ValueObject\BedComposition;
use App\Room\Domain\ValueObject\BedEntry;
use App\Room\Domain\ValueObject\BedType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BedCompositionTest extends TestCase
{
    #[Test]
    public function itRejectsEmptyList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BedComposition([]);
    }

    #[Test]
    public function itRejectsBedCountBelowOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BedEntry(BedType::King, 0);
    }

    #[Test]
    public function itRejectsBedCountAboveTen(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BedEntry(BedType::King, 11);
    }

    #[Test]
    public function itSerializesToArray(): void
    {
        $composition = new BedComposition([
            new BedEntry(BedType::King, 1),
            new BedEntry(BedType::SofaBed, 1),
        ]);

        self::assertSame(
            [['type' => 'king', 'count' => 1], ['type' => 'sofa_bed', 'count' => 1]],
            $composition->toArray(),
        );
    }

    #[Test]
    public function itDeserializesFromArray(): void
    {
        $data = [['type' => 'queen', 'count' => 2]];
        $composition = BedComposition::fromArray($data);

        self::assertCount(1, $composition->entries);
        self::assertSame(BedType::Queen, $composition->entries[0]->type);
        self::assertSame(2, $composition->entries[0]->count);
    }

    #[Test]
    public function itRoundTrips(): void
    {
        $original = new BedComposition([new BedEntry(BedType::Single, 2)]);
        $roundTripped = BedComposition::fromArray($original->toArray());

        self::assertSame($original->toArray(), $roundTripped->toArray());
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
make unit-tests
```
Expected: class not found errors.

- [ ] **Step 3: Create BedType enum**

```php
<?php
// src/Room/Domain/ValueObject/BedType.php
declare(strict_types=1);
namespace App\Room\Domain\ValueObject;

enum BedType: string
{
    case Single = 'single';
    case Double = 'double';
    case Queen = 'queen';
    case King = 'king';
    case Bunk = 'bunk';
    case SofaBed = 'sofa_bed';
    case BabyCot = 'baby_cot';
}
```

- [ ] **Step 4: Create BedEntry value object**

```php
<?php
// src/Room/Domain/ValueObject/BedEntry.php
declare(strict_types=1);
namespace App\Room\Domain\ValueObject;

final readonly class BedEntry
{
    public function __construct(
        public BedType $type,
        public int $count,
    ) {
        if ($count < 1 || $count > 10) {
            throw new \InvalidArgumentException(sprintf('Bed count must be between 1 and 10, got %d.', $count));
        }
    }
}
```

- [ ] **Step 5: Create BedComposition value object**

```php
<?php
// src/Room/Domain/ValueObject/BedComposition.php
declare(strict_types=1);
namespace App\Room\Domain\ValueObject;

final readonly class BedComposition
{
    /** @param list<BedEntry> $entries */
    public function __construct(
        public array $entries,
    ) {
        if ([] === $entries) {
            throw new \InvalidArgumentException('Bed composition must contain at least one entry.');
        }
    }

    /** @return list<array{type: string, count: int}> */
    public function toArray(): array
    {
        return array_map(
            fn(BedEntry $e) => ['type' => $e->type->value, 'count' => $e->count],
            $this->entries,
        );
    }

    /** @param list<array{type: string, count: int}> $data */
    public static function fromArray(array $data): self
    {
        return new self(array_map(
            fn(array $entry) => new BedEntry(BedType::from($entry['type']), $entry['count']),
            array_values($data),
        ));
    }
}
```

- [ ] **Step 6: Run tests to confirm they pass**

```bash
make unit-tests
```
Expected: all BedCompositionTest tests PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Room/Domain/ValueObject/BedType.php \
        src/Room/Domain/ValueObject/BedEntry.php \
        src/Room/Domain/ValueObject/BedComposition.php \
        tests/Room/Domain/ValueObject/BedCompositionTest.php
git commit -m "feat(room): add BedType enum, BedEntry, and BedComposition value objects"
```

---

### Task 2: RoomType model, RoomTypePage, exceptions, and ports

**Files:**
- Create: `src/Room/Domain/Model/RoomType.php`
- Create: `src/Room/Domain/Model/RoomTypePage.php`
- Create: `src/Room/Domain/Exception/RoomTypeAlreadyExistsException.php`
- Create: `src/Room/Domain/Exception/RoomTypeNotFoundException.php`
- Create: `src/Room/Domain/Exception/RoomTypeHasRoomsException.php`
- Create: `src/Room/Domain/Port/RoomTypeRepositoryInterface.php`
- Create: `src/Room/Domain/Port/RoomTypeIdGeneratorInterface.php`
- Create: `src/Room/Domain/Port/RoomTypeHasRoomsInterface.php`

- [ ] **Step 1: Create RoomType model**

```php
<?php
// src/Room/Domain/Model/RoomType.php
declare(strict_types=1);
namespace App\Room\Domain\Model;

use App\Room\Domain\ValueObject\BedComposition;

final readonly class RoomType
{
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
    ) {
    }
}
```

- [ ] **Step 2: Create RoomTypePage model**

```php
<?php
// src/Room/Domain/Model/RoomTypePage.php
declare(strict_types=1);
namespace App\Room\Domain\Model;

final readonly class RoomTypePage
{
    /** @param list<RoomType> $roomTypes */
    public function __construct(
        public array $roomTypes,
        public int $total,
    ) {
    }
}
```

- [ ] **Step 3: Create exceptions**

```php
<?php
// src/Room/Domain/Exception/RoomTypeAlreadyExistsException.php
declare(strict_types=1);
namespace App\Room\Domain\Exception;

final class RoomTypeAlreadyExistsException extends \DomainException
{
    public function __construct(string $name, string $hotelId)
    {
        parent::__construct(sprintf('Room type "%s" already exists in hotel "%s".', $name, $hotelId));
    }
}
```

```php
<?php
// src/Room/Domain/Exception/RoomTypeNotFoundException.php
declare(strict_types=1);
namespace App\Room\Domain\Exception;

final class RoomTypeNotFoundException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Room type "%s" not found.', $id));
    }
}
```

```php
<?php
// src/Room/Domain/Exception/RoomTypeHasRoomsException.php
declare(strict_types=1);
namespace App\Room\Domain\Exception;

final class RoomTypeHasRoomsException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Room type "%s" cannot be deleted: rooms are assigned to it.', $id));
    }
}
```

- [ ] **Step 4: Create ports**

```php
<?php
// src/Room/Domain/Port/RoomTypeRepositoryInterface.php
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
    public function delete(string $id): void;
    public function list(string $hotelId, int $page, int $limit): RoomTypePage;
}
```

```php
<?php
// src/Room/Domain/Port/RoomTypeIdGeneratorInterface.php
declare(strict_types=1);
namespace App\Room\Domain\Port;

interface RoomTypeIdGeneratorInterface
{
    public function generate(): string;
}
```

```php
<?php
// src/Room/Domain/Port/RoomTypeHasRoomsInterface.php
declare(strict_types=1);
namespace App\Room\Domain\Port;

interface RoomTypeHasRoomsInterface
{
    public function hasRooms(string $roomTypeId): bool;
}
```

- [ ] **Step 5: Commit**

```bash
git add src/Room/Domain/
git commit -m "feat(room): add RoomType model, exceptions, and domain ports"
```

---

### Task 3: Test doubles

**Files:**
- Create: `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomTypeRepository.php`
- Create: `tests/Room/Infrastructure/FakeRoomTypeHasRooms.php`

- [ ] **Step 1: Create InMemoryRoomTypeRepository**

```php
<?php
// tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomTypeRepository.php
declare(strict_types=1);
namespace App\Tests\Room\Infrastructure\Persistence\InMemory;

use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Model\RoomTypePage;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;

final class InMemoryRoomTypeRepository implements RoomTypeRepositoryInterface
{
    /** @var array<string, RoomType> */
    private array $roomTypes = [];

    public function add(RoomType $roomType): void
    {
        $this->roomTypes[$roomType->id] = $roomType;
    }

    public function get(string $id): ?RoomType
    {
        return $this->roomTypes[$id] ?? null;
    }

    public function existsByHotelIdAndName(string $hotelId, string $name): bool
    {
        foreach ($this->roomTypes as $roomType) {
            if ($roomType->hotelId === $hotelId && $roomType->name === $name) {
                return true;
            }
        }

        return false;
    }

    public function update(RoomType $roomType): void
    {
        $this->roomTypes[$roomType->id] = $roomType;
    }

    public function delete(string $id): void
    {
        unset($this->roomTypes[$id]);
    }

    public function list(string $hotelId, int $page, int $limit): RoomTypePage
    {
        $filtered = array_values(array_filter(
            $this->roomTypes,
            static fn(RoomType $rt) => $rt->hotelId === $hotelId,
        ));

        usort($filtered, static fn(RoomType $a, RoomType $b) => strcmp($a->name, $b->name));

        $total = count($filtered);
        $roomTypes = array_slice($filtered, ($page - 1) * $limit, $limit);

        return new RoomTypePage($roomTypes, $total);
    }
}
```

- [ ] **Step 2: Create FakeRoomTypeHasRooms**

```php
<?php
// tests/Room/Infrastructure/FakeRoomTypeHasRooms.php
declare(strict_types=1);
namespace App\Tests\Room\Infrastructure;

use App\Room\Domain\Port\RoomTypeHasRoomsInterface;

final class FakeRoomTypeHasRooms implements RoomTypeHasRoomsInterface
{
    private bool $hasRooms = false;

    public function setHasRooms(bool $hasRooms): void
    {
        $this->hasRooms = $hasRooms;
    }

    public function hasRooms(string $roomTypeId): bool
    {
        return $this->hasRooms;
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add tests/Room/Infrastructure/
git commit -m "test(room): add InMemoryRoomTypeRepository and FakeRoomTypeHasRooms test doubles"
```

---

### Task 4: RegisterRoomType use case

**Files:**
- Create: `src/Room/Application/UseCase/RegisterRoomType/RegisterRoomTypeCommand.php`
- Create: `src/Room/Application/UseCase/RegisterRoomType/RegisterRoomTypeCommandHandler.php`
- Create: `src/Room/Application/Service/RegisterRoomTypeCommandFactory.php`
- Test: `tests/Room/Application/UseCase/RegisterRoomType/RegisterRoomTypeCommandHandlerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Room/Application/UseCase/RegisterRoomType/RegisterRoomTypeCommandHandlerTest.php
declare(strict_types=1);
namespace App\Tests\Room\Application\UseCase\RegisterRoomType;

use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommandHandler;
use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomTypeAlreadyExistsException;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegisterRoomTypeCommandHandlerTest extends TestCase
{
    private InMemoryRoomTypeRepository $repository;
    private FakeHotelExistenceChecker $hotelChecker;
    private RegisterRoomTypeCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRoomTypeRepository();
        $this->hotelChecker = new FakeHotelExistenceChecker();
        $this->handler = new RegisterRoomTypeCommandHandler($this->repository, $this->hotelChecker);
    }

    private function makeCommand(string $id = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', string $name = 'Suite Royale'): RegisterRoomTypeCommand
    {
        return new RegisterRoomTypeCommand(
            id: $id,
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            name: $name,
            livingSpaceCount: 2,
            surfaceM2: 80,
            guestCapacity: 2,
            isAccessible: false,
            bedEntries: [['type' => 'king', 'count' => 1]],
            createdAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
        );
    }

    #[Test]
    public function itPersistsTheRoomType(): void
    {
        ($this->handler)($this->makeCommand());

        $roomType = $this->repository->get('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11');
        self::assertNotNull($roomType);
        self::assertSame('Suite Royale', $roomType->name);
        self::assertSame(2, $roomType->livingSpaceCount);
        self::assertSame(80, $roomType->surfaceM2);
        self::assertSame(2, $roomType->guestCapacity);
        self::assertFalse($roomType->isAccessible);
        self::assertSame([['type' => 'king', 'count' => 1]], $roomType->bedComposition->toArray());
    }

    #[Test]
    public function itThrowsWhenHotelDoesNotExist(): void
    {
        $this->hotelChecker->setExists(false);
        $this->expectException(HotelNotFoundException::class);

        ($this->handler)($this->makeCommand());
    }

    #[Test]
    public function itThrowsWhenNameAlreadyExistsInHotel(): void
    {
        ($this->handler)($this->makeCommand('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));

        $this->expectException(RoomTypeAlreadyExistsException::class);

        ($this->handler)($this->makeCommand('b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22'));
    }

    #[Test]
    public function itAllowsSameNameInDifferentHotels(): void
    {
        ($this->handler)($this->makeCommand('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));

        $cmd2 = new RegisterRoomTypeCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            hotelId: '550e8400-e29b-41d4-a716-000000000001',
            name: 'Suite Royale',
            livingSpaceCount: 2,
            surfaceM2: null,
            guestCapacity: 2,
            isAccessible: false,
            bedEntries: [['type' => 'king', 'count' => 1]],
            createdAt: new \DateTimeImmutable(),
        );
        ($this->handler)($cmd2);

        self::assertNotNull($this->repository->get('b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22'));
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
make unit-tests
```
Expected: class not found errors.

- [ ] **Step 3: Create the command**

```php
<?php
// src/Room/Application/UseCase/RegisterRoomType/RegisterRoomTypeCommand.php
declare(strict_types=1);
namespace App\Room\Application\UseCase\RegisterRoomType;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class RegisterRoomTypeCommand implements SyncCommandInterface
{
    /** @param list<array{type: string, count: int}> $bedEntries */
    public function __construct(
        public string $id,
        public string $hotelId,
        public string $name,
        public int $livingSpaceCount,
        public ?int $surfaceM2,
        public int $guestCapacity,
        public bool $isAccessible,
        public array $bedEntries,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 4: Create the handler**

```php
<?php
// src/Room/Application/UseCase/RegisterRoomType/RegisterRoomTypeCommandHandler.php
declare(strict_types=1);
namespace App\Room\Application\UseCase\RegisterRoomType;

use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomTypeAlreadyExistsException;
use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\BedComposition;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class RegisterRoomTypeCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomTypeRepositoryInterface $roomTypeRepository,
        private HotelExistsInterface $hotelExists,
    ) {
    }

    public function __invoke(RegisterRoomTypeCommand $command): void
    {
        if (!$this->hotelExists->exists($command->hotelId)) {
            throw new HotelNotFoundException($command->hotelId);
        }

        if ($this->roomTypeRepository->existsByHotelIdAndName($command->hotelId, $command->name)) {
            throw new RoomTypeAlreadyExistsException($command->name, $command->hotelId);
        }

        $this->roomTypeRepository->add(new RoomType(
            $command->id,
            $command->hotelId,
            $command->name,
            $command->livingSpaceCount,
            $command->surfaceM2,
            $command->guestCapacity,
            $command->isAccessible,
            BedComposition::fromArray($command->bedEntries),
            $command->createdAt,
        ));
    }
}
```

- [ ] **Step 5: Create the factory**

```php
<?php
// src/Room/Application/Service/RegisterRoomTypeCommandFactory.php
declare(strict_types=1);
namespace App\Room\Application\Service;

use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Domain\Port\RoomTypeIdGeneratorInterface;
use Psr\Clock\ClockInterface;

final readonly class RegisterRoomTypeCommandFactory
{
    public function __construct(
        private RoomTypeIdGeneratorInterface $roomTypeIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    /** @param list<array{type: string, count: int}> $bedEntries */
    public function create(
        string $hotelId,
        string $name,
        int $livingSpaceCount,
        ?int $surfaceM2,
        int $guestCapacity,
        bool $isAccessible,
        array $bedEntries,
    ): RegisterRoomTypeCommand {
        return new RegisterRoomTypeCommand(
            id: $this->roomTypeIdGenerator->generate(),
            hotelId: $hotelId,
            name: $name,
            livingSpaceCount: $livingSpaceCount,
            surfaceM2: $surfaceM2,
            guestCapacity: $guestCapacity,
            isAccessible: $isAccessible,
            bedEntries: $bedEntries,
            createdAt: $this->clock->now(),
        );
    }
}
```

- [ ] **Step 6: Run tests to confirm they pass**

```bash
make unit-tests
```
Expected: all RegisterRoomTypeCommandHandlerTest tests PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Room/Application/UseCase/RegisterRoomType/ \
        src/Room/Application/Service/RegisterRoomTypeCommandFactory.php \
        tests/Room/Application/UseCase/RegisterRoomType/
git commit -m "feat(room): add RegisterRoomType use case"
```

---

### Task 5: GetRoomType and ListRoomTypes use cases

**Files:**
- Create: `src/Room/Application/UseCase/GetRoomType/GetRoomTypeQuery.php`
- Create: `src/Room/Application/UseCase/GetRoomType/GetRoomTypeQueryHandler.php`
- Create: `src/Room/Application/UseCase/ListRoomTypes/ListRoomTypesQuery.php`
- Create: `src/Room/Application/UseCase/ListRoomTypes/ListRoomTypesQueryHandler.php`
- Test: `tests/Room/Application/UseCase/GetRoomType/GetRoomTypeQueryHandlerTest.php`
- Test: `tests/Room/Application/UseCase/ListRoomTypes/ListRoomTypesQueryHandlerTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Room/Application/UseCase/GetRoomType/GetRoomTypeQueryHandlerTest.php
declare(strict_types=1);
namespace App\Tests\Room\Application\UseCase\GetRoomType;

use App\Room\Application\UseCase\GetRoomType\GetRoomTypeQuery;
use App\Room\Application\UseCase\GetRoomType\GetRoomTypeQueryHandler;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommandHandler;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetRoomTypeQueryHandlerTest extends TestCase
{
    private InMemoryRoomTypeRepository $repository;
    private GetRoomTypeQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRoomTypeRepository();
        $this->handler = new GetRoomTypeQueryHandler($this->repository);

        $registerHandler = new RegisterRoomTypeCommandHandler($this->repository, new FakeHotelExistenceChecker());
        ($registerHandler)(new RegisterRoomTypeCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            name: 'Single',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 1,
            isAccessible: false,
            bedEntries: [['type' => 'single', 'count' => 1]],
            createdAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
        ));
    }

    #[Test]
    public function itReturnsTheRoomType(): void
    {
        $result = ($this->handler)(new GetRoomTypeQuery('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'));
        self::assertNotNull($result);
        self::assertSame('Single', $result->name);
    }

    #[Test]
    public function itReturnsNullWhenNotFound(): void
    {
        $result = ($this->handler)(new GetRoomTypeQuery('00000000-0000-4000-8000-000000000000'));
        self::assertNull($result);
    }
}
```

```php
<?php
// tests/Room/Application/UseCase/ListRoomTypes/ListRoomTypesQueryHandlerTest.php
declare(strict_types=1);
namespace App\Tests\Room\Application\UseCase\ListRoomTypes;

use App\Room\Application\UseCase\ListRoomTypes\ListRoomTypesQuery;
use App\Room\Application\UseCase\ListRoomTypes\ListRoomTypesQueryHandler;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommandHandler;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ListRoomTypesQueryHandlerTest extends TestCase
{
    private InMemoryRoomTypeRepository $repository;
    private ListRoomTypesQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRoomTypeRepository();
        $this->handler = new ListRoomTypesQueryHandler($this->repository);

        $registerHandler = new RegisterRoomTypeCommandHandler($this->repository, new FakeHotelExistenceChecker());
        foreach (['Suite', 'Double', 'Single'] as $i => $name) {
            ($registerHandler)(new RegisterRoomTypeCommand(
                id: sprintf('a0eebc99-9c0b-4ef8-bb6d-6bb9bd38%04d', $i),
                hotelId: '550e8400-e29b-41d4-a716-446655440000',
                name: $name,
                livingSpaceCount: 1,
                surfaceM2: null,
                guestCapacity: 1,
                isAccessible: false,
                bedEntries: [['type' => 'single', 'count' => 1]],
                createdAt: new \DateTimeImmutable(),
            ));
        }
    }

    #[Test]
    public function itReturnsRoomTypesSortedByName(): void
    {
        $page = ($this->handler)(new ListRoomTypesQuery('550e8400-e29b-41d4-a716-446655440000', 1, 20));
        self::assertSame(3, $page->total);
        self::assertSame('Double', $page->roomTypes[0]->name);
        self::assertSame('Single', $page->roomTypes[1]->name);
        self::assertSame('Suite', $page->roomTypes[2]->name);
    }

    #[Test]
    public function itPaginates(): void
    {
        $page = ($this->handler)(new ListRoomTypesQuery('550e8400-e29b-41d4-a716-446655440000', 1, 2));
        self::assertSame(3, $page->total);
        self::assertCount(2, $page->roomTypes);
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
make unit-tests
```

- [ ] **Step 3: Create query classes and handlers**

```php
<?php
// src/Room/Application/UseCase/GetRoomType/GetRoomTypeQuery.php
declare(strict_types=1);
namespace App\Room\Application\UseCase\GetRoomType;

use App\Shared\Application\Bus\SyncQueryInterface;

final readonly class GetRoomTypeQuery implements SyncQueryInterface
{
    public function __construct(public string $id) {}
}
```

```php
<?php
// src/Room/Application/UseCase/GetRoomType/GetRoomTypeQueryHandler.php
declare(strict_types=1);
namespace App\Room\Application\UseCase\GetRoomType;

use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetRoomTypeQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private RoomTypeRepositoryInterface $roomTypeRepository) {}

    public function __invoke(GetRoomTypeQuery $query): ?RoomType
    {
        return $this->roomTypeRepository->get($query->id);
    }
}
```

```php
<?php
// src/Room/Application/UseCase/ListRoomTypes/ListRoomTypesQuery.php
declare(strict_types=1);
namespace App\Room\Application\UseCase\ListRoomTypes;

use App\Shared\Application\Bus\SyncQueryInterface;

final readonly class ListRoomTypesQuery implements SyncQueryInterface
{
    public function __construct(
        public string $hotelId,
        public int $page,
        public int $limit,
    ) {}
}
```

```php
<?php
// src/Room/Application/UseCase/ListRoomTypes/ListRoomTypesQueryHandler.php
declare(strict_types=1);
namespace App\Room\Application\UseCase\ListRoomTypes;

use App\Room\Domain\Model\RoomTypePage;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class ListRoomTypesQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private RoomTypeRepositoryInterface $roomTypeRepository) {}

    public function __invoke(ListRoomTypesQuery $query): RoomTypePage
    {
        return $this->roomTypeRepository->list($query->hotelId, $query->page, $query->limit);
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
make unit-tests
```

- [ ] **Step 5: Commit**

```bash
git add src/Room/Application/UseCase/GetRoomType/ \
        src/Room/Application/UseCase/ListRoomTypes/ \
        tests/Room/Application/UseCase/GetRoomType/ \
        tests/Room/Application/UseCase/ListRoomTypes/
git commit -m "feat(room): add GetRoomType and ListRoomTypes use cases"
```

---

### Task 6: UpdateRoomType use case

**Files:**
- Create: `src/Room/Application/UseCase/UpdateRoomType/UpdateRoomTypeCommand.php`
- Create: `src/Room/Application/UseCase/UpdateRoomType/UpdateRoomTypeCommandHandler.php`
- Test: `tests/Room/Application/UseCase/UpdateRoomType/UpdateRoomTypeCommandHandlerTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Room/Application/UseCase/UpdateRoomType/UpdateRoomTypeCommandHandlerTest.php
declare(strict_types=1);
namespace App\Tests\Room\Application\UseCase\UpdateRoomType;

use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommandHandler;
use App\Room\Application\UseCase\UpdateRoomType\UpdateRoomTypeCommand;
use App\Room\Application\UseCase\UpdateRoomType\UpdateRoomTypeCommandHandler;
use App\Room\Domain\Exception\RoomTypeAlreadyExistsException;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UpdateRoomTypeCommandHandlerTest extends TestCase
{
    private InMemoryRoomTypeRepository $repository;
    private UpdateRoomTypeCommandHandler $handler;

    private const string HOTEL_ID = '550e8400-e29b-41d4-a716-446655440000';
    private const string ROOM_TYPE_ID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    protected function setUp(): void
    {
        $this->repository = new InMemoryRoomTypeRepository();
        $this->handler = new UpdateRoomTypeCommandHandler($this->repository);

        $registerHandler = new RegisterRoomTypeCommandHandler($this->repository, new FakeHotelExistenceChecker());
        ($registerHandler)(new RegisterRoomTypeCommand(
            id: self::ROOM_TYPE_ID,
            hotelId: self::HOTEL_ID,
            name: 'Single',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 1,
            isAccessible: false,
            bedEntries: [['type' => 'single', 'count' => 1]],
            createdAt: new \DateTimeImmutable('2024-01-01'),
        ));
    }

    #[Test]
    public function itUpdatesTheRoomType(): void
    {
        ($this->handler)(new UpdateRoomTypeCommand(
            id: self::ROOM_TYPE_ID,
            name: 'Double',
            livingSpaceCount: 1,
            surfaceM2: 25,
            guestCapacity: 2,
            isAccessible: true,
            bedEntries: [['type' => 'double', 'count' => 1]],
        ));

        $updated = $this->repository->get(self::ROOM_TYPE_ID);
        self::assertNotNull($updated);
        self::assertSame('Double', $updated->name);
        self::assertSame(25, $updated->surfaceM2);
        self::assertSame(2, $updated->guestCapacity);
        self::assertTrue($updated->isAccessible);
        self::assertSame([['type' => 'double', 'count' => 1]], $updated->bedComposition->toArray());
        self::assertSame(self::HOTEL_ID, $updated->hotelId);
    }

    #[Test]
    public function itThrowsWhenRoomTypeNotFound(): void
    {
        $this->expectException(RoomTypeNotFoundException::class);

        ($this->handler)(new UpdateRoomTypeCommand(
            id: '00000000-0000-4000-8000-000000000000',
            name: 'X',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 1,
            isAccessible: false,
            bedEntries: [['type' => 'single', 'count' => 1]],
        ));
    }

    #[Test]
    public function itThrowsWhenNewNameAlreadyTaken(): void
    {
        $registerHandler = new RegisterRoomTypeCommandHandler($this->repository, new FakeHotelExistenceChecker());
        ($registerHandler)(new RegisterRoomTypeCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            hotelId: self::HOTEL_ID,
            name: 'Double',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 2,
            isAccessible: false,
            bedEntries: [['type' => 'double', 'count' => 1]],
            createdAt: new \DateTimeImmutable(),
        ));

        $this->expectException(RoomTypeAlreadyExistsException::class);

        ($this->handler)(new UpdateRoomTypeCommand(
            id: self::ROOM_TYPE_ID,
            name: 'Double',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 1,
            isAccessible: false,
            bedEntries: [['type' => 'single', 'count' => 1]],
        ));
    }

    #[Test]
    public function itAllowsKeepingTheSameName(): void
    {
        ($this->handler)(new UpdateRoomTypeCommand(
            id: self::ROOM_TYPE_ID,
            name: 'Single',
            livingSpaceCount: 1,
            surfaceM2: 20,
            guestCapacity: 1,
            isAccessible: false,
            bedEntries: [['type' => 'single', 'count' => 1]],
        ));

        $updated = $this->repository->get(self::ROOM_TYPE_ID);
        self::assertNotNull($updated);
        self::assertSame(20, $updated->surfaceM2);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
make unit-tests
```

- [ ] **Step 3: Create the command and handler**

```php
<?php
// src/Room/Application/UseCase/UpdateRoomType/UpdateRoomTypeCommand.php
declare(strict_types=1);
namespace App\Room\Application\UseCase\UpdateRoomType;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class UpdateRoomTypeCommand implements SyncCommandInterface
{
    /** @param list<array{type: string, count: int}> $bedEntries */
    public function __construct(
        public string $id,
        public string $name,
        public int $livingSpaceCount,
        public ?int $surfaceM2,
        public int $guestCapacity,
        public bool $isAccessible,
        public array $bedEntries,
    ) {
    }
}
```

```php
<?php
// src/Room/Application/UseCase/UpdateRoomType/UpdateRoomTypeCommandHandler.php
declare(strict_types=1);
namespace App\Room\Application\UseCase\UpdateRoomType;

use App\Room\Domain\Exception\RoomTypeAlreadyExistsException;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\BedComposition;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class UpdateRoomTypeCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(private RoomTypeRepositoryInterface $roomTypeRepository) {}

    public function __invoke(UpdateRoomTypeCommand $command): void
    {
        $roomType = $this->roomTypeRepository->get($command->id);
        if (null === $roomType) {
            throw new RoomTypeNotFoundException($command->id);
        }

        if ($roomType->name !== $command->name
            && $this->roomTypeRepository->existsByHotelIdAndName($roomType->hotelId, $command->name)
        ) {
            throw new RoomTypeAlreadyExistsException($command->name, $roomType->hotelId);
        }

        $this->roomTypeRepository->update(new RoomType(
            $roomType->id,
            $roomType->hotelId,
            $command->name,
            $command->livingSpaceCount,
            $command->surfaceM2,
            $command->guestCapacity,
            $command->isAccessible,
            BedComposition::fromArray($command->bedEntries),
            $roomType->createdAt,
        ));
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
make unit-tests
```

- [ ] **Step 5: Commit**

```bash
git add src/Room/Application/UseCase/UpdateRoomType/ \
        tests/Room/Application/UseCase/UpdateRoomType/
git commit -m "feat(room): add UpdateRoomType use case"
```

---

### Task 7: DeleteRoomType use case

**Files:**
- Create: `src/Room/Application/UseCase/DeleteRoomType/DeleteRoomTypeCommand.php`
- Create: `src/Room/Application/UseCase/DeleteRoomType/DeleteRoomTypeCommandHandler.php`
- Test: `tests/Room/Application/UseCase/DeleteRoomType/DeleteRoomTypeCommandHandlerTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Room/Application/UseCase/DeleteRoomType/DeleteRoomTypeCommandHandlerTest.php
declare(strict_types=1);
namespace App\Tests\Room\Application\UseCase\DeleteRoomType;

use App\Room\Application\UseCase\DeleteRoomType\DeleteRoomTypeCommand;
use App\Room\Application\UseCase\DeleteRoomType\DeleteRoomTypeCommandHandler;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommandHandler;
use App\Room\Domain\Exception\RoomTypeHasRoomsException;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\FakeRoomTypeHasRooms;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomTypeRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeleteRoomTypeCommandHandlerTest extends TestCase
{
    private InMemoryRoomTypeRepository $repository;
    private FakeRoomTypeHasRooms $hasRooms;
    private DeleteRoomTypeCommandHandler $handler;

    private const string ROOM_TYPE_ID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    protected function setUp(): void
    {
        $this->repository = new InMemoryRoomTypeRepository();
        $this->hasRooms = new FakeRoomTypeHasRooms();
        $this->handler = new DeleteRoomTypeCommandHandler($this->repository, $this->hasRooms);

        $registerHandler = new RegisterRoomTypeCommandHandler($this->repository, new FakeHotelExistenceChecker());
        ($registerHandler)(new RegisterRoomTypeCommand(
            id: self::ROOM_TYPE_ID,
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            name: 'Single',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 1,
            isAccessible: false,
            bedEntries: [['type' => 'single', 'count' => 1]],
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itDeletesTheRoomType(): void
    {
        ($this->handler)(new DeleteRoomTypeCommand(self::ROOM_TYPE_ID));

        self::assertNull($this->repository->get(self::ROOM_TYPE_ID));
    }

    #[Test]
    public function itThrowsWhenRoomTypeNotFound(): void
    {
        $this->expectException(RoomTypeNotFoundException::class);

        ($this->handler)(new DeleteRoomTypeCommand('00000000-0000-4000-8000-000000000000'));
    }

    #[Test]
    public function itThrowsWhenRoomsAreAssigned(): void
    {
        $this->hasRooms->setHasRooms(true);
        $this->expectException(RoomTypeHasRoomsException::class);

        ($this->handler)(new DeleteRoomTypeCommand(self::ROOM_TYPE_ID));
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
make unit-tests
```

- [ ] **Step 3: Create command and handler**

```php
<?php
// src/Room/Application/UseCase/DeleteRoomType/DeleteRoomTypeCommand.php
declare(strict_types=1);
namespace App\Room\Application\UseCase\DeleteRoomType;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class DeleteRoomTypeCommand implements SyncCommandInterface
{
    public function __construct(public string $id) {}
}
```

```php
<?php
// src/Room/Application/UseCase/DeleteRoomType/DeleteRoomTypeCommandHandler.php
declare(strict_types=1);
namespace App\Room\Application\UseCase\DeleteRoomType;

use App\Room\Domain\Exception\RoomTypeHasRoomsException;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Room\Domain\Port\RoomTypeHasRoomsInterface;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class DeleteRoomTypeCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomTypeRepositoryInterface $roomTypeRepository,
        private RoomTypeHasRoomsInterface $roomTypeHasRooms,
    ) {
    }

    public function __invoke(DeleteRoomTypeCommand $command): void
    {
        $roomType = $this->roomTypeRepository->get($command->id);
        if (null === $roomType) {
            throw new RoomTypeNotFoundException($command->id);
        }

        if ($this->roomTypeHasRooms->hasRooms($command->id)) {
            throw new RoomTypeHasRoomsException($command->id);
        }

        $this->roomTypeRepository->delete($command->id);
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
make unit-tests
```

- [ ] **Step 5: Commit**

```bash
git add src/Room/Application/UseCase/DeleteRoomType/ \
        tests/Room/Application/UseCase/DeleteRoomType/
git commit -m "feat(room): add DeleteRoomType use case"
```

---

### Task 8: Infrastructure — repository, ID generator, migration

**Files:**
- Create: `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeRepository.php`
- Create: `src/Room/Infrastructure/Service/RoomTypeIdGenerator.php`
- Create: migration (via `make generate-migration`)

- [ ] **Step 1: Create RoomTypeIdGenerator**

```php
<?php
// src/Room/Infrastructure/Service/RoomTypeIdGenerator.php
declare(strict_types=1);
namespace App\Room\Infrastructure\Service;

use App\Room\Domain\Port\RoomTypeIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class RoomTypeIdGenerator implements RoomTypeIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}
```

- [ ] **Step 2: Create RoomTypeRepository**

```php
<?php
// src/Room/Infrastructure/Persistence/Doctrine/RoomTypeRepository.php
declare(strict_types=1);
namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Model\RoomTypePage;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\BedComposition;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

final readonly class RoomTypeRepository implements RoomTypeRepositoryInterface
{
    public function __construct(private Connection $bookit) {}

    public function add(RoomType $roomType): void
    {
        $this->bookit->insert('room_type', [
            'id' => $roomType->id,
            'hotel_id' => $roomType->hotelId,
            'name' => $roomType->name,
            'living_space_count' => $roomType->livingSpaceCount,
            'surface_m2' => $roomType->surfaceM2,
            'guest_capacity' => $roomType->guestCapacity,
            'is_accessible' => $roomType->isAccessible,
            'bed_composition' => json_encode($roomType->bedComposition->toArray(), \JSON_THROW_ON_ERROR),
            'created_at' => $roomType->createdAt->format('Y-m-d H:i:s'),
        ], [
            'is_accessible' => Types::BOOLEAN,
        ]);
    }

    public function get(string $id): ?RoomType
    {
        /** @var array{id: string, hotel_id: string, name: string, living_space_count: int|string, surface_m2: int|string|null, guest_capacity: int|string, is_accessible: string|bool, bed_composition: string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, hotel_id, name, living_space_count, surface_m2, guest_capacity, is_accessible, bed_composition, created_at FROM room_type WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function existsByHotelIdAndName(string $hotelId, string $name): bool
    {
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM room_type WHERE hotel_id = :hotelId AND name = :name',
            ['hotelId' => $hotelId, 'name' => $name],
        );

        return $count > 0;
    }

    public function update(RoomType $roomType): void
    {
        $this->bookit->update('room_type', [
            'name' => $roomType->name,
            'living_space_count' => $roomType->livingSpaceCount,
            'surface_m2' => $roomType->surfaceM2,
            'guest_capacity' => $roomType->guestCapacity,
            'is_accessible' => $roomType->isAccessible,
            'bed_composition' => json_encode($roomType->bedComposition->toArray(), \JSON_THROW_ON_ERROR),
        ], ['id' => $roomType->id], [
            'is_accessible' => Types::BOOLEAN,
        ]);
    }

    public function delete(string $id): void
    {
        $this->bookit->delete('room_type', ['id' => $id]);
    }

    public function list(string $hotelId, int $page, int $limit): RoomTypePage
    {
        /** @var int|string $count */
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM room_type WHERE hotel_id = :hotelId',
            ['hotelId' => $hotelId],
        );
        $total = (int) $count;

        /** @var list<array{id: string, hotel_id: string, name: string, living_space_count: int|string, surface_m2: int|string|null, guest_capacity: int|string, is_accessible: string|bool, bed_composition: string, created_at: string}> $rows */
        $rows = $this->bookit->fetchAllAssociative(
            'SELECT id, hotel_id, name, living_space_count, surface_m2, guest_capacity, is_accessible, bed_composition, created_at FROM room_type WHERE hotel_id = :hotelId ORDER BY name ASC LIMIT :limit OFFSET :offset',
            ['hotelId' => $hotelId, 'limit' => $limit, 'offset' => ($page - 1) * $limit],
        );

        return new RoomTypePage(array_map($this->hydrate(...), $rows), $total);
    }

    /**
     * @param array{id: string, hotel_id: string, name: string, living_space_count: int|string, surface_m2: int|string|null, guest_capacity: int|string, is_accessible: string|bool, bed_composition: string, created_at: string} $row
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
        );
    }
}
```

- [ ] **Step 3: Update `config/services/room.yaml`**

Add the `RoomTypeIdGeneratorInterface` binding and the `RoomTypeHasRoomsInterface` binding at the end of the file:

```yaml
# config/services/room.yaml
parameters: {}

services:
    _defaults:
        autowire: true
        autoconfigure: true
    _instanceof:
        App\Shared\Application\Bus\SyncCommandHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: sync.command.bus}
        App\Shared\Application\Bus\SyncQueryHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: sync.query.bus}

    App\Room\Domain\:
        resource: '../../src/Room/Domain/'
        exclude:
            - '../../src/Room/Domain/Model/'

    App\Room\Application\:
        resource: '../../src/Room/Application/'
        exclude:
            - '../../src/Room/Application/**/*Exception.php'
            - '../../src/Room/Application/**/*Command.php'
            - '../../src/Room/Application/**/*Query.php'

    App\Room\Infrastructure\:
        resource: '../../src/Room/Infrastructure/'
        exclude:
            - '../../src/Room/Infrastructure/**/*Exception.php'

    App\Room\UI\:
        resource: '../../src/Room/UI/'
        exclude:
            - '../../src/Room/UI/**/*Request.php'

    App\Room\Domain\Port\RoomTypeIdGeneratorInterface:
        class: App\Room\Infrastructure\Service\RoomTypeIdGenerator

    App\Room\Domain\Port\RoomTypeHasRoomsInterface:
        class: App\Room\Infrastructure\Persistence\Doctrine\RoomTypeHasRoomsChecker
```

- [ ] **Step 4: Create the `RoomTypeHasRoomsChecker` infrastructure class**

This class will be needed by Plan B (once `room.room_type_id` column exists), but we must declare it now since `room.yaml` references it. Create a stub that always returns `false` — Plan B will replace the implementation.

```php
<?php
// src/Room/Infrastructure/Persistence/Doctrine/RoomTypeHasRoomsChecker.php
declare(strict_types=1);
namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Port\RoomTypeHasRoomsInterface;
use Doctrine\DBAL\Connection;

final readonly class RoomTypeHasRoomsChecker implements RoomTypeHasRoomsInterface
{
    public function __construct(private Connection $bookit) {}

    public function hasRooms(string $roomTypeId): bool
    {
        // Stub — returns false until room.room_type_id column is added in Plan B
        return false;
    }
}
```

- [ ] **Step 5: Generate the migration**

Run in Docker:
```bash
make generate-migration
```

This creates a new file in `migrations/`. Open it and replace the generated body with:

```php
public function getDescription(): string
{
    return 'Create room_type table';
}

public function up(Schema $schema): void
{
    $this->addSql('CREATE TABLE room_type (
        id UUID NOT NULL,
        hotel_id UUID NOT NULL,
        name VARCHAR(100) NOT NULL,
        living_space_count SMALLINT NOT NULL,
        surface_m2 SMALLINT DEFAULT NULL,
        guest_capacity SMALLINT NOT NULL,
        is_accessible BOOLEAN NOT NULL DEFAULT FALSE,
        bed_composition JSONB NOT NULL,
        created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
        CONSTRAINT pk_room_type PRIMARY KEY (id),
        CONSTRAINT uq_room_type_hotel_name UNIQUE (hotel_id, name)
    )');
}

public function down(Schema $schema): void
{
    $this->addSql('DROP TABLE room_type');
}
```

- [ ] **Step 6: Run the migration**

```bash
make migrate
```
Expected: migration applied with no errors.

- [ ] **Step 7: Run integration tests to confirm the container compiles**

```bash
make integration-tests
```
Expected: no `ServiceNotFoundException` or wiring errors.

- [ ] **Step 8: Commit**

```bash
git add src/Room/Infrastructure/ \
        config/services/room.yaml \
        migrations/
git commit -m "feat(room): add RoomTypeRepository, RoomTypeIdGenerator, and room_type migration"
```

---

### Task 9: Update exceptions.yaml

**Files:**
- Modify: `config/services/exceptions.yaml`

- [ ] **Step 1: Add Room Type exception mappings**

In `config/services/exceptions.yaml`, inside the `$map:` block, add after the existing Room entries:

```yaml
App\Room\Domain\Exception\RoomTypeAlreadyExistsException:
    type: 'https://book.it/problems/room-type-already-exists'
    title: 'Room Type Already Exists'
    status: 409
App\Room\Domain\Exception\RoomTypeNotFoundException:
    type: 'https://book.it/problems/room-type-not-found'
    title: 'Room Type Not Found'
    status: 404
App\Room\Domain\Exception\RoomTypeHasRoomsException:
    type: 'https://book.it/problems/room-type-has-rooms'
    title: 'Room Type Has Rooms'
    status: 409
```

- [ ] **Step 2: Commit**

```bash
git add config/services/exceptions.yaml
git commit -m "feat(room): map RoomType exceptions to HTTP problem responses"
```

---

### Task 10: RoomTypeSerializer and Register + Get + List HTTP controllers

**Files:**
- Create: `src/Room/UI/Http/Controller/RoomTypeSerializer.php`
- Create: `src/Room/UI/Http/Controller/RegisterRoomType/RegisterRoomTypeRequest.php`
- Create: `src/Room/UI/Http/Controller/RegisterRoomType/RegisterRoomTypeController.php`
- Create: `src/Room/UI/Http/Controller/GetRoomType/GetRoomTypeController.php`
- Create: `src/Room/UI/Http/Controller/ListRoomTypes/ListRoomTypesRequest.php`
- Create: `src/Room/UI/Http/Controller/ListRoomTypes/RoomTypeCatalogueSerializer.php`
- Create: `src/Room/UI/Http/Controller/ListRoomTypes/ListRoomTypesController.php`
- Test: `tests/Room/UI/Http/Controller/RegisterRoomType/RegisterRoomTypeControllerTest.php`
- Test: `tests/Room/UI/Http/Controller/GetRoomType/GetRoomTypeControllerTest.php`
- Test: `tests/Room/UI/Http/Controller/ListRoomTypes/ListRoomTypesControllerTest.php`

- [ ] **Step 1: Create RoomTypeSerializer**

```php
<?php
// src/Room/UI/Http/Controller/RoomTypeSerializer.php
declare(strict_types=1);
namespace App\Room\UI\Http\Controller;

use App\Room\Domain\Model\RoomType;

final class RoomTypeSerializer
{
    /**
     * @return array{id: string, hotelId: string, name: string, livingSpaceCount: int, surfaceM2: int|null, guestCapacity: int, isAccessible: bool, bedComposition: list<array{type: string, count: int}>, createdAt: int}
     */
    public function serialize(RoomType $roomType): array
    {
        return [
            'id' => $roomType->id,
            'hotelId' => $roomType->hotelId,
            'name' => $roomType->name,
            'livingSpaceCount' => $roomType->livingSpaceCount,
            'surfaceM2' => $roomType->surfaceM2,
            'guestCapacity' => $roomType->guestCapacity,
            'isAccessible' => $roomType->isAccessible,
            'bedComposition' => $roomType->bedComposition->toArray(),
            'createdAt' => $roomType->createdAt->getTimestamp(),
        ];
    }
}
```

- [ ] **Step 2: Create RegisterRoomTypeRequest**

```php
<?php
// src/Room/UI/Http/Controller/RegisterRoomType/RegisterRoomTypeRequest.php
declare(strict_types=1);
namespace App\Room\UI\Http\Controller\RegisterRoomType;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterRoomTypeRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        #[OA\Property(type: 'string', example: 'Suite Royale', maxLength: 100, minLength: 1)]
        public ?string $name = null,

        #[Assert\NotNull]
        #[Assert\Range(min: 1, max: 20)]
        #[OA\Property(type: 'integer', example: 2, minimum: 1, maximum: 20, nullable: false)]
        public ?int $livingSpaceCount = null,

        #[Assert\Range(min: 1, max: 2000)]
        #[OA\Property(type: 'integer', example: 80, minimum: 1, maximum: 2000, nullable: true)]
        public ?int $surfaceM2 = null,

        #[Assert\NotNull]
        #[Assert\Range(min: 1, max: 20)]
        #[OA\Property(type: 'integer', example: 2, minimum: 1, maximum: 20, nullable: false)]
        public ?int $guestCapacity = null,

        #[Assert\NotNull]
        #[OA\Property(type: 'boolean', example: false, nullable: false)]
        public ?bool $isAccessible = null,

        #[Assert\NotNull]
        #[Assert\Count(min: 1)]
        #[Assert\All([
            new Assert\Collection([
                'type' => [
                    new Assert\NotBlank(),
                    new Assert\Choice(choices: ['single', 'double', 'queen', 'king', 'bunk', 'sofa_bed', 'baby_cot']),
                ],
                'count' => [
                    new Assert\NotNull(),
                    new Assert\Type('integer'),
                    new Assert\Range(min: 1, max: 10),
                ],
            ]),
        ])]
        #[OA\Property(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['single', 'double', 'queen', 'king', 'bunk', 'sofa_bed', 'baby_cot']),
                    new OA\Property(property: 'count', type: 'integer', minimum: 1, maximum: 10),
                ],
                type: 'object',
            ),
        )]
        public ?array $bedComposition = null,
    ) {
    }
}
```

- [ ] **Step 3: Write failing functional tests for Register + Get + List**

```php
<?php
// tests/Room/UI/Http/Controller/RegisterRoomType/RegisterRoomTypeControllerTest.php
declare(strict_types=1);
namespace App\Tests\Room\UI\Http\Controller\RegisterRoomType;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class RegisterRoomTypeControllerTest extends WebTestCase
{
    private const array HOTEL_PAYLOAD = [
        'name' => 'Hotel Test',
        'streetAddress' => '1 rue de la Paix',
        'postalCode' => '75001',
        'city' => 'Paris',
        'country' => 'FR',
    ];

    private const array VALID_PAYLOAD = [
        'name' => 'Suite Royale',
        'livingSpaceCount' => 2,
        'surfaceM2' => 80,
        'guestCapacity' => 2,
        'isAccessible' => false,
        'bedComposition' => [['type' => 'king', 'count' => 1]],
    ];

    #[Test]
    public function itRegistersARoomTypeAndReturns201(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/room-types",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{id: string, hotelId: string, name: string, livingSpaceCount: int, surfaceM2: int|null, guestCapacity: int, isAccessible: bool, bedComposition: list<array{type: string, count: int}>, createdAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['id']);
        self::assertSame($hotelId, $body['hotelId']);
        self::assertSame('Suite Royale', $body['name']);
        self::assertSame(2, $body['livingSpaceCount']);
        self::assertSame(80, $body['surfaceM2']);
        self::assertSame(2, $body['guestCapacity']);
        self::assertFalse($body['isAccessible']);
        self::assertSame([['type' => 'king', 'count' => 1]], $body['bedComposition']);
    }

    #[Test]
    public function itAcceptsNullSurfaceM2(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $payload = self::VALID_PAYLOAD;
        unset($payload['surfaceM2']);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/room-types",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        /** @var array{surfaceM2: int|null} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNull($body['surfaceM2']);
    }

    #[Test]
    public function itReturns409WhenNameAlreadyExistsInHotel(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR));
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR));

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        /** @var array{type: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-type-already-exists', $body['type']);
    }

    #[Test]
    public function itReturns404WhenHotelDoesNotExist(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/v1/hotels/00000000-0000-4000-8000-000000000000/room-types', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR));

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenNameIsMissing(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $payload = self::VALID_PAYLOAD;
        unset($payload['name']);

        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenBedCompositionIsEmpty(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $payload = array_merge(self::VALID_PAYLOAD, ['bedComposition' => []]);

        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenBedTypeIsInvalid(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $payload = array_merge(self::VALID_PAYLOAD, ['bedComposition' => [['type' => 'hammock', 'count' => 1]]]);

        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request('POST', '/api/v1/hotels', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}
```

```php
<?php
// tests/Room/UI/Http/Controller/GetRoomType/GetRoomTypeControllerTest.php
declare(strict_types=1);
namespace App\Tests\Room\UI\Http\Controller\GetRoomType;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetRoomTypeControllerTest extends WebTestCase
{
    private const array HOTEL_PAYLOAD = ['name' => 'Hotel Test', 'streetAddress' => '1 rue de la Paix', 'postalCode' => '75001', 'city' => 'Paris', 'country' => 'FR'];
    private const array ROOM_TYPE_PAYLOAD = ['name' => 'Single', 'livingSpaceCount' => 1, 'guestCapacity' => 1, 'isAccessible' => false, 'bedComposition' => [['type' => 'single', 'count' => 1]]];

    #[Test]
    public function itReturnsTheRoomType(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request('GET', "/api/v1/hotels/{$hotelId}/room-types/{$roomTypeId}");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        /** @var array{id: string, name: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($roomTypeId, $body['id']);
        self::assertSame('Single', $body['name']);
    }

    #[Test]
    public function itReturns404WhenNotFound(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request('GET', "/api/v1/hotels/{$hotelId}/room-types/00000000-0000-4000-8000-000000000000");

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request('POST', '/api/v1/hotels', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    private function registerRoomTypeAndGetId(KernelBrowser $client, string $hotelId): string
    {
        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::ROOM_TYPE_PAYLOAD, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}
```

```php
<?php
// tests/Room/UI/Http/Controller/ListRoomTypes/ListRoomTypesControllerTest.php
declare(strict_types=1);
namespace App\Tests\Room\UI\Http\Controller\ListRoomTypes;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class ListRoomTypesControllerTest extends WebTestCase
{
    private const array HOTEL_PAYLOAD = ['name' => 'Hotel Test', 'streetAddress' => '1 rue de la Paix', 'postalCode' => '75001', 'city' => 'Paris', 'country' => 'FR'];

    private function makeRoomTypePayload(string $name): array
    {
        return ['name' => $name, 'livingSpaceCount' => 1, 'guestCapacity' => 1, 'isAccessible' => false, 'bedComposition' => [['type' => 'single', 'count' => 1]]];
    }

    #[Test]
    public function itReturnsAPaginatedList(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        foreach (['Suite', 'Double', 'Single'] as $name) {
            $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($this->makeRoomTypePayload($name), \JSON_THROW_ON_ERROR));
        }

        $client->request('GET', "/api/v1/hotels/{$hotelId}/room-types");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{name: string}>, meta: array{total: int, page: int, limit: int, totalPages: int}} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(3, $body['meta']['total']);
        self::assertSame('Double', $body['data'][0]['name']);
        self::assertSame('Single', $body['data'][1]['name']);
        self::assertSame('Suite', $body['data'][2]['name']);
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request('POST', '/api/v1/hotels', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}
```

- [ ] **Step 4: Run tests to confirm they fail**

```bash
make functional-tests
```
Expected: route not found / controller not found errors.

- [ ] **Step 5: Create RegisterRoomTypeController**

```php
<?php
// src/Room/UI/Http/Controller/RegisterRoomType/RegisterRoomTypeController.php
declare(strict_types=1);
namespace App\Room\UI\Http\Controller\RegisterRoomType;

use App\Room\Application\Service\RegisterRoomTypeCommandFactory;
use App\Room\Application\UseCase\GetRoomType\GetRoomTypeQuery;
use App\Room\UI\Http\Controller\RoomTypeSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class RegisterRoomTypeController
{
    public function __construct(
        private RegisterRoomTypeCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private RoomTypeSerializer $roomTypeSerializer,
    ) {
    }

    #[Route('/hotels/{hotelId}/room-types', name: 'room_register_room_type', requirements: ['hotelId' => Requirement::UUID_V4], methods: ['POST'])]
    #[OA\Post(
        summary: 'Register a new room type in a hotel',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: RegisterRoomTypeRequest::class)),
        ),
        tags: ['Room Types'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_CREATED, description: 'Room type registered'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Hotel not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Room type name already exists', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $hotelId,
        #[MapRequestPayload(acceptFormat: 'json')] RegisterRoomTypeRequest $request,
    ): Response {
        $command = $this->commandFactory->create(
            hotelId: $hotelId,
            name: $request->name ?? throw new \LogicException('name is required'),
            livingSpaceCount: $request->livingSpaceCount ?? throw new \LogicException('livingSpaceCount is required'),
            surfaceM2: $request->surfaceM2,
            guestCapacity: $request->guestCapacity ?? throw new \LogicException('guestCapacity is required'),
            isAccessible: $request->isAccessible ?? throw new \LogicException('isAccessible is required'),
            bedEntries: $request->bedComposition ?? throw new \LogicException('bedComposition is required'),
        );
        $this->commandBus->execute($command);

        $roomType = $this->queryBus->ask(new GetRoomTypeQuery($command->id));
        if (null === $roomType) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->roomTypeSerializer->serialize($roomType), Response::HTTP_CREATED);
    }
}
```

- [ ] **Step 6: Create GetRoomTypeController**

```php
<?php
// src/Room/UI/Http/Controller/GetRoomType/GetRoomTypeController.php
declare(strict_types=1);
namespace App\Room\UI\Http\Controller\GetRoomType;

use App\Room\Application\UseCase\GetRoomType\GetRoomTypeQuery;
use App\Room\UI\Http\Controller\RoomTypeSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetRoomTypeController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private RoomTypeSerializer $roomTypeSerializer,
    ) {
    }

    #[Route('/hotels/{hotelId}/room-types/{roomTypeId}', name: 'room_get_room_type', requirements: ['hotelId' => Requirement::UUID_V4, 'roomTypeId' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a room type by id',
        tags: ['Room Types'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'roomTypeId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_OK, description: 'Room type found'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Room type not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(string $hotelId, string $roomTypeId): Response
    {
        $roomType = $this->queryBus->ask(new GetRoomTypeQuery($roomTypeId));
        if (null === $roomType) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->roomTypeSerializer->serialize($roomType));
    }
}
```

- [ ] **Step 7: Create ListRoomTypes controller and support classes**

```php
<?php
// src/Room/UI/Http/Controller/ListRoomTypes/ListRoomTypesRequest.php
declare(strict_types=1);
namespace App\Room\UI\Http\Controller\ListRoomTypes;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListRoomTypesRequest
{
    public function __construct(
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

```php
<?php
// src/Room/UI/Http/Controller/ListRoomTypes/RoomTypeCatalogueSerializer.php
declare(strict_types=1);
namespace App\Room\UI\Http\Controller\ListRoomTypes;

use App\Room\Domain\Model\RoomTypePage;
use App\Room\UI\Http\Controller\RoomTypeSerializer;

final class RoomTypeCatalogueSerializer
{
    public function __construct(private RoomTypeSerializer $roomTypeSerializer) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, limit: int, total: int, totalPages: int}}
     */
    public function serialize(RoomTypePage $page, int $pageNum, int $limit): array
    {
        return [
            'data' => array_map($this->roomTypeSerializer->serialize(...), $page->roomTypes),
            'meta' => [
                'page' => $pageNum,
                'limit' => $limit,
                'total' => $page->total,
                'totalPages' => (int) ceil($page->total / $limit),
            ],
        ];
    }
}
```

```php
<?php
// src/Room/UI/Http/Controller/ListRoomTypes/ListRoomTypesController.php
declare(strict_types=1);
namespace App\Room\UI\Http\Controller\ListRoomTypes;

use App\Room\Application\UseCase\ListRoomTypes\ListRoomTypesQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class ListRoomTypesController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private RoomTypeCatalogueSerializer $serializer,
    ) {
    }

    #[Route('/hotels/{hotelId}/room-types', name: 'room_list_room_types', requirements: ['hotelId' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'List room types of a hotel (paginated)',
        tags: ['Room Types'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_OK, description: 'Paginated room type catalogue'),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $hotelId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ListRoomTypesRequest $request = new ListRoomTypesRequest(),
    ): Response {
        $page = $this->queryBus->ask(new ListRoomTypesQuery($hotelId, $request->page, $request->limit));

        return new JsonResponse($this->serializer->serialize($page, $request->page, $request->limit));
    }
}
```

- [ ] **Step 8: Run functional tests to confirm they pass**

```bash
make functional-tests
```
Expected: all RegisterRoomType, GetRoomType, ListRoomTypes controller tests PASS.

- [ ] **Step 9: Commit**

```bash
git add src/Room/UI/Http/Controller/RoomTypeSerializer.php \
        src/Room/UI/Http/Controller/RegisterRoomType/ \
        src/Room/UI/Http/Controller/GetRoomType/ \
        src/Room/UI/Http/Controller/ListRoomTypes/ \
        tests/Room/UI/Http/Controller/RegisterRoomType/ \
        tests/Room/UI/Http/Controller/GetRoomType/ \
        tests/Room/UI/Http/Controller/ListRoomTypes/
git commit -m "feat(room): add RegisterRoomType, GetRoomType, and ListRoomTypes HTTP controllers"
```

---

### Task 11: Update + Delete HTTP controllers

**Files:**
- Create: `src/Room/UI/Http/Controller/UpdateRoomType/UpdateRoomTypeRequest.php`
- Create: `src/Room/UI/Http/Controller/UpdateRoomType/UpdateRoomTypeController.php`
- Create: `src/Room/UI/Http/Controller/DeleteRoomType/DeleteRoomTypeController.php`
- Test: `tests/Room/UI/Http/Controller/UpdateRoomType/UpdateRoomTypeControllerTest.php`
- Test: `tests/Room/UI/Http/Controller/DeleteRoomType/DeleteRoomTypeControllerTest.php`

- [ ] **Step 1: Write failing functional tests**

```php
<?php
// tests/Room/UI/Http/Controller/UpdateRoomType/UpdateRoomTypeControllerTest.php
declare(strict_types=1);
namespace App\Tests\Room\UI\Http\Controller\UpdateRoomType;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class UpdateRoomTypeControllerTest extends WebTestCase
{
    private const array HOTEL_PAYLOAD = ['name' => 'Hotel Test', 'streetAddress' => '1 rue de la Paix', 'postalCode' => '75001', 'city' => 'Paris', 'country' => 'FR'];
    private const array INITIAL_PAYLOAD = ['name' => 'Single', 'livingSpaceCount' => 1, 'guestCapacity' => 1, 'isAccessible' => false, 'bedComposition' => [['type' => 'single', 'count' => 1]]];

    #[Test]
    public function itUpdatesAndReturns200(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId, self::INITIAL_PAYLOAD);

        $updatePayload = ['name' => 'Double', 'livingSpaceCount' => 1, 'surfaceM2' => 25, 'guestCapacity' => 2, 'isAccessible' => true, 'bedComposition' => [['type' => 'double', 'count' => 1]]];

        $client->request('PUT', "/api/v1/hotels/{$hotelId}/room-types/{$roomTypeId}", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($updatePayload, \JSON_THROW_ON_ERROR));

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        /** @var array{name: string, surfaceM2: int, guestCapacity: int, isAccessible: bool} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Double', $body['name']);
        self::assertSame(25, $body['surfaceM2']);
        self::assertSame(2, $body['guestCapacity']);
        self::assertTrue($body['isAccessible']);
    }

    #[Test]
    public function itReturns404WhenNotFound(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request('PUT', "/api/v1/hotels/{$hotelId}/room-types/00000000-0000-4000-8000-000000000000", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::INITIAL_PAYLOAD, \JSON_THROW_ON_ERROR));

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns409WhenNewNameAlreadyTaken(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId, self::INITIAL_PAYLOAD);
        $this->registerRoomTypeAndGetId($client, $hotelId, array_merge(self::INITIAL_PAYLOAD, ['name' => 'Double']));

        $client->request('PUT', "/api/v1/hotels/{$hotelId}/room-types/{$roomTypeId}", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(array_merge(self::INITIAL_PAYLOAD, ['name' => 'Double']), \JSON_THROW_ON_ERROR));

        self::assertSame(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode());
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request('POST', '/api/v1/hotels', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    private function registerRoomTypeAndGetId(KernelBrowser $client, string $hotelId, array $payload): string
    {
        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}
```

```php
<?php
// tests/Room/UI/Http/Controller/DeleteRoomType/DeleteRoomTypeControllerTest.php
declare(strict_types=1);
namespace App\Tests\Room\UI\Http\Controller\DeleteRoomType;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class DeleteRoomTypeControllerTest extends WebTestCase
{
    private const array HOTEL_PAYLOAD = ['name' => 'Hotel Test', 'streetAddress' => '1 rue de la Paix', 'postalCode' => '75001', 'city' => 'Paris', 'country' => 'FR'];
    private const array ROOM_TYPE_PAYLOAD = ['name' => 'Single', 'livingSpaceCount' => 1, 'guestCapacity' => 1, 'isAccessible' => false, 'bedComposition' => [['type' => 'single', 'count' => 1]]];

    #[Test]
    public function itDeletesAndReturns204(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request('DELETE', "/api/v1/hotels/{$hotelId}/room-types/{$roomTypeId}");

        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->request('GET', "/api/v1/hotels/{$hotelId}/room-types/{$roomTypeId}");
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns404WhenNotFound(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request('DELETE', "/api/v1/hotels/{$hotelId}/room-types/00000000-0000-4000-8000-000000000000");

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request('POST', '/api/v1/hotels', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    private function registerRoomTypeAndGetId(KernelBrowser $client, string $hotelId): string
    {
        $client->request('POST', "/api/v1/hotels/{$hotelId}/room-types", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::ROOM_TYPE_PAYLOAD, \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
make functional-tests
```

- [ ] **Step 3: Create UpdateRoomTypeRequest**

```php
<?php
// src/Room/UI/Http/Controller/UpdateRoomType/UpdateRoomTypeRequest.php
declare(strict_types=1);
namespace App\Room\UI\Http\Controller\UpdateRoomType;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateRoomTypeRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        #[OA\Property(type: 'string', example: 'Suite Royale', maxLength: 100, minLength: 1)]
        public ?string $name = null,

        #[Assert\NotNull]
        #[Assert\Range(min: 1, max: 20)]
        #[OA\Property(type: 'integer', example: 2, minimum: 1, maximum: 20, nullable: false)]
        public ?int $livingSpaceCount = null,

        #[Assert\Range(min: 1, max: 2000)]
        #[OA\Property(type: 'integer', example: 80, minimum: 1, maximum: 2000, nullable: true)]
        public ?int $surfaceM2 = null,

        #[Assert\NotNull]
        #[Assert\Range(min: 1, max: 20)]
        #[OA\Property(type: 'integer', example: 2, minimum: 1, maximum: 20, nullable: false)]
        public ?int $guestCapacity = null,

        #[Assert\NotNull]
        #[OA\Property(type: 'boolean', example: false, nullable: false)]
        public ?bool $isAccessible = null,

        #[Assert\NotNull]
        #[Assert\Count(min: 1)]
        #[Assert\All([
            new Assert\Collection([
                'type' => [
                    new Assert\NotBlank(),
                    new Assert\Choice(choices: ['single', 'double', 'queen', 'king', 'bunk', 'sofa_bed', 'baby_cot']),
                ],
                'count' => [
                    new Assert\NotNull(),
                    new Assert\Type('integer'),
                    new Assert\Range(min: 1, max: 10),
                ],
            ]),
        ])]
        #[OA\Property(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['single', 'double', 'queen', 'king', 'bunk', 'sofa_bed', 'baby_cot']),
                    new OA\Property(property: 'count', type: 'integer', minimum: 1, maximum: 10),
                ],
                type: 'object',
            ),
        )]
        public ?array $bedComposition = null,
    ) {
    }
}
```

- [ ] **Step 4: Create UpdateRoomTypeController**

```php
<?php
// src/Room/UI/Http/Controller/UpdateRoomType/UpdateRoomTypeController.php
declare(strict_types=1);
namespace App\Room\UI\Http\Controller\UpdateRoomType;

use App\Room\Application\UseCase\GetRoomType\GetRoomTypeQuery;
use App\Room\Application\UseCase\UpdateRoomType\UpdateRoomTypeCommand;
use App\Room\UI\Http\Controller\RoomTypeSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class UpdateRoomTypeController
{
    public function __construct(
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private RoomTypeSerializer $roomTypeSerializer,
    ) {
    }

    #[Route('/hotels/{hotelId}/room-types/{roomTypeId}', name: 'room_update_room_type', requirements: ['hotelId' => Requirement::UUID_V4, 'roomTypeId' => Requirement::UUID_V4], methods: ['PUT'])]
    #[OA\Put(
        summary: 'Update a room type',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: UpdateRoomTypeRequest::class)),
        ),
        tags: ['Room Types'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'roomTypeId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_OK, description: 'Room type updated'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Room type not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Room type name already exists', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'))),
        ],
    )]
    public function __invoke(
        string $hotelId,
        string $roomTypeId,
        #[MapRequestPayload(acceptFormat: 'json')] UpdateRoomTypeRequest $request,
    ): Response {
        $this->commandBus->execute(new UpdateRoomTypeCommand(
            id: $roomTypeId,
            name: $request->name ?? throw new \LogicException('name is required'),
            livingSpaceCount: $request->livingSpaceCount ?? throw new \LogicException('livingSpaceCount is required'),
            surfaceM2: $request->surfaceM2,
            guestCapacity: $request->guestCapacity ?? throw new \LogicException('guestCapacity is required'),
            isAccessible: $request->isAccessible ?? throw new \LogicException('isAccessible is required'),
            bedEntries: $request->bedComposition ?? throw new \LogicException('bedComposition is required'),
        ));

        $roomType = $this->queryBus->ask(new GetRoomTypeQuery($roomTypeId));
        if (null === $roomType) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->roomTypeSerializer->serialize($roomType));
    }
}
```

- [ ] **Step 5: Create DeleteRoomTypeController**

```php
<?php
// src/Room/UI/Http/Controller/DeleteRoomType/DeleteRoomTypeController.php
declare(strict_types=1);
namespace App\Room\UI\Http\Controller\DeleteRoomType;

use App\Room\Application\UseCase\DeleteRoomType\DeleteRoomTypeCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class DeleteRoomTypeController
{
    public function __construct(private SyncCommandBusInterface $commandBus) {}

    #[Route('/hotels/{hotelId}/room-types/{roomTypeId}', name: 'room_delete_room_type', requirements: ['hotelId' => Requirement::UUID_V4, 'roomTypeId' => Requirement::UUID_V4], methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Delete a room type',
        tags: ['Room Types'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'roomTypeId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Room type deleted'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Room type not found', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Room type has rooms assigned', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(string $hotelId, string $roomTypeId): Response
    {
        $this->commandBus->execute(new DeleteRoomTypeCommand($roomTypeId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 6: Run all functional tests**

```bash
make functional-tests
```
Expected: all controller tests PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Room/UI/Http/Controller/UpdateRoomType/ \
        src/Room/UI/Http/Controller/DeleteRoomType/ \
        tests/Room/UI/Http/Controller/UpdateRoomType/ \
        tests/Room/UI/Http/Controller/DeleteRoomType/
git commit -m "feat(room): add UpdateRoomType and DeleteRoomType HTTP controllers"
```

---

### Task 12: Finalize — openapi + lint

- [ ] **Step 1: Run full test suite**

```bash
make tests
```
Expected: all unit, integration, and functional tests PASS.

- [ ] **Step 2: Run linter**

```bash
make lint
```
Fix any CS Fixer or deptrac violations before continuing.

- [ ] **Step 3: Regenerate OpenAPI spec**

```bash
make openapi
```

- [ ] **Step 4: Commit**

```bash
git add public/api/openapi.yaml
git commit -m "docs(openapi): add Room Type endpoints"
```

---

**Plan A complete.** Plan B (`2026-05-25-room-types-plan-b.md`) covers binding Room → RoomType: adding `roomTypeId` to the Room model, migration, and updating all RegisterRoom / BatchRegisterRooms code.


# Search — Migration & Domain Events Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create the 3-table Search read model schema and add the 8 domain events dispatched from Hotel and Room command handlers (no Search listeners in this plan).

**Architecture:** Domain events are plain `readonly` classes in `Shared\Domain\Event`, dispatched via `Psr\EventDispatcher\EventDispatcherInterface` injected into existing command handlers. The Doctrine migration creates 3 tables (`search_hotel_room_types`, `search_room_index`, `search_unavailable_periods`) that will be populated by Search projectors in a future plan.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine Migrations, PHPUnit (unit tests with `TestCase` + mocks)

---

## File Map

**New files:**
- `migrations/Version20260531100000.php` — 3 Search tables
- `src/Shared/Domain/Event/HotelRegistered.php`
- `src/Shared/Domain/Event/StarRatingClassified.php`
- `src/Shared/Domain/Event/HotelAmenityDeclared.php`
- `src/Shared/Domain/Event/RoomTypeRegistered.php`
- `src/Shared/Domain/Event/RoomTypeUpdated.php`
- `src/Shared/Domain/Event/RoomTypeAmenityDeclared.php`
- `src/Shared/Domain/Event/RoomTypeDeleted.php`
- `src/Shared/Domain/Event/RoomRegistered.php`
- `tests/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandlerTest.php`
- `tests/Hotel/Application/UseCase/ClassifyHotel/ClassifyHotelCommandHandlerTest.php`
- `tests/Hotel/Application/UseCase/DeclareHotelAmenities/DeclareHotelAmenitiesCommandHandlerTest.php`
- `tests/Room/Application/UseCase/RegisterRoomType/RegisterRoomTypeCommandHandlerTest.php`
- `tests/Room/Application/UseCase/UpdateRoomType/UpdateRoomTypeCommandHandlerTest.php`
- `tests/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommandHandlerTest.php`
- `tests/Room/Application/UseCase/DeleteRoomType/DeleteRoomTypeCommandHandlerTest.php`
- `tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php`

**Modified files:**
- `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandler.php` — inject `EventDispatcherInterface`, dispatch `HotelRegistered`
- `src/Hotel/Application/UseCase/ClassifyHotel/ClassifyHotelCommandHandler.php` — dispatch `StarRatingClassified`
- `src/Hotel/Application/UseCase/DeclareHotelAmenities/DeclareHotelAmenitiesCommandHandler.php` — dispatch `HotelAmenityDeclared`
- `src/Room/Application/UseCase/RegisterRoomType/RegisterRoomTypeCommandHandler.php` — dispatch `RoomTypeRegistered`
- `src/Room/Application/UseCase/UpdateRoomType/UpdateRoomTypeCommandHandler.php` — dispatch `RoomTypeUpdated`
- `src/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommandHandler.php` — dispatch `RoomTypeAmenityDeclared`
- `src/Room/Application/UseCase/DeleteRoomType/DeleteRoomTypeCommandHandler.php` — dispatch `RoomTypeDeleted`
- `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php` — dispatch `RoomRegistered`

---

### Task 1: Doctrine migration — 3 Search tables

**Files:**
- Create: `migrations/Version20260531100000.php`

- [ ] **Step 1: Create the migration file**

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Search context schema and read model tables (search.hotel_room_types, search.room_index, search.unavailable_periods)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS search');

        $this->addSql(<<<'SQL'
            CREATE TABLE search.hotel_room_types (
                room_type_id  UUID        NOT NULL,
                hotel_id      UUID        NOT NULL,
                hotel_name    VARCHAR(255) NOT NULL,
                city          VARCHAR(255) NOT NULL,
                country       VARCHAR(255) NOT NULL,
                star_rating   SMALLINT    NULL,
                hotel_amenities  JSONB    NOT NULL DEFAULT '[]',
                room_type_name   VARCHAR(255) NOT NULL,
                guest_capacity   SMALLINT NOT NULL,
                bed_composition  JSONB    NOT NULL,
                room_amenities   JSONB    NOT NULL DEFAULT '[]',
                base_price_cents INT      NULL,
                PRIMARY KEY (room_type_id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE search.room_index (
                room_id      UUID NOT NULL,
                room_type_id UUID NOT NULL,
                hotel_id     UUID NOT NULL,
                PRIMARY KEY (room_id),
                CONSTRAINT fk_search_room_index_room_type
                    FOREIGN KEY (room_type_id)
                    REFERENCES search.hotel_room_types (room_type_id)
                    ON DELETE CASCADE
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE search.unavailable_periods (
                id           UUID      NOT NULL,
                room_id      UUID      NOT NULL,
                room_type_id UUID      NOT NULL,
                hotel_id     UUID      NOT NULL,
                period       DATERANGE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_search_unavailable_periods_room
                    FOREIGN KEY (room_id)
                    REFERENCES search.room_index (room_id)
                    ON DELETE CASCADE
            )
        SQL);

        $this->addSql('CREATE INDEX idx_search_unavailable_periods_period ON search.unavailable_periods USING GiST (period)');
        $this->addSql('CREATE INDEX idx_search_room_index_room_type ON search.room_index (room_type_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS search.unavailable_periods');
        $this->addSql('DROP TABLE IF EXISTS search.room_index');
        $this->addSql('DROP TABLE IF EXISTS search.hotel_room_types');
        $this->addSql('DROP SCHEMA IF EXISTS search');
    }
}
```

- [ ] **Step 2: Run the migration**

```bash
make migrate
```

Expected: `[OK] Successfully executed 1 migrations.`

- [ ] **Step 3: Verify the tables exist**

```bash
docker compose exec postgres psql -U bookit -c "\dt search.*"
```

Expected: 3 rows — `search.hotel_room_types`, `search.room_index`, `search.unavailable_periods`.

- [ ] **Step 4: Commit**

```bash
git add migrations/Version20260531100000.php
git commit -m "feat(search): add Search read model tables (hotel_room_types, room_index, unavailable_periods)"
```

---

### Task 2: Hotel event classes

**Files:**
- Create: `src/Shared/Domain/Event/HotelRegistered.php`
- Create: `src/Shared/Domain/Event/StarRatingClassified.php`
- Create: `src/Shared/Domain/Event/HotelAmenityDeclared.php`

- [ ] **Step 1: Create `HotelRegistered`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class HotelRegistered
{
    public function __construct(
        public string $hotelId,
        public string $name,
        public string $city,
        public string $country,
        public ?int $starRating,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 2: Create `StarRatingClassified`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class StarRatingClassified
{
    public function __construct(
        public string $hotelId,
        public ?int $starRating,
    ) {
    }
}
```

- [ ] **Step 3: Create `HotelAmenityDeclared`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class HotelAmenityDeclared
{
    /** @param string[] $amenities */
    public function __construct(
        public string $hotelId,
        public array $amenities,
    ) {
    }
}
```

- [ ] **Step 4: Run lint**

```bash
make lint
```

Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add src/Shared/Domain/Event/HotelRegistered.php \
        src/Shared/Domain/Event/StarRatingClassified.php \
        src/Shared/Domain/Event/HotelAmenityDeclared.php
git commit -m "feat(search): add Hotel domain events (HotelRegistered, StarRatingClassified, HotelAmenityDeclared)"
```

---

### Task 3: RegisterHotelCommandHandler — dispatch HotelRegistered

**Files:**
- Create: `tests/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandlerTest.php`
- Modify: `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandler.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommand;
use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommandHandler;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Domain\Event\HotelRegistered;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class RegisterHotelCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesHotelRegisteredOnSuccess(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(false);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof HotelRegistered
                    && $event->hotelId === 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'
                    && $event->name === 'Le Grand Hôtel'
                    && $event->city === 'Paris'
                    && $event->country === 'FR'
                    && $event->starRating === null;
            }));

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher);

        ($handler)(new RegisterHotelCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            name: 'Le Grand Hôtel',
            address: new Address('1 rue de la Paix', '75001', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        ));
    }

    #[Test]
    public function itDispatchesStarRatingWhenProvided(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(false);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof HotelRegistered && $event->starRating === 4;
            }));

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher);

        ($handler)(new RegisterHotelCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a12',
            name: 'Luxury Palace',
            address: new Address('5 avenue Foch', '75016', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            starRating: new \App\Hotel\Domain\ValueObject\StarRating(4, false),
        ));
    }

    #[Test]
    public function itDoesNotDispatchWhenHotelAlreadyExists(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(true);

        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher);

        $this->expectException(\App\Hotel\Domain\Exception\HotelAlreadyExistsException::class);

        ($handler)(new RegisterHotelCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a13',
            name: 'Le Grand Hôtel',
            address: new Address('1 rue de la Paix', '75001', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        ));
    }
}
```

- [ ] **Step 2: Run the test — expect FAIL**

```bash
make unit-test
```

Expected: FAIL — `RegisterHotelCommandHandler::__construct()` doesn't accept `EventDispatcherInterface`.

- [ ] **Step 3: Modify the handler**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Domain\Exception\HotelAlreadyExistsException;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\HotelRegistered;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class RegisterHotelCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private HotelRepositoryInterface $hotelRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(RegisterHotelCommand $command): void
    {
        if ($this->hotelRepository->existsByNameAndAddress($command->name, $command->address)) {
            throw new HotelAlreadyExistsException($command->name, $command->address->city);
        }

        $hotel = new Hotel($command->id, $command->name, $command->address, $command->createdAt, $command->starRating);

        $this->hotelRepository->add($hotel);

        $this->eventDispatcher->dispatch(new HotelRegistered(
            hotelId: $hotel->id,
            name: $hotel->name,
            city: $hotel->address->city,
            country: $hotel->address->country,
            starRating: $hotel->starRating?->stars,
            createdAt: $hotel->createdAt,
        ));
    }
}
```

- [ ] **Step 4: Run the test — expect PASS**

```bash
make unit-test
```

Expected: all tests PASS.

- [ ] **Step 5: Run lint**

```bash
make lint
```

Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandler.php \
        tests/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandlerTest.php
git commit -m "feat(hotel): dispatch HotelRegistered from RegisterHotelCommandHandler"
```

---

### Task 4: ClassifyHotelCommandHandler — dispatch StarRatingClassified

**Files:**
- Create: `tests/Hotel/Application/UseCase/ClassifyHotel/ClassifyHotelCommandHandlerTest.php`
- Modify: `src/Hotel/Application/UseCase/ClassifyHotel/ClassifyHotelCommandHandler.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\ClassifyHotel;

use App\Hotel\Application\UseCase\ClassifyHotel\ClassifyHotelCommand;
use App\Hotel\Application\UseCase\ClassifyHotel\ClassifyHotelCommandHandler;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Domain\Event\StarRatingClassified;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class ClassifyHotelCommandHandlerTest extends TestCase
{
    private Hotel $hotel;

    protected function setUp(): void
    {
        $this->hotel = new Hotel(
            id: 'hotel-id-1',
            name: 'Le Grand Hôtel',
            address: new Address('1 rue de la Paix', '75001', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    #[Test]
    public function itDispatchesStarRatingClassifiedWithStars(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('get')->willReturn($this->hotel);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof StarRatingClassified
                    && $event->hotelId === 'hotel-id-1'
                    && $event->starRating === 4;
            }));

        $handler = new ClassifyHotelCommandHandler($repository, $dispatcher);

        ($handler)(new ClassifyHotelCommand(hotelId: 'hotel-id-1', stars: 4, superior: false));
    }

    #[Test]
    public function itDispatchesNullStarRatingWhenRatingRemoved(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('get')->willReturn($this->hotel);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof StarRatingClassified
                    && $event->hotelId === 'hotel-id-1'
                    && $event->starRating === null;
            }));

        $handler = new ClassifyHotelCommandHandler($repository, $dispatcher);

        ($handler)(new ClassifyHotelCommand(hotelId: 'hotel-id-1', stars: null));
    }

    #[Test]
    public function itDoesNotDispatchWhenHotelNotFound(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('get')->willReturn(null);
        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new ClassifyHotelCommandHandler($repository, $dispatcher);

        $this->expectException(\App\Hotel\Domain\Exception\HotelNotFoundException::class);

        ($handler)(new ClassifyHotelCommand(hotelId: 'missing-id', stars: 3));
    }
}
```

- [ ] **Step 2: Run the test — expect FAIL**

```bash
make unit-test
```

Expected: FAIL — handler doesn't accept `EventDispatcherInterface`.

- [ ] **Step 3: Modify the handler**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\ClassifyHotel;

use App\Hotel\Domain\Exception\HotelNotFoundException;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\StarRatingClassified;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class ClassifyHotelCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private HotelRepositoryInterface $hotelRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ClassifyHotelCommand $command): void
    {
        $hotel = $this->hotelRepository->get($command->hotelId);

        if (null === $hotel) {
            throw new HotelNotFoundException($command->hotelId);
        }

        $starRating = null !== $command->stars
            ? new StarRating($command->stars, $command->superior)
            : null;

        $this->hotelRepository->save($hotel->withStarRating($starRating));

        $this->eventDispatcher->dispatch(new StarRatingClassified(
            hotelId: $command->hotelId,
            starRating: $command->stars,
        ));
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
make unit-test
```

- [ ] **Step 5: Commit**

```bash
git add src/Hotel/Application/UseCase/ClassifyHotel/ClassifyHotelCommandHandler.php \
        tests/Hotel/Application/UseCase/ClassifyHotel/ClassifyHotelCommandHandlerTest.php
git commit -m "feat(hotel): dispatch StarRatingClassified from ClassifyHotelCommandHandler"
```

---

### Task 5: DeclareHotelAmenitiesCommandHandler — dispatch HotelAmenityDeclared

**Files:**
- Create: `tests/Hotel/Application/UseCase/DeclareHotelAmenities/DeclareHotelAmenitiesCommandHandlerTest.php`
- Modify: `src/Hotel/Application/UseCase/DeclareHotelAmenities/DeclareHotelAmenitiesCommandHandler.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\DeclareHotelAmenities;

use App\Hotel\Application\UseCase\DeclareHotelAmenities\DeclareHotelAmenitiesCommand;
use App\Hotel\Application\UseCase\DeclareHotelAmenities\DeclareHotelAmenitiesCommandHandler;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Domain\Event\HotelAmenityDeclared;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class DeclareHotelAmenitiesCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesHotelAmenityDeclared(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $hotel = new Hotel(
            id: 'hotel-id-1',
            name: 'Le Grand Hôtel',
            address: new Address('1 rue de la Paix', '75001', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $repository->method('get')->willReturn($hotel);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof HotelAmenityDeclared
                    && $event->hotelId === 'hotel-id-1'
                    && $event->amenities === ['pool', 'gym'];
            }));

        $handler = new DeclareHotelAmenitiesCommandHandler($repository, $dispatcher);

        ($handler)(new DeclareHotelAmenitiesCommand(hotelId: 'hotel-id-1', amenities: ['pool', 'gym']));
    }

    #[Test]
    public function itDoesNotDispatchWhenHotelNotFound(): void
    {
        $repository = $this->createMock(HotelRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('get')->willReturn(null);
        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new DeclareHotelAmenitiesCommandHandler($repository, $dispatcher);

        $this->expectException(\App\Hotel\Domain\Exception\HotelNotFoundException::class);

        ($handler)(new DeclareHotelAmenitiesCommand(hotelId: 'missing-id', amenities: ['pool']));
    }
}
```

- [ ] **Step 2: Run the test — expect FAIL**

```bash
make unit-test
```

- [ ] **Step 3: Modify the handler**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\DeclareHotelAmenities;

use App\Hotel\Domain\Exception\HotelNotFoundException;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\HotelAmenityDeclared;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class DeclareHotelAmenitiesCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private HotelRepositoryInterface $hotelRepository,
        private EventDispatcherInterface $eventDispatcher,
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

        $this->eventDispatcher->dispatch(new HotelAmenityDeclared(
            hotelId: $command->hotelId,
            amenities: $command->amenities,
        ));
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
make unit-test
```

- [ ] **Step 5: Commit**

```bash
git add src/Hotel/Application/UseCase/DeclareHotelAmenities/DeclareHotelAmenitiesCommandHandler.php \
        tests/Hotel/Application/UseCase/DeclareHotelAmenities/DeclareHotelAmenitiesCommandHandlerTest.php
git commit -m "feat(hotel): dispatch HotelAmenityDeclared from DeclareHotelAmenitiesCommandHandler"
```

---

### Task 6: Room event classes

**Files:**
- Create: `src/Shared/Domain/Event/RoomTypeRegistered.php`
- Create: `src/Shared/Domain/Event/RoomTypeUpdated.php`
- Create: `src/Shared/Domain/Event/RoomTypeAmenityDeclared.php`
- Create: `src/Shared/Domain/Event/RoomTypeDeleted.php`
- Create: `src/Shared/Domain/Event/RoomRegistered.php`

- [ ] **Step 1: Create `RoomTypeRegistered`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class RoomTypeRegistered
{
    /** @param list<array{type: string, count: int}> $bedComposition */
    public function __construct(
        public string $roomTypeId,
        public string $hotelId,
        public string $name,
        public int $guestCapacity,
        public array $bedComposition,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 2: Create `RoomTypeUpdated`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class RoomTypeUpdated
{
    /** @param list<array{type: string, count: int}> $bedComposition */
    public function __construct(
        public string $roomTypeId,
        public string $hotelId,
        public string $name,
        public int $guestCapacity,
        public array $bedComposition,
    ) {
    }
}
```

- [ ] **Step 3: Create `RoomTypeAmenityDeclared`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class RoomTypeAmenityDeclared
{
    /** @param string[] $amenities */
    public function __construct(
        public string $roomTypeId,
        public string $hotelId,
        public array $amenities,
    ) {
    }
}
```

- [ ] **Step 4: Create `RoomTypeDeleted`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class RoomTypeDeleted
{
    public function __construct(
        public string $roomTypeId,
        public string $hotelId,
    ) {
    }
}
```

- [ ] **Step 5: Create `RoomRegistered`**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class RoomRegistered
{
    public function __construct(
        public string $roomId,
        public string $hotelId,
        public string $roomTypeId,
    ) {
    }
}
```

- [ ] **Step 6: Run lint**

```bash
make lint
```

- [ ] **Step 7: Commit**

```bash
git add src/Shared/Domain/Event/RoomTypeRegistered.php \
        src/Shared/Domain/Event/RoomTypeUpdated.php \
        src/Shared/Domain/Event/RoomTypeAmenityDeclared.php \
        src/Shared/Domain/Event/RoomTypeDeleted.php \
        src/Shared/Domain/Event/RoomRegistered.php
git commit -m "feat(search): add Room domain events (RoomTypeRegistered, RoomTypeUpdated, RoomTypeAmenityDeclared, RoomTypeDeleted, RoomRegistered)"
```

---

### Task 7: RegisterRoomTypeCommandHandler — dispatch RoomTypeRegistered

**Files:**
- Create: `tests/Room/Application/UseCase/RegisterRoomType/RegisterRoomTypeCommandHandlerTest.php`
- Modify: `src/Room/Application/UseCase/RegisterRoomType/RegisterRoomTypeCommandHandler.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\RegisterRoomType;

use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommand;
use App\Room\Application\UseCase\RegisterRoomType\RegisterRoomTypeCommandHandler;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Shared\Domain\Event\RoomTypeRegistered;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class RegisterRoomTypeCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesRoomTypeRegistered(): void
    {
        $roomTypeRepository = $this->createMock(RoomTypeRepositoryInterface::class);
        $hotelExists = $this->createMock(HotelExistsInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $hotelExists->method('exists')->willReturn(true);
        $roomTypeRepository->method('existsByHotelIdAndName')->willReturn(false);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof RoomTypeRegistered
                    && $event->roomTypeId === 'rt-id-1'
                    && $event->hotelId === 'hotel-id-1'
                    && $event->name === 'Standard'
                    && $event->guestCapacity === 2
                    && $event->bedComposition === [['type' => 'double', 'count' => 1]];
            }));

        $handler = new RegisterRoomTypeCommandHandler($roomTypeRepository, $hotelExists, $dispatcher);

        ($handler)(new RegisterRoomTypeCommand(
            id: 'rt-id-1',
            hotelId: 'hotel-id-1',
            name: 'Standard',
            livingSpaceCount: 1,
            surfaceM2: 20,
            guestCapacity: 2,
            isAccessible: false,
            bedEntries: [['type' => 'double', 'count' => 1]],
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        ));
    }

    #[Test]
    public function itDoesNotDispatchWhenHotelNotFound(): void
    {
        $roomTypeRepository = $this->createMock(RoomTypeRepositoryInterface::class);
        $hotelExists = $this->createMock(HotelExistsInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $hotelExists->method('exists')->willReturn(false);
        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new RegisterRoomTypeCommandHandler($roomTypeRepository, $hotelExists, $dispatcher);

        $this->expectException(\App\Room\Domain\Exception\HotelNotFoundException::class);

        ($handler)(new RegisterRoomTypeCommand(
            id: 'rt-id-2',
            hotelId: 'missing-hotel',
            name: 'Standard',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 2,
            isAccessible: false,
            bedEntries: [['type' => 'double', 'count' => 1]],
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        ));
    }
}
```

- [ ] **Step 2: Run the test — expect FAIL**

```bash
make unit-test
```

- [ ] **Step 3: Modify the handler**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\RegisterRoomType;

use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomTypeAlreadyExistsException;
use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\BedComposition;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\RoomTypeRegistered;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class RegisterRoomTypeCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomTypeRepositoryInterface $roomTypeRepository,
        private HotelExistsInterface $hotelExists,
        private EventDispatcherInterface $eventDispatcher,
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

        $roomType = new RoomType(
            $command->id,
            $command->hotelId,
            $command->name,
            $command->livingSpaceCount,
            $command->surfaceM2,
            $command->guestCapacity,
            $command->isAccessible,
            BedComposition::fromArray($command->bedEntries),
            $command->createdAt,
        );

        $this->roomTypeRepository->add($roomType);

        $this->eventDispatcher->dispatch(new RoomTypeRegistered(
            roomTypeId: $roomType->id,
            hotelId: $roomType->hotelId,
            name: $roomType->name,
            guestCapacity: $roomType->guestCapacity,
            bedComposition: $roomType->bedComposition->toArray(),
            createdAt: $roomType->createdAt,
        ));
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
make unit-test
```

- [ ] **Step 5: Commit**

```bash
git add src/Room/Application/UseCase/RegisterRoomType/RegisterRoomTypeCommandHandler.php \
        tests/Room/Application/UseCase/RegisterRoomType/RegisterRoomTypeCommandHandlerTest.php
git commit -m "feat(room): dispatch RoomTypeRegistered from RegisterRoomTypeCommandHandler"
```

---

### Task 8: UpdateRoomTypeCommandHandler — dispatch RoomTypeUpdated

**Files:**
- Create: `tests/Room/Application/UseCase/UpdateRoomType/UpdateRoomTypeCommandHandlerTest.php`
- Modify: `src/Room/Application/UseCase/UpdateRoomType/UpdateRoomTypeCommandHandler.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\UpdateRoomType;

use App\Room\Application\UseCase\UpdateRoomType\UpdateRoomTypeCommand;
use App\Room\Application\UseCase\UpdateRoomType\UpdateRoomTypeCommandHandler;
use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\BedComposition;
use App\Room\Domain\ValueObject\BedEntry;
use App\Room\Domain\ValueObject\BedType;
use App\Shared\Domain\Event\RoomTypeUpdated;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class UpdateRoomTypeCommandHandlerTest extends TestCase
{
    private RoomType $existingRoomType;

    protected function setUp(): void
    {
        $this->existingRoomType = new RoomType(
            id: 'rt-id-1',
            hotelId: 'hotel-id-1',
            name: 'Standard',
            livingSpaceCount: 1,
            surfaceM2: 20,
            guestCapacity: 2,
            isAccessible: false,
            bedComposition: new BedComposition([new BedEntry(BedType::Double, 1)]),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    #[Test]
    public function itDispatchesRoomTypeUpdated(): void
    {
        $repository = $this->createMock(RoomTypeRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('get')->willReturn($this->existingRoomType);
        $repository->method('existsByHotelIdAndName')->willReturn(false);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof RoomTypeUpdated
                    && $event->roomTypeId === 'rt-id-1'
                    && $event->hotelId === 'hotel-id-1'
                    && $event->name === 'Standard Plus'
                    && $event->guestCapacity === 3
                    && $event->bedComposition === [['type' => 'king', 'count' => 1]];
            }));

        $handler = new UpdateRoomTypeCommandHandler($repository, $dispatcher);

        ($handler)(new UpdateRoomTypeCommand(
            id: 'rt-id-1',
            name: 'Standard Plus',
            livingSpaceCount: 1,
            surfaceM2: 25,
            guestCapacity: 3,
            isAccessible: false,
            bedEntries: [['type' => 'king', 'count' => 1]],
        ));
    }

    #[Test]
    public function itDoesNotDispatchWhenRoomTypeNotFound(): void
    {
        $repository = $this->createMock(RoomTypeRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('get')->willReturn(null);
        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new UpdateRoomTypeCommandHandler($repository, $dispatcher);

        $this->expectException(\App\Room\Domain\Exception\RoomTypeNotFoundException::class);

        ($handler)(new UpdateRoomTypeCommand(
            id: 'missing-id',
            name: 'Standard',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 2,
            isAccessible: false,
            bedEntries: [['type' => 'double', 'count' => 1]],
        ));
    }
}
```

- [ ] **Step 2: Run the test — expect FAIL**

```bash
make unit-test
```

- [ ] **Step 3: Modify the handler**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\UpdateRoomType;

use App\Room\Domain\Exception\RoomTypeAlreadyExistsException;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\BedComposition;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\RoomTypeUpdated;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class UpdateRoomTypeCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomTypeRepositoryInterface $roomTypeRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

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

        $this->eventDispatcher->dispatch(new RoomTypeUpdated(
            roomTypeId: $roomType->id,
            hotelId: $roomType->hotelId,
            name: $command->name,
            guestCapacity: $command->guestCapacity,
            bedComposition: $command->bedEntries,
        ));
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
make unit-test
```

- [ ] **Step 5: Commit**

```bash
git add src/Room/Application/UseCase/UpdateRoomType/UpdateRoomTypeCommandHandler.php \
        tests/Room/Application/UseCase/UpdateRoomType/UpdateRoomTypeCommandHandlerTest.php
git commit -m "feat(room): dispatch RoomTypeUpdated from UpdateRoomTypeCommandHandler"
```

---

### Task 9: DeclareRoomTypeAmenitiesCommandHandler — dispatch RoomTypeAmenityDeclared

**Files:**
- Create: `tests/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommandHandlerTest.php`
- Modify: `src/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommandHandler.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\DeclareRoomTypeAmenities;

use App\Room\Application\UseCase\DeclareRoomTypeAmenities\DeclareRoomTypeAmenitiesCommand;
use App\Room\Application\UseCase\DeclareRoomTypeAmenities\DeclareRoomTypeAmenitiesCommandHandler;
use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\BedComposition;
use App\Room\Domain\ValueObject\BedEntry;
use App\Room\Domain\ValueObject\BedType;
use App\Shared\Domain\Event\RoomTypeAmenityDeclared;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class DeclareRoomTypeAmenitiesCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesRoomTypeAmenityDeclared(): void
    {
        $repository = $this->createMock(RoomTypeRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $roomType = new RoomType(
            id: 'rt-id-1',
            hotelId: 'hotel-id-1',
            name: 'Standard',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 2,
            isAccessible: false,
            bedComposition: new BedComposition([new BedEntry(BedType::Double, 1)]),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $repository->method('get')->willReturn($roomType);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof RoomTypeAmenityDeclared
                    && $event->roomTypeId === 'rt-id-1'
                    && $event->hotelId === 'hotel-id-1'
                    && $event->amenities === ['wifi', 'tv'];
            }));

        $handler = new DeclareRoomTypeAmenitiesCommandHandler($repository, $dispatcher);

        ($handler)(new DeclareRoomTypeAmenitiesCommand(roomTypeId: 'rt-id-1', amenities: ['wifi', 'tv']));
    }

    #[Test]
    public function itDoesNotDispatchWhenRoomTypeNotFound(): void
    {
        $repository = $this->createMock(RoomTypeRepositoryInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('get')->willReturn(null);
        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new DeclareRoomTypeAmenitiesCommandHandler($repository, $dispatcher);

        $this->expectException(\App\Room\Domain\Exception\RoomTypeNotFoundException::class);

        ($handler)(new DeclareRoomTypeAmenitiesCommand(roomTypeId: 'missing-id', amenities: ['wifi']));
    }
}
```

- [ ] **Step 2: Run the test — expect FAIL**

```bash
make unit-test
```

- [ ] **Step 3: Modify the handler**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\DeclareRoomTypeAmenities;

use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\RoomAmenity;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\RoomTypeAmenityDeclared;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class DeclareRoomTypeAmenitiesCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomTypeRepositoryInterface $roomTypeRepository,
        private EventDispatcherInterface $eventDispatcher,
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

        $this->eventDispatcher->dispatch(new RoomTypeAmenityDeclared(
            roomTypeId: $command->roomTypeId,
            hotelId: $roomType->hotelId,
            amenities: $command->amenities,
        ));
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
make unit-test
```

- [ ] **Step 5: Commit**

```bash
git add src/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommandHandler.php \
        tests/Room/Application/UseCase/DeclareRoomTypeAmenities/DeclareRoomTypeAmenitiesCommandHandlerTest.php
git commit -m "feat(room): dispatch RoomTypeAmenityDeclared from DeclareRoomTypeAmenitiesCommandHandler"
```

---

### Task 10: DeleteRoomTypeCommandHandler — dispatch RoomTypeDeleted

**Files:**
- Create: `tests/Room/Application/UseCase/DeleteRoomType/DeleteRoomTypeCommandHandlerTest.php`
- Modify: `src/Room/Application/UseCase/DeleteRoomType/DeleteRoomTypeCommandHandler.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\DeleteRoomType;

use App\Room\Application\UseCase\DeleteRoomType\DeleteRoomTypeCommand;
use App\Room\Application\UseCase\DeleteRoomType\DeleteRoomTypeCommandHandler;
use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Port\RoomTypeHasRoomsInterface;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\BedComposition;
use App\Room\Domain\ValueObject\BedEntry;
use App\Room\Domain\ValueObject\BedType;
use App\Shared\Domain\Event\RoomTypeDeleted;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class DeleteRoomTypeCommandHandlerTest extends TestCase
{
    private RoomType $roomType;

    protected function setUp(): void
    {
        $this->roomType = new RoomType(
            id: 'rt-id-1',
            hotelId: 'hotel-id-1',
            name: 'Standard',
            livingSpaceCount: 1,
            surfaceM2: null,
            guestCapacity: 2,
            isAccessible: false,
            bedComposition: new BedComposition([new BedEntry(BedType::Double, 1)]),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    #[Test]
    public function itDispatchesRoomTypeDeleted(): void
    {
        $repository = $this->createMock(RoomTypeRepositoryInterface::class);
        $hasRooms = $this->createMock(RoomTypeHasRoomsInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('get')->willReturn($this->roomType);
        $hasRooms->method('hasRooms')->willReturn(false);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof RoomTypeDeleted
                    && $event->roomTypeId === 'rt-id-1'
                    && $event->hotelId === 'hotel-id-1';
            }));

        $handler = new DeleteRoomTypeCommandHandler($repository, $hasRooms, $dispatcher);

        ($handler)(new DeleteRoomTypeCommand(id: 'rt-id-1'));
    }

    #[Test]
    public function itDoesNotDispatchWhenRoomTypeHasRooms(): void
    {
        $repository = $this->createMock(RoomTypeRepositoryInterface::class);
        $hasRooms = $this->createMock(RoomTypeHasRoomsInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->method('get')->willReturn($this->roomType);
        $hasRooms->method('hasRooms')->willReturn(true);
        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new DeleteRoomTypeCommandHandler($repository, $hasRooms, $dispatcher);

        $this->expectException(\App\Room\Domain\Exception\RoomTypeHasRoomsException::class);

        ($handler)(new DeleteRoomTypeCommand(id: 'rt-id-1'));
    }
}
```

- [ ] **Step 2: Run the test — expect FAIL**

```bash
make unit-test
```

- [ ] **Step 3: Modify the handler**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\DeleteRoomType;

use App\Room\Domain\Exception\RoomTypeHasRoomsException;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Room\Domain\Port\RoomTypeHasRoomsInterface;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\RoomTypeDeleted;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class DeleteRoomTypeCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomTypeRepositoryInterface $roomTypeRepository,
        private RoomTypeHasRoomsInterface $roomTypeHasRooms,
        private EventDispatcherInterface $eventDispatcher,
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

        $this->eventDispatcher->dispatch(new RoomTypeDeleted(
            roomTypeId: $roomType->id,
            hotelId: $roomType->hotelId,
        ));
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
make unit-test
```

- [ ] **Step 5: Commit**

```bash
git add src/Room/Application/UseCase/DeleteRoomType/DeleteRoomTypeCommandHandler.php \
        tests/Room/Application/UseCase/DeleteRoomType/DeleteRoomTypeCommandHandlerTest.php
git commit -m "feat(room): dispatch RoomTypeDeleted from DeleteRoomTypeCommandHandler"
```

---

### Task 11: RegisterRoomCommandHandler — dispatch RoomRegistered

**Files:**
- Create: `tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php`
- Modify: `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\RegisterRoom;

use App\Room\Application\UseCase\RegisterRoom\RegisterRoomCommand;
use App\Room\Application\UseCase\RegisterRoom\RegisterRoomCommandHandler;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Room\Domain\Port\RoomTypeExistsInterface;
use App\Shared\Domain\Event\RoomRegistered;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class RegisterRoomCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesRoomRegistered(): void
    {
        $roomRepository = $this->createMock(RoomRepositoryInterface::class);
        $hotelExists = $this->createMock(HotelExistsInterface::class);
        $roomTypeExists = $this->createMock(RoomTypeExistsInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $hotelExists->method('exists')->willReturn(true);
        $roomTypeExists->method('exists')->willReturn(true);
        $roomRepository->method('existsByHotelIdAndNumber')->willReturn(false);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof RoomRegistered
                    && $event->roomId === 'room-id-1'
                    && $event->hotelId === 'hotel-id-1'
                    && $event->roomTypeId === 'rt-id-1';
            }));

        $handler = new RegisterRoomCommandHandler($roomRepository, $hotelExists, $roomTypeExists, $dispatcher);

        ($handler)(new RegisterRoomCommand(
            id: 'room-id-1',
            hotelId: 'hotel-id-1',
            number: '101',
            floor: 1,
            roomTypeId: 'rt-id-1',
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        ));
    }

    #[Test]
    public function itDoesNotDispatchWhenRoomAlreadyExists(): void
    {
        $roomRepository = $this->createMock(RoomRepositoryInterface::class);
        $hotelExists = $this->createMock(HotelExistsInterface::class);
        $roomTypeExists = $this->createMock(RoomTypeExistsInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $hotelExists->method('exists')->willReturn(true);
        $roomTypeExists->method('exists')->willReturn(true);
        $roomRepository->method('existsByHotelIdAndNumber')->willReturn(true);
        $dispatcher->expects($this->never())->method('dispatch');

        $handler = new RegisterRoomCommandHandler($roomRepository, $hotelExists, $roomTypeExists, $dispatcher);

        $this->expectException(\App\Room\Domain\Exception\RoomAlreadyExistsException::class);

        ($handler)(new RegisterRoomCommand(
            id: 'room-id-2',
            hotelId: 'hotel-id-1',
            number: '101',
            floor: 1,
            roomTypeId: 'rt-id-1',
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        ));
    }
}
```

- [ ] **Step 2: Run the test — expect FAIL**

```bash
make unit-test
```

- [ ] **Step 3: Modify the handler**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\RegisterRoom;

use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomAlreadyExistsException;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Room\Domain\Model\Room;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Room\Domain\Port\RoomTypeExistsInterface;
use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\RoomRegistered;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class RegisterRoomCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
        private HotelExistsInterface $hotelExists,
        private RoomTypeExistsInterface $roomTypeExists,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(RegisterRoomCommand $command): void
    {
        if (!$this->hotelExists->exists($command->hotelId)) {
            throw new HotelNotFoundException($command->hotelId);
        }

        if (!$this->roomTypeExists->exists($command->roomTypeId)) {
            throw new RoomTypeNotFoundException($command->roomTypeId);
        }

        if ($this->roomRepository->existsByHotelIdAndNumber($command->hotelId, $command->number)) {
            throw new RoomAlreadyExistsException($command->number, $command->hotelId);
        }

        $this->roomRepository->add(new Room(
            $command->id,
            $command->hotelId,
            new RoomNumber($command->number),
            new RoomFloor($command->floor),
            $command->roomTypeId,
            $command->createdAt,
        ));

        $this->eventDispatcher->dispatch(new RoomRegistered(
            roomId: $command->id,
            hotelId: $command->hotelId,
            roomTypeId: $command->roomTypeId,
        ));
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
make unit-test
```

- [ ] **Step 5: Run full lint**

```bash
make lint
```

Expected: no errors. If deptrac complains, check that no `App\Search\` imports were accidentally introduced.

- [ ] **Step 6: Run full test suite**

```bash
make test
```

Expected: all tests PASS (unit + functional).

- [ ] **Step 7: Commit**

```bash
git add src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php \
        tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php
git commit -m "feat(room): dispatch RoomRegistered from RegisterRoomCommandHandler"
```

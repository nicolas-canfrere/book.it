# Search — Async Command Projection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace direct DBAL writes in Search projectors with async Messenger commands so that search read-model maintenance never blocks or fails the main request, while keeping the Application layer free of vendor dependencies.

**Architecture:** Each `#[AsEventListener]` becomes a thin dispatcher injecting `AsyncCommandDispatcherInterface` and emitting an `AsyncCommandInterface` message to the AMQP `commands` transport. Dedicated command handlers in `App\Search\Application\UseCase\` implement `AsyncCommandHandlerInterface` and delegate all SQL to three writer interfaces defined in `App\Search\Domain\Port\` (`HotelRoomTypeWriterInterface`, `RoomIndexWriterInterface`, `UnavailablePeriodWriterInterface`). DBAL connections are confined to the Infrastructure implementations of those interfaces. `HotelRoomTypeWriter` reads hotel data from `@doctrine.dbal.hotel_connection` and writes to `@doctrine.dbal.search_connection` (search_path=search → unqualified table names). All other writers use only `@doctrine.dbal.search_connection`.

**Tech Stack:** PHP 8.4, Symfony 8.0, Symfony Messenger (AMQP), Doctrine DBAL, PHPUnit (unit tests with `TestCase` + mocks)

**Prerequisite:** Plans 1 and 2 fully applied **except** the listener bodies from Plan 2 Tasks 7–11. This plan replaces those direct-write implementations. The skeleton (Plan 2 Task 6), query handler (Task 12), and controller (Task 13) are unchanged.

---

## File Map

**New files:**

*Domain ports:*
- `src/Search/Domain/Port/HotelRoomTypeWriterInterface.php`
- `src/Search/Domain/Port/RoomIndexWriterInterface.php`
- `src/Search/Domain/Port/UnavailablePeriodWriterInterface.php`

*Infrastructure writers (tested against Connection mocks):*
- `src/Search/Infrastructure/Persistence/HotelRoomTypeWriter.php`
- `src/Search/Infrastructure/Persistence/RoomIndexWriter.php`
- `src/Search/Infrastructure/Persistence/UnavailablePeriodWriter.php`
- `tests/Search/Infrastructure/Persistence/HotelRoomTypeWriterTest.php`
- `tests/Search/Infrastructure/Persistence/RoomIndexWriterTest.php`
- `tests/Search/Infrastructure/Persistence/UnavailablePeriodWriterTest.php`

*Async commands (implement `AsyncCommandInterface`):*
- `src/Search/Application/UseCase/UpdateSearchHotelStarRating/UpdateSearchHotelStarRatingCommand.php`
- `src/Search/Application/UseCase/UpdateSearchHotelAmenities/UpdateSearchHotelAmenitiesCommand.php`
- `src/Search/Application/UseCase/RegisterSearchRoomType/RegisterSearchRoomTypeCommand.php`
- `src/Search/Application/UseCase/UpdateSearchRoomType/UpdateSearchRoomTypeCommand.php`
- `src/Search/Application/UseCase/UpdateSearchRoomTypeAmenities/UpdateSearchRoomTypeAmenitiesCommand.php`
- `src/Search/Application/UseCase/DeleteSearchRoomType/DeleteSearchRoomTypeCommand.php`
- `src/Search/Application/UseCase/RegisterSearchRoom/RegisterSearchRoomCommand.php`
- `src/Search/Application/UseCase/AddSearchUnavailablePeriod/AddSearchUnavailablePeriodCommand.php`
- `src/Search/Application/UseCase/RemoveSearchUnavailablePeriodByPeriod/RemoveSearchUnavailablePeriodByPeriodCommand.php`
- `src/Search/Application/UseCase/RemoveSearchUnavailablePeriodBySource/RemoveSearchUnavailablePeriodBySourceCommand.php`

*Async command handlers (implement `AsyncCommandHandlerInterface`, tested against writer interface mocks):*
- `src/Search/Application/UseCase/UpdateSearchHotelStarRating/UpdateSearchHotelStarRatingCommandHandler.php`
- `src/Search/Application/UseCase/UpdateSearchHotelAmenities/UpdateSearchHotelAmenitiesCommandHandler.php`
- `src/Search/Application/UseCase/RegisterSearchRoomType/RegisterSearchRoomTypeCommandHandler.php`
- `src/Search/Application/UseCase/UpdateSearchRoomType/UpdateSearchRoomTypeCommandHandler.php`
- `src/Search/Application/UseCase/UpdateSearchRoomTypeAmenities/UpdateSearchRoomTypeAmenitiesCommandHandler.php`
- `src/Search/Application/UseCase/DeleteSearchRoomType/DeleteSearchRoomTypeCommandHandler.php`
- `src/Search/Application/UseCase/RegisterSearchRoom/RegisterSearchRoomCommandHandler.php`
- `src/Search/Application/UseCase/AddSearchUnavailablePeriod/AddSearchUnavailablePeriodCommandHandler.php`
- `src/Search/Application/UseCase/RemoveSearchUnavailablePeriodByPeriod/RemoveSearchUnavailablePeriodByPeriodCommandHandler.php`
- `src/Search/Application/UseCase/RemoveSearchUnavailablePeriodBySource/RemoveSearchUnavailablePeriodBySourceCommandHandler.php`
- `tests/Search/Application/UseCase/UpdateSearchHotelStarRating/UpdateSearchHotelStarRatingCommandHandlerTest.php`
- `tests/Search/Application/UseCase/UpdateSearchHotelAmenities/UpdateSearchHotelAmenitiesCommandHandlerTest.php`
- `tests/Search/Application/UseCase/RegisterSearchRoomType/RegisterSearchRoomTypeCommandHandlerTest.php`
- `tests/Search/Application/UseCase/UpdateSearchRoomType/UpdateSearchRoomTypeCommandHandlerTest.php`
- `tests/Search/Application/UseCase/UpdateSearchRoomTypeAmenities/UpdateSearchRoomTypeAmenitiesCommandHandlerTest.php`
- `tests/Search/Application/UseCase/DeleteSearchRoomType/DeleteSearchRoomTypeCommandHandlerTest.php`
- `tests/Search/Application/UseCase/RegisterSearchRoom/RegisterSearchRoomCommandHandlerTest.php`
- `tests/Search/Application/UseCase/AddSearchUnavailablePeriod/AddSearchUnavailablePeriodCommandHandlerTest.php`
- `tests/Search/Application/UseCase/RemoveSearchUnavailablePeriodByPeriod/RemoveSearchUnavailablePeriodByPeriodCommandHandlerTest.php`
- `tests/Search/Application/UseCase/RemoveSearchUnavailablePeriodBySource/RemoveSearchUnavailablePeriodBySourceCommandHandlerTest.php`

**Modified files:**
- `src/Search/Infrastructure/EventListener/StarRatingClassifiedListener.php`
- `src/Search/Infrastructure/EventListener/HotelAmenityDeclaredListener.php`
- `src/Search/Infrastructure/EventListener/RoomTypeRegisteredListener.php`
- `src/Search/Infrastructure/EventListener/RoomTypeUpdatedListener.php`
- `src/Search/Infrastructure/EventListener/RoomTypeAmenityDeclaredListener.php`
- `src/Search/Infrastructure/EventListener/RoomTypeDeletedListener.php`
- `src/Search/Infrastructure/EventListener/RoomRegisteredListener.php`
- `src/Search/Infrastructure/EventListener/BlockedPeriodCreatedListener.php`
- `src/Search/Infrastructure/EventListener/BlockedPeriodDeletedListener.php`
- `src/Search/Infrastructure/EventListener/AvailabilityHoldCreatedListener.php`
- `src/Search/Infrastructure/EventListener/AvailabilityHoldDeletedListener.php`
- `config/services/search.yaml`

---

### Task 1: Domain port interfaces — writer contracts

**Files:**
- Create: `src/Search/Domain/Port/HotelRoomTypeWriterInterface.php`
- Create: `src/Search/Domain/Port/RoomIndexWriterInterface.php`
- Create: `src/Search/Domain/Port/UnavailablePeriodWriterInterface.php`

- [ ] **Step 1: Create `HotelRoomTypeWriterInterface`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

interface HotelRoomTypeWriterInterface
{
    public function updateStarRating(string $hotelId, ?int $starRating): void;

    /** @param string[] $amenities */
    public function updateHotelAmenities(string $hotelId, array $amenities): void;

    /** @param list<array{type: string, count: int}> $bedComposition */
    public function upsertRoomType(
        string $roomTypeId,
        string $hotelId,
        string $name,
        int $guestCapacity,
        array $bedComposition,
    ): void;

    /** @param list<array{type: string, count: int}> $bedComposition */
    public function updateRoomType(
        string $roomTypeId,
        string $name,
        int $guestCapacity,
        array $bedComposition,
    ): void;

    /** @param string[] $amenities */
    public function updateRoomAmenities(string $roomTypeId, array $amenities): void;

    public function deleteRoomType(string $roomTypeId): void;
}
```

- [ ] **Step 2: Create `RoomIndexWriterInterface`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

interface RoomIndexWriterInterface
{
    public function upsert(string $roomId, string $roomTypeId, string $hotelId): void;
}
```

- [ ] **Step 3: Create `UnavailablePeriodWriterInterface`**

`add()` receives `sourceId` which serves both as the row primary key (ensuring idempotent replay via `ON CONFLICT (id) DO NOTHING`) and as the lookup key for deletion — `blockedPeriodId` for blocked periods, `reservationId` for availability holds.

```php
<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

interface UnavailablePeriodWriterInterface
{
    public function add(
        string $sourceId,
        string $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void;

    public function removeByPeriod(
        string $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void;

    public function removeBySource(string $sourceId): void;
}
```

- [ ] **Step 4: Run lint**

```bash
make lint
```

- [ ] **Step 5: Commit**

```bash
git add src/Search/Domain/Port/
git commit -m "feat(search): add Search domain port interfaces (HotelRoomTypeWriter, RoomIndexWriter, UnavailablePeriodWriter)"
```

---

### Task 2: Infrastructure writers — DBAL implementations

**Files:**
- Create: `src/Search/Infrastructure/Persistence/HotelRoomTypeWriter.php`
- Create: `src/Search/Infrastructure/Persistence/RoomIndexWriter.php`
- Create: `src/Search/Infrastructure/Persistence/UnavailablePeriodWriter.php`
- Create: `tests/Search/Infrastructure/Persistence/HotelRoomTypeWriterTest.php`
- Create: `tests/Search/Infrastructure/Persistence/RoomIndexWriterTest.php`
- Create: `tests/Search/Infrastructure/Persistence/UnavailablePeriodWriterTest.php`

All table names are unqualified — the `search_path=search` set by `SearchPathMiddleware` on the `search` connection resolves them to the `search` schema at runtime. `HotelRoomTypeWriter::upsertRoomType()` reads hotel data from `$hotelConnection` (search_path=hotel, so `FROM hotel` is unqualified) then writes to `$connection`.

- [ ] **Step 1: Write failing tests for `HotelRoomTypeWriter`**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Infrastructure\Persistence;

use App\Search\Infrastructure\Persistence\HotelRoomTypeWriter;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class HotelRoomTypeWriterTest extends TestCase
{
    #[Test]
    public function itUpdatesStarRating(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE hotel_room_types SET star_rating = :starRating WHERE hotel_id = :hotelId',
                ['starRating' => 4, 'hotelId' => 'hotel-id-1'],
            );

        (new HotelRoomTypeWriter($connection, $this->createMock(Connection::class)))
            ->updateStarRating('hotel-id-1', 4);
    }

    #[Test]
    public function itSetsNullStarRating(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE hotel_room_types SET star_rating = :starRating WHERE hotel_id = :hotelId',
                ['starRating' => null, 'hotelId' => 'hotel-id-1'],
            );

        (new HotelRoomTypeWriter($connection, $this->createMock(Connection::class)))
            ->updateStarRating('hotel-id-1', null);
    }

    #[Test]
    public function itUpdatesHotelAmenities(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE hotel_room_types SET hotel_amenities = :amenities WHERE hotel_id = :hotelId',
                ['amenities' => '["pool","gym"]', 'hotelId' => 'hotel-id-1'],
            );

        (new HotelRoomTypeWriter($connection, $this->createMock(Connection::class)))
            ->updateHotelAmenities('hotel-id-1', ['pool', 'gym']);
    }

    #[Test]
    public function itUpsertsRoomTypeWithDenormalizedHotelData(): void
    {
        $searchConnection = $this->createMock(Connection::class);
        $hotelConnection  = $this->createMock(Connection::class);

        $hotelConnection->method('fetchAssociative')->willReturn([
            'name'        => 'Le Grand Hôtel',
            'city'        => 'Paris',
            'country'     => 'FR',
            'star_rating' => 4,
            'amenities'   => '["pool"]',
        ]);

        $searchConnection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('INSERT INTO hotel_room_types'),
                $this->callback(static function (array $p): bool {
                    return $p['roomTypeId'] === 'rt-id-1'
                        && $p['hotelId'] === 'hotel-id-1'
                        && $p['hotelName'] === 'Le Grand Hôtel'
                        && $p['city'] === 'Paris'
                        && $p['country'] === 'FR'
                        && $p['starRating'] === 4
                        && $p['hotelAmenities'] === '["pool"]'
                        && $p['roomTypeName'] === 'Standard'
                        && $p['guestCapacity'] === 2;
                }),
            );

        (new HotelRoomTypeWriter($searchConnection, $hotelConnection))
            ->upsertRoomType('rt-id-1', 'hotel-id-1', 'Standard', 2, [['type' => 'double', 'count' => 1]]);
    }

    #[Test]
    public function itSkipsUpsertWhenHotelNotFound(): void
    {
        $searchConnection = $this->createMock(Connection::class);
        $hotelConnection  = $this->createMock(Connection::class);

        $hotelConnection->method('fetchAssociative')->willReturn(false);
        $searchConnection->expects($this->never())->method('executeStatement');

        (new HotelRoomTypeWriter($searchConnection, $hotelConnection))
            ->upsertRoomType('rt-id-1', 'missing-hotel', 'Standard', 2, []);
    }

    #[Test]
    public function itUpdatesRoomType(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('UPDATE hotel_room_types'),
                $this->callback(static function (array $p): bool {
                    return $p['roomTypeId'] === 'rt-id-1'
                        && $p['name'] === 'Standard Plus'
                        && $p['guestCapacity'] === 3;
                }),
            );

        (new HotelRoomTypeWriter($connection, $this->createMock(Connection::class)))
            ->updateRoomType('rt-id-1', 'Standard Plus', 3, [['type' => 'king', 'count' => 1]]);
    }

    #[Test]
    public function itUpdatesRoomAmenities(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE hotel_room_types SET room_amenities = :amenities WHERE room_type_id = :roomTypeId',
                ['amenities' => '["wifi","tv"]', 'roomTypeId' => 'rt-id-1'],
            );

        (new HotelRoomTypeWriter($connection, $this->createMock(Connection::class)))
            ->updateRoomAmenities('rt-id-1', ['wifi', 'tv']);
    }

    #[Test]
    public function itDeletesRoomType(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'DELETE FROM hotel_room_types WHERE room_type_id = :roomTypeId',
                ['roomTypeId' => 'rt-id-1'],
            );

        (new HotelRoomTypeWriter($connection, $this->createMock(Connection::class)))
            ->deleteRoomType('rt-id-1');
    }
}
```

- [ ] **Step 2: Write failing tests for `RoomIndexWriter`**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Infrastructure\Persistence;

use App\Search\Infrastructure\Persistence\RoomIndexWriter;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RoomIndexWriterTest extends TestCase
{
    #[Test]
    public function itUpsertsRoom(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('INSERT INTO room_index'),
                ['roomId' => 'room-id-1', 'roomTypeId' => 'rt-id-1', 'hotelId' => 'hotel-id-1'],
            );

        (new RoomIndexWriter($connection))->upsert('room-id-1', 'rt-id-1', 'hotel-id-1');
    }
}
```

- [ ] **Step 3: Write failing tests for `UnavailablePeriodWriter`**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Infrastructure\Persistence;

use App\Search\Infrastructure\Persistence\UnavailablePeriodWriter;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UnavailablePeriodWriterTest extends TestCase
{
    #[Test]
    public function itInsertsUnavailablePeriodLookingUpRoomIndex(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                'SELECT room_type_id, hotel_id FROM room_index WHERE room_id = :roomId',
                ['roomId' => 'room-id-1'],
            )
            ->willReturn(['room_type_id' => 'rt-id-1', 'hotel_id' => 'hotel-id-1']);

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('INSERT INTO unavailable_periods'),
                $this->callback(static function (array $p): bool {
                    return $p['id'] === 'source-id-1'
                        && $p['roomId'] === 'room-id-1'
                        && $p['roomTypeId'] === 'rt-id-1'
                        && $p['hotelId'] === 'hotel-id-1'
                        && $p['checkIn'] === '2026-07-01'
                        && $p['checkOut'] === '2026-07-05'
                        && $p['sourceId'] === 'source-id-1';
                }),
            );

        (new UnavailablePeriodWriter($connection))->add(
            'source-id-1',
            'room-id-1',
            new \DateTimeImmutable('2026-07-01'),
            new \DateTimeImmutable('2026-07-05'),
        );
    }

    #[Test]
    public function itSkipsInsertWhenRoomNotIndexed(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        $connection->expects($this->never())->method('executeStatement');

        (new UnavailablePeriodWriter($connection))->add(
            'source-id-1',
            'unknown-room',
            new \DateTimeImmutable('2026-07-01'),
            new \DateTimeImmutable('2026-07-05'),
        );
    }

    #[Test]
    public function itRemovesByPeriod(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('DELETE FROM unavailable_periods'),
                ['roomId' => 'room-id-1', 'checkIn' => '2026-07-01', 'checkOut' => '2026-07-05'],
            );

        (new UnavailablePeriodWriter($connection))->removeByPeriod(
            'room-id-1',
            new \DateTimeImmutable('2026-07-01'),
            new \DateTimeImmutable('2026-07-05'),
        );
    }

    #[Test]
    public function itRemovesBySource(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'DELETE FROM unavailable_periods WHERE source_id = :sourceId',
                ['sourceId' => 'res-id-1'],
            );

        (new UnavailablePeriodWriter($connection))->removeBySource('res-id-1');
    }
}
```

- [ ] **Step 4: Run tests — expect FAIL**

```bash
make unit-test
```

- [ ] **Step 5: Create `HotelRoomTypeWriter`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Persistence;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use Doctrine\DBAL\Connection;

final readonly class HotelRoomTypeWriter implements HotelRoomTypeWriterInterface
{
    public function __construct(
        private Connection $connection,
        private Connection $hotelConnection,
    ) {
    }

    public function updateStarRating(string $hotelId, ?int $starRating): void
    {
        $this->connection->executeStatement(
            'UPDATE hotel_room_types SET star_rating = :starRating WHERE hotel_id = :hotelId',
            ['starRating' => $starRating, 'hotelId' => $hotelId],
        );
    }

    public function updateHotelAmenities(string $hotelId, array $amenities): void
    {
        $this->connection->executeStatement(
            'UPDATE hotel_room_types SET hotel_amenities = :amenities WHERE hotel_id = :hotelId',
            ['amenities' => json_encode($amenities, \JSON_THROW_ON_ERROR), 'hotelId' => $hotelId],
        );
    }

    public function upsertRoomType(
        string $roomTypeId,
        string $hotelId,
        string $name,
        int $guestCapacity,
        array $bedComposition,
    ): void {
        $hotel = $this->hotelConnection->fetchAssociative(
            'SELECT name, city, country, star_rating, amenities FROM hotel WHERE id = :id',
            ['id' => $hotelId],
        );

        if (false === $hotel) {
            return;
        }

        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO hotel_room_types
                (room_type_id, hotel_id, hotel_name, city, country, star_rating, hotel_amenities,
                 room_type_name, guest_capacity, bed_composition, room_amenities)
            VALUES
                (:roomTypeId, :hotelId, :hotelName, :city, :country, :starRating, :hotelAmenities,
                 :roomTypeName, :guestCapacity, :bedComposition, '[]')
            ON CONFLICT (room_type_id) DO UPDATE SET
                hotel_name      = EXCLUDED.hotel_name,
                city            = EXCLUDED.city,
                country         = EXCLUDED.country,
                star_rating     = EXCLUDED.star_rating,
                hotel_amenities = EXCLUDED.hotel_amenities,
                room_type_name  = EXCLUDED.room_type_name,
                guest_capacity  = EXCLUDED.guest_capacity,
                bed_composition = EXCLUDED.bed_composition
            SQL,
            [
                'roomTypeId'     => $roomTypeId,
                'hotelId'        => $hotelId,
                'hotelName'      => $hotel['name'],
                'city'           => $hotel['city'],
                'country'        => $hotel['country'],
                'starRating'     => $hotel['star_rating'] ?? null,
                'hotelAmenities' => $hotel['amenities'] ?? '[]',
                'roomTypeName'   => $name,
                'guestCapacity'  => $guestCapacity,
                'bedComposition' => json_encode($bedComposition, \JSON_THROW_ON_ERROR),
            ],
        );
    }

    public function updateRoomType(
        string $roomTypeId,
        string $name,
        int $guestCapacity,
        array $bedComposition,
    ): void {
        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE hotel_room_types
            SET room_type_name  = :name,
                guest_capacity  = :guestCapacity,
                bed_composition = :bedComposition
            WHERE room_type_id = :roomTypeId
            SQL,
            [
                'name'           => $name,
                'guestCapacity'  => $guestCapacity,
                'bedComposition' => json_encode($bedComposition, \JSON_THROW_ON_ERROR),
                'roomTypeId'     => $roomTypeId,
            ],
        );
    }

    public function updateRoomAmenities(string $roomTypeId, array $amenities): void
    {
        $this->connection->executeStatement(
            'UPDATE hotel_room_types SET room_amenities = :amenities WHERE room_type_id = :roomTypeId',
            ['amenities' => json_encode($amenities, \JSON_THROW_ON_ERROR), 'roomTypeId' => $roomTypeId],
        );
    }

    public function deleteRoomType(string $roomTypeId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM hotel_room_types WHERE room_type_id = :roomTypeId',
            ['roomTypeId' => $roomTypeId],
        );
    }
}
```

Note: verify the column names of the `hotel` table in the Hotel migrations before running. Adjust `amenities` to match the actual column name.

- [ ] **Step 6: Create `RoomIndexWriter`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Persistence;

use App\Search\Domain\Port\RoomIndexWriterInterface;
use Doctrine\DBAL\Connection;

final readonly class RoomIndexWriter implements RoomIndexWriterInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function upsert(string $roomId, string $roomTypeId, string $hotelId): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO room_index (room_id, room_type_id, hotel_id)
            VALUES (:roomId, :roomTypeId, :hotelId)
            ON CONFLICT (room_id) DO UPDATE SET
                room_type_id = EXCLUDED.room_type_id,
                hotel_id     = EXCLUDED.hotel_id
            SQL,
            ['roomId' => $roomId, 'roomTypeId' => $roomTypeId, 'hotelId' => $hotelId],
        );
    }
}
```

- [ ] **Step 7: Create `UnavailablePeriodWriter`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Persistence;

use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use Doctrine\DBAL\Connection;

final readonly class UnavailablePeriodWriter implements UnavailablePeriodWriterInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function add(
        string $sourceId,
        string $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void {
        $roomRow = $this->connection->fetchAssociative(
            'SELECT room_type_id, hotel_id FROM room_index WHERE room_id = :roomId',
            ['roomId' => $roomId],
        );

        if (false === $roomRow) {
            return;
        }

        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO unavailable_periods (id, room_id, room_type_id, hotel_id, period, source_id)
            VALUES (:id, :roomId, :roomTypeId, :hotelId, daterange(:checkIn, :checkOut), :sourceId)
            ON CONFLICT (id) DO NOTHING
            SQL,
            [
                'id'         => $sourceId,
                'roomId'     => $roomId,
                'roomTypeId' => $roomRow['room_type_id'],
                'hotelId'    => $roomRow['hotel_id'],
                'checkIn'    => $checkIn->format('Y-m-d'),
                'checkOut'   => $checkOut->format('Y-m-d'),
                'sourceId'   => $sourceId,
            ],
        );
    }

    public function removeByPeriod(
        string $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void {
        $this->connection->executeStatement(
            <<<'SQL'
            DELETE FROM unavailable_periods
            WHERE room_id = :roomId
              AND period = daterange(:checkIn, :checkOut)
            SQL,
            ['roomId' => $roomId, 'checkIn' => $checkIn->format('Y-m-d'), 'checkOut' => $checkOut->format('Y-m-d')],
        );
    }

    public function removeBySource(string $sourceId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM unavailable_periods WHERE source_id = :sourceId',
            ['sourceId' => $sourceId],
        );
    }
}
```

- [ ] **Step 8: Run tests — expect PASS**

```bash
make unit-test
```

- [ ] **Step 9: Run lint**

```bash
make lint
```

- [ ] **Step 10: Commit**

```bash
git add src/Search/Infrastructure/Persistence/ \
        tests/Search/Infrastructure/Persistence/
git commit -m "feat(search): add Search Infrastructure writers (HotelRoomTypeWriter, RoomIndexWriter, UnavailablePeriodWriter)"
```

---

### Task 3: Hotel async command handlers

**Files:**
- Create: `src/Search/Application/UseCase/UpdateSearchHotelStarRating/UpdateSearchHotelStarRatingCommand.php`
- Create: `src/Search/Application/UseCase/UpdateSearchHotelStarRating/UpdateSearchHotelStarRatingCommandHandler.php`
- Create: `src/Search/Application/UseCase/UpdateSearchHotelAmenities/UpdateSearchHotelAmenitiesCommand.php`
- Create: `src/Search/Application/UseCase/UpdateSearchHotelAmenities/UpdateSearchHotelAmenitiesCommandHandler.php`
- Create: `tests/Search/Application/UseCase/UpdateSearchHotelStarRating/UpdateSearchHotelStarRatingCommandHandlerTest.php`
- Create: `tests/Search/Application/UseCase/UpdateSearchHotelAmenities/UpdateSearchHotelAmenitiesCommandHandlerTest.php`

- [ ] **Step 1: Create command classes**

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchHotelStarRating;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class UpdateSearchHotelStarRatingCommand implements AsyncCommandInterface
{
    public function __construct(
        public string $hotelId,
        public ?int $starRating,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchHotelAmenities;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class UpdateSearchHotelAmenitiesCommand implements AsyncCommandInterface
{
    /** @param string[] $amenities */
    public function __construct(
        public string $hotelId,
        public array $amenities,
    ) {
    }
}
```

- [ ] **Step 2: Write failing tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\UpdateSearchHotelStarRating;

use App\Search\Application\UseCase\UpdateSearchHotelStarRating\UpdateSearchHotelStarRatingCommand;
use App\Search\Application\UseCase\UpdateSearchHotelStarRating\UpdateSearchHotelStarRatingCommandHandler;
use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UpdateSearchHotelStarRatingCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesStarRatingUpdateToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('updateStarRating')
            ->with('hotel-id-1', 4);

        $handler = new UpdateSearchHotelStarRatingCommandHandler($writer);
        ($handler)(new UpdateSearchHotelStarRatingCommand(hotelId: 'hotel-id-1', starRating: 4));
    }

    #[Test]
    public function itDelegatesNullStarRatingToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('updateStarRating')
            ->with('hotel-id-1', null);

        $handler = new UpdateSearchHotelStarRatingCommandHandler($writer);
        ($handler)(new UpdateSearchHotelStarRatingCommand(hotelId: 'hotel-id-1', starRating: null));
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\UpdateSearchHotelAmenities;

use App\Search\Application\UseCase\UpdateSearchHotelAmenities\UpdateSearchHotelAmenitiesCommand;
use App\Search\Application\UseCase\UpdateSearchHotelAmenities\UpdateSearchHotelAmenitiesCommandHandler;
use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UpdateSearchHotelAmenitiesCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesAmenitiesUpdateToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('updateHotelAmenities')
            ->with('hotel-id-1', ['pool', 'gym']);

        $handler = new UpdateSearchHotelAmenitiesCommandHandler($writer);
        ($handler)(new UpdateSearchHotelAmenitiesCommand(hotelId: 'hotel-id-1', amenities: ['pool', 'gym']));
    }
}
```

- [ ] **Step 3: Run tests — expect FAIL**

```bash
make unit-test
```

- [ ] **Step 4: Create handler classes**

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchHotelStarRating;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class UpdateSearchHotelStarRatingCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private HotelRoomTypeWriterInterface $writer)
    {
    }

    public function __invoke(UpdateSearchHotelStarRatingCommand $command): void
    {
        $this->writer->updateStarRating($command->hotelId, $command->starRating);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchHotelAmenities;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class UpdateSearchHotelAmenitiesCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private HotelRoomTypeWriterInterface $writer)
    {
    }

    public function __invoke(UpdateSearchHotelAmenitiesCommand $command): void
    {
        $this->writer->updateHotelAmenities($command->hotelId, $command->amenities);
    }
}
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
make unit-test
```

- [ ] **Step 6: Commit**

```bash
git add src/Search/Application/UseCase/UpdateSearchHotelStarRating/ \
        src/Search/Application/UseCase/UpdateSearchHotelAmenities/ \
        tests/Search/Application/UseCase/UpdateSearchHotelStarRating/ \
        tests/Search/Application/UseCase/UpdateSearchHotelAmenities/
git commit -m "feat(search): add Hotel async command handlers (star rating, amenities)"
```

---

### Task 4: RoomType async command handlers

**Files:**
- Create: `src/Search/Application/UseCase/RegisterSearchRoomType/RegisterSearchRoomTypeCommand.php`
- Create: `src/Search/Application/UseCase/RegisterSearchRoomType/RegisterSearchRoomTypeCommandHandler.php`
- Create: `src/Search/Application/UseCase/UpdateSearchRoomType/UpdateSearchRoomTypeCommand.php`
- Create: `src/Search/Application/UseCase/UpdateSearchRoomType/UpdateSearchRoomTypeCommandHandler.php`
- Create: `src/Search/Application/UseCase/UpdateSearchRoomTypeAmenities/UpdateSearchRoomTypeAmenitiesCommand.php`
- Create: `src/Search/Application/UseCase/UpdateSearchRoomTypeAmenities/UpdateSearchRoomTypeAmenitiesCommandHandler.php`
- Create: `src/Search/Application/UseCase/DeleteSearchRoomType/DeleteSearchRoomTypeCommand.php`
- Create: `src/Search/Application/UseCase/DeleteSearchRoomType/DeleteSearchRoomTypeCommandHandler.php`
- Create: `tests/Search/Application/UseCase/RegisterSearchRoomType/RegisterSearchRoomTypeCommandHandlerTest.php`
- Create: `tests/Search/Application/UseCase/UpdateSearchRoomType/UpdateSearchRoomTypeCommandHandlerTest.php`
- Create: `tests/Search/Application/UseCase/UpdateSearchRoomTypeAmenities/UpdateSearchRoomTypeAmenitiesCommandHandlerTest.php`
- Create: `tests/Search/Application/UseCase/DeleteSearchRoomType/DeleteSearchRoomTypeCommandHandlerTest.php`

- [ ] **Step 1: Create the four command classes**

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RegisterSearchRoomType;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class RegisterSearchRoomTypeCommand implements AsyncCommandInterface
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

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchRoomType;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class UpdateSearchRoomTypeCommand implements AsyncCommandInterface
{
    /** @param list<array{type: string, count: int}> $bedComposition */
    public function __construct(
        public string $roomTypeId,
        public string $name,
        public int $guestCapacity,
        public array $bedComposition,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchRoomTypeAmenities;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class UpdateSearchRoomTypeAmenitiesCommand implements AsyncCommandInterface
{
    /** @param string[] $amenities */
    public function __construct(
        public string $roomTypeId,
        public array $amenities,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\DeleteSearchRoomType;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class DeleteSearchRoomTypeCommand implements AsyncCommandInterface
{
    public function __construct(public string $roomTypeId)
    {
    }
}
```

- [ ] **Step 2: Write failing tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\RegisterSearchRoomType;

use App\Search\Application\UseCase\RegisterSearchRoomType\RegisterSearchRoomTypeCommand;
use App\Search\Application\UseCase\RegisterSearchRoomType\RegisterSearchRoomTypeCommandHandler;
use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegisterSearchRoomTypeCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesUpsertToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('upsertRoomType')
            ->with('rt-id-1', 'hotel-id-1', 'Standard', 2, [['type' => 'double', 'count' => 1]]);

        $handler = new RegisterSearchRoomTypeCommandHandler($writer);
        ($handler)(new RegisterSearchRoomTypeCommand(
            roomTypeId: 'rt-id-1',
            hotelId: 'hotel-id-1',
            name: 'Standard',
            guestCapacity: 2,
            bedComposition: [['type' => 'double', 'count' => 1]],
        ));
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\UpdateSearchRoomType;

use App\Search\Application\UseCase\UpdateSearchRoomType\UpdateSearchRoomTypeCommand;
use App\Search\Application\UseCase\UpdateSearchRoomType\UpdateSearchRoomTypeCommandHandler;
use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UpdateSearchRoomTypeCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesUpdateToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('updateRoomType')
            ->with('rt-id-1', 'Standard Plus', 3, [['type' => 'king', 'count' => 1]]);

        $handler = new UpdateSearchRoomTypeCommandHandler($writer);
        ($handler)(new UpdateSearchRoomTypeCommand(
            roomTypeId: 'rt-id-1',
            name: 'Standard Plus',
            guestCapacity: 3,
            bedComposition: [['type' => 'king', 'count' => 1]],
        ));
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\UpdateSearchRoomTypeAmenities;

use App\Search\Application\UseCase\UpdateSearchRoomTypeAmenities\UpdateSearchRoomTypeAmenitiesCommand;
use App\Search\Application\UseCase\UpdateSearchRoomTypeAmenities\UpdateSearchRoomTypeAmenitiesCommandHandler;
use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UpdateSearchRoomTypeAmenitiesCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesAmenitiesUpdateToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('updateRoomAmenities')
            ->with('rt-id-1', ['wifi', 'tv']);

        $handler = new UpdateSearchRoomTypeAmenitiesCommandHandler($writer);
        ($handler)(new UpdateSearchRoomTypeAmenitiesCommand(roomTypeId: 'rt-id-1', amenities: ['wifi', 'tv']));
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\DeleteSearchRoomType;

use App\Search\Application\UseCase\DeleteSearchRoomType\DeleteSearchRoomTypeCommand;
use App\Search\Application\UseCase\DeleteSearchRoomType\DeleteSearchRoomTypeCommandHandler;
use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DeleteSearchRoomTypeCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesDeletionToWriter(): void
    {
        $writer = $this->createMock(HotelRoomTypeWriterInterface::class);
        $writer->expects($this->once())
            ->method('deleteRoomType')
            ->with('rt-id-1');

        $handler = new DeleteSearchRoomTypeCommandHandler($writer);
        ($handler)(new DeleteSearchRoomTypeCommand(roomTypeId: 'rt-id-1'));
    }
}
```

- [ ] **Step 3: Run tests — expect FAIL**

```bash
make unit-test
```

- [ ] **Step 4: Create the four handler classes**

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RegisterSearchRoomType;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class RegisterSearchRoomTypeCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private HotelRoomTypeWriterInterface $writer)
    {
    }

    public function __invoke(RegisterSearchRoomTypeCommand $command): void
    {
        $this->writer->upsertRoomType(
            $command->roomTypeId,
            $command->hotelId,
            $command->name,
            $command->guestCapacity,
            $command->bedComposition,
        );
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchRoomType;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class UpdateSearchRoomTypeCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private HotelRoomTypeWriterInterface $writer)
    {
    }

    public function __invoke(UpdateSearchRoomTypeCommand $command): void
    {
        $this->writer->updateRoomType(
            $command->roomTypeId,
            $command->name,
            $command->guestCapacity,
            $command->bedComposition,
        );
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\UpdateSearchRoomTypeAmenities;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class UpdateSearchRoomTypeAmenitiesCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private HotelRoomTypeWriterInterface $writer)
    {
    }

    public function __invoke(UpdateSearchRoomTypeAmenitiesCommand $command): void
    {
        $this->writer->updateRoomAmenities($command->roomTypeId, $command->amenities);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\DeleteSearchRoomType;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class DeleteSearchRoomTypeCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private HotelRoomTypeWriterInterface $writer)
    {
    }

    public function __invoke(DeleteSearchRoomTypeCommand $command): void
    {
        $this->writer->deleteRoomType($command->roomTypeId);
    }
}
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
make unit-test
```

- [ ] **Step 6: Commit**

```bash
git add src/Search/Application/UseCase/RegisterSearchRoomType/ \
        src/Search/Application/UseCase/UpdateSearchRoomType/ \
        src/Search/Application/UseCase/UpdateSearchRoomTypeAmenities/ \
        src/Search/Application/UseCase/DeleteSearchRoomType/ \
        tests/Search/Application/UseCase/RegisterSearchRoomType/ \
        tests/Search/Application/UseCase/UpdateSearchRoomType/ \
        tests/Search/Application/UseCase/UpdateSearchRoomTypeAmenities/ \
        tests/Search/Application/UseCase/DeleteSearchRoomType/
git commit -m "feat(search): add RoomType async command handlers (register, update, amenities, delete)"
```

---

### Task 5: Room async command and handler

**Files:**
- Create: `src/Search/Application/UseCase/RegisterSearchRoom/RegisterSearchRoomCommand.php`
- Create: `src/Search/Application/UseCase/RegisterSearchRoom/RegisterSearchRoomCommandHandler.php`
- Create: `tests/Search/Application/UseCase/RegisterSearchRoom/RegisterSearchRoomCommandHandlerTest.php`

- [ ] **Step 1: Create command and write failing test**

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RegisterSearchRoom;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class RegisterSearchRoomCommand implements AsyncCommandInterface
{
    public function __construct(
        public string $roomId,
        public string $hotelId,
        public string $roomTypeId,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\RegisterSearchRoom;

use App\Search\Application\UseCase\RegisterSearchRoom\RegisterSearchRoomCommand;
use App\Search\Application\UseCase\RegisterSearchRoom\RegisterSearchRoomCommandHandler;
use App\Search\Domain\Port\RoomIndexWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegisterSearchRoomCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesUpsertToWriter(): void
    {
        $writer = $this->createMock(RoomIndexWriterInterface::class);
        $writer->expects($this->once())
            ->method('upsert')
            ->with('room-id-1', 'rt-id-1', 'hotel-id-1');

        $handler = new RegisterSearchRoomCommandHandler($writer);
        ($handler)(new RegisterSearchRoomCommand(roomId: 'room-id-1', hotelId: 'hotel-id-1', roomTypeId: 'rt-id-1'));
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
make unit-test
```

- [ ] **Step 3: Create handler**

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RegisterSearchRoom;

use App\Search\Domain\Port\RoomIndexWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class RegisterSearchRoomCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private RoomIndexWriterInterface $writer)
    {
    }

    public function __invoke(RegisterSearchRoomCommand $command): void
    {
        $this->writer->upsert($command->roomId, $command->roomTypeId, $command->hotelId);
    }
}
```

- [ ] **Step 4: Run test — expect PASS**

```bash
make unit-test
```

- [ ] **Step 5: Commit**

```bash
git add src/Search/Application/UseCase/RegisterSearchRoom/ \
        tests/Search/Application/UseCase/RegisterSearchRoom/
git commit -m "feat(search): add RegisterSearchRoom async command handler"
```

---

### Task 6: Availability async command handlers

**Files:**
- Create: `src/Search/Application/UseCase/AddSearchUnavailablePeriod/AddSearchUnavailablePeriodCommand.php`
- Create: `src/Search/Application/UseCase/AddSearchUnavailablePeriod/AddSearchUnavailablePeriodCommandHandler.php`
- Create: `src/Search/Application/UseCase/RemoveSearchUnavailablePeriodByPeriod/RemoveSearchUnavailablePeriodByPeriodCommand.php`
- Create: `src/Search/Application/UseCase/RemoveSearchUnavailablePeriodByPeriod/RemoveSearchUnavailablePeriodByPeriodCommandHandler.php`
- Create: `src/Search/Application/UseCase/RemoveSearchUnavailablePeriodBySource/RemoveSearchUnavailablePeriodBySourceCommand.php`
- Create: `src/Search/Application/UseCase/RemoveSearchUnavailablePeriodBySource/RemoveSearchUnavailablePeriodBySourceCommandHandler.php`
- Create: `tests/Search/Application/UseCase/AddSearchUnavailablePeriod/AddSearchUnavailablePeriodCommandHandlerTest.php`
- Create: `tests/Search/Application/UseCase/RemoveSearchUnavailablePeriodByPeriod/RemoveSearchUnavailablePeriodByPeriodCommandHandlerTest.php`
- Create: `tests/Search/Application/UseCase/RemoveSearchUnavailablePeriodBySource/RemoveSearchUnavailablePeriodBySourceCommandHandlerTest.php`

- [ ] **Step 1: Create the three command classes**

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\AddSearchUnavailablePeriod;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class AddSearchUnavailablePeriodCommand implements AsyncCommandInterface
{
    public function __construct(
        public string $sourceId,
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RemoveSearchUnavailablePeriodByPeriod;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class RemoveSearchUnavailablePeriodByPeriodCommand implements AsyncCommandInterface
{
    public function __construct(
        public string $roomId,
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RemoveSearchUnavailablePeriodBySource;

use App\Shared\Application\Bus\AsyncCommandInterface;

final readonly class RemoveSearchUnavailablePeriodBySourceCommand implements AsyncCommandInterface
{
    public function __construct(public string $sourceId)
    {
    }
}
```

- [ ] **Step 2: Write failing tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\AddSearchUnavailablePeriod;

use App\Search\Application\UseCase\AddSearchUnavailablePeriod\AddSearchUnavailablePeriodCommand;
use App\Search\Application\UseCase\AddSearchUnavailablePeriod\AddSearchUnavailablePeriodCommandHandler;
use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AddSearchUnavailablePeriodCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesAddToWriter(): void
    {
        $writer   = $this->createMock(UnavailablePeriodWriterInterface::class);
        $checkIn  = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        $writer->expects($this->once())
            ->method('add')
            ->with('source-id-1', 'room-id-1', $checkIn, $checkOut);

        $handler = new AddSearchUnavailablePeriodCommandHandler($writer);
        ($handler)(new AddSearchUnavailablePeriodCommand(
            sourceId: 'source-id-1',
            roomId: 'room-id-1',
            checkIn: $checkIn,
            checkOut: $checkOut,
        ));
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\RemoveSearchUnavailablePeriodByPeriod;

use App\Search\Application\UseCase\RemoveSearchUnavailablePeriodByPeriod\RemoveSearchUnavailablePeriodByPeriodCommand;
use App\Search\Application\UseCase\RemoveSearchUnavailablePeriodByPeriod\RemoveSearchUnavailablePeriodByPeriodCommandHandler;
use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RemoveSearchUnavailablePeriodByPeriodCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesRemoveByPeriodToWriter(): void
    {
        $writer   = $this->createMock(UnavailablePeriodWriterInterface::class);
        $checkIn  = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        $writer->expects($this->once())
            ->method('removeByPeriod')
            ->with('room-id-1', $checkIn, $checkOut);

        $handler = new RemoveSearchUnavailablePeriodByPeriodCommandHandler($writer);
        ($handler)(new RemoveSearchUnavailablePeriodByPeriodCommand(
            roomId: 'room-id-1',
            checkIn: $checkIn,
            checkOut: $checkOut,
        ));
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Application\UseCase\RemoveSearchUnavailablePeriodBySource;

use App\Search\Application\UseCase\RemoveSearchUnavailablePeriodBySource\RemoveSearchUnavailablePeriodBySourceCommand;
use App\Search\Application\UseCase\RemoveSearchUnavailablePeriodBySource\RemoveSearchUnavailablePeriodBySourceCommandHandler;
use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RemoveSearchUnavailablePeriodBySourceCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesRemoveBySourceToWriter(): void
    {
        $writer = $this->createMock(UnavailablePeriodWriterInterface::class);
        $writer->expects($this->once())
            ->method('removeBySource')
            ->with('res-id-1');

        $handler = new RemoveSearchUnavailablePeriodBySourceCommandHandler($writer);
        ($handler)(new RemoveSearchUnavailablePeriodBySourceCommand(sourceId: 'res-id-1'));
    }
}
```

- [ ] **Step 3: Run tests — expect FAIL**

```bash
make unit-test
```

- [ ] **Step 4: Create the three handler classes**

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\AddSearchUnavailablePeriod;

use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class AddSearchUnavailablePeriodCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private UnavailablePeriodWriterInterface $writer)
    {
    }

    public function __invoke(AddSearchUnavailablePeriodCommand $command): void
    {
        $this->writer->add($command->sourceId, $command->roomId, $command->checkIn, $command->checkOut);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RemoveSearchUnavailablePeriodByPeriod;

use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class RemoveSearchUnavailablePeriodByPeriodCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private UnavailablePeriodWriterInterface $writer)
    {
    }

    public function __invoke(RemoveSearchUnavailablePeriodByPeriodCommand $command): void
    {
        $this->writer->removeByPeriod($command->roomId, $command->checkIn, $command->checkOut);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Search\Application\UseCase\RemoveSearchUnavailablePeriodBySource;

use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use App\Shared\Application\Bus\AsyncCommandHandlerInterface;

final readonly class RemoveSearchUnavailablePeriodBySourceCommandHandler implements AsyncCommandHandlerInterface
{
    public function __construct(private UnavailablePeriodWriterInterface $writer)
    {
    }

    public function __invoke(RemoveSearchUnavailablePeriodBySourceCommand $command): void
    {
        $this->writer->removeBySource($command->sourceId);
    }
}
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
make unit-test
```

- [ ] **Step 6: Run lint**

```bash
make lint
```

- [ ] **Step 7: Commit**

```bash
git add src/Search/Application/UseCase/AddSearchUnavailablePeriod/ \
        src/Search/Application/UseCase/RemoveSearchUnavailablePeriodByPeriod/ \
        src/Search/Application/UseCase/RemoveSearchUnavailablePeriodBySource/ \
        tests/Search/Application/UseCase/AddSearchUnavailablePeriod/ \
        tests/Search/Application/UseCase/RemoveSearchUnavailablePeriodByPeriod/ \
        tests/Search/Application/UseCase/RemoveSearchUnavailablePeriodBySource/
git commit -m "feat(search): add Availability async command handlers (add/remove unavailable periods)"
```

---

### Task 7: Update all listeners to dispatch async commands

**Files:**
- Modify: all 11 listener files in `src/Search/Infrastructure/EventListener/`

Each listener loses its `Connection` (or `HotelRoomTypeWriterInterface` etc.) constructor arg and gains `AsyncCommandDispatcherInterface`. `HotelRegisteredListener` stays a no-op.

- [ ] **Step 1: Rewrite `StarRatingClassifiedListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\UpdateSearchHotelStarRating\UpdateSearchHotelStarRatingCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\StarRatingClassified;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: StarRatingClassified::class)]
final readonly class StarRatingClassifiedListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(StarRatingClassified $event): void
    {
        $this->commandDispatcher->dispatch(new UpdateSearchHotelStarRatingCommand(
            hotelId: $event->hotelId,
            starRating: $event->starRating,
        ));
    }
}
```

- [ ] **Step 2: Rewrite `HotelAmenityDeclaredListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\UpdateSearchHotelAmenities\UpdateSearchHotelAmenitiesCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\HotelAmenityDeclared;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: HotelAmenityDeclared::class)]
final readonly class HotelAmenityDeclaredListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(HotelAmenityDeclared $event): void
    {
        $this->commandDispatcher->dispatch(new UpdateSearchHotelAmenitiesCommand(
            hotelId: $event->hotelId,
            amenities: $event->amenities,
        ));
    }
}
```

- [ ] **Step 3: Rewrite `RoomTypeRegisteredListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\RegisterSearchRoomType\RegisterSearchRoomTypeCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\RoomTypeRegistered;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeRegistered::class)]
final readonly class RoomTypeRegisteredListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(RoomTypeRegistered $event): void
    {
        $this->commandDispatcher->dispatch(new RegisterSearchRoomTypeCommand(
            roomTypeId: $event->roomTypeId,
            hotelId: $event->hotelId,
            name: $event->name,
            guestCapacity: $event->guestCapacity,
            bedComposition: $event->bedComposition,
        ));
    }
}
```

- [ ] **Step 4: Rewrite `RoomTypeUpdatedListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\UpdateSearchRoomType\UpdateSearchRoomTypeCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\RoomTypeUpdated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeUpdated::class)]
final readonly class RoomTypeUpdatedListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(RoomTypeUpdated $event): void
    {
        $this->commandDispatcher->dispatch(new UpdateSearchRoomTypeCommand(
            roomTypeId: $event->roomTypeId,
            name: $event->name,
            guestCapacity: $event->guestCapacity,
            bedComposition: $event->bedComposition,
        ));
    }
}
```

- [ ] **Step 5: Rewrite `RoomTypeAmenityDeclaredListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\UpdateSearchRoomTypeAmenities\UpdateSearchRoomTypeAmenitiesCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\RoomTypeAmenityDeclared;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeAmenityDeclared::class)]
final readonly class RoomTypeAmenityDeclaredListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(RoomTypeAmenityDeclared $event): void
    {
        $this->commandDispatcher->dispatch(new UpdateSearchRoomTypeAmenitiesCommand(
            roomTypeId: $event->roomTypeId,
            amenities: $event->amenities,
        ));
    }
}
```

- [ ] **Step 6: Rewrite `RoomTypeDeletedListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\DeleteSearchRoomType\DeleteSearchRoomTypeCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\RoomTypeDeleted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomTypeDeleted::class)]
final readonly class RoomTypeDeletedListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(RoomTypeDeleted $event): void
    {
        $this->commandDispatcher->dispatch(new DeleteSearchRoomTypeCommand(
            roomTypeId: $event->roomTypeId,
        ));
    }
}
```

- [ ] **Step 7: Rewrite `RoomRegisteredListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\RegisterSearchRoom\RegisterSearchRoomCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\RoomRegistered;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: RoomRegistered::class)]
final readonly class RoomRegisteredListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(RoomRegistered $event): void
    {
        $this->commandDispatcher->dispatch(new RegisterSearchRoomCommand(
            roomId: $event->roomId,
            hotelId: $event->hotelId,
            roomTypeId: $event->roomTypeId,
        ));
    }
}
```

- [ ] **Step 8: Rewrite `BlockedPeriodCreatedListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\AddSearchUnavailablePeriod\AddSearchUnavailablePeriodCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\BlockedPeriodCreated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BlockedPeriodCreated::class)]
final readonly class BlockedPeriodCreatedListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(BlockedPeriodCreated $event): void
    {
        $this->commandDispatcher->dispatch(new AddSearchUnavailablePeriodCommand(
            sourceId: $event->blockedPeriodId,
            roomId: $event->roomId,
            checkIn: $event->checkIn,
            checkOut: $event->checkOut,
        ));
    }
}
```

- [ ] **Step 9: Rewrite `BlockedPeriodDeletedListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\RemoveSearchUnavailablePeriodByPeriod\RemoveSearchUnavailablePeriodByPeriodCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\BlockedPeriodDeleted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BlockedPeriodDeleted::class)]
final readonly class BlockedPeriodDeletedListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(BlockedPeriodDeleted $event): void
    {
        $this->commandDispatcher->dispatch(new RemoveSearchUnavailablePeriodByPeriodCommand(
            roomId: $event->roomId,
            checkIn: $event->checkIn,
            checkOut: $event->checkOut,
        ));
    }
}
```

- [ ] **Step 10: Rewrite `AvailabilityHoldCreatedListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\AddSearchUnavailablePeriod\AddSearchUnavailablePeriodCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\AvailabilityHoldCreated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: AvailabilityHoldCreated::class)]
final readonly class AvailabilityHoldCreatedListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(AvailabilityHoldCreated $event): void
    {
        $this->commandDispatcher->dispatch(new AddSearchUnavailablePeriodCommand(
            sourceId: $event->reservationId,
            roomId: $event->roomId,
            checkIn: $event->checkIn,
            checkOut: $event->checkOut,
        ));
    }
}
```

- [ ] **Step 11: Rewrite `AvailabilityHoldDeletedListener`**

```php
<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\EventListener;

use App\Search\Application\UseCase\RemoveSearchUnavailablePeriodBySource\RemoveSearchUnavailablePeriodBySourceCommand;
use App\Shared\Application\Bus\AsyncCommandDispatcherInterface;
use App\Shared\Domain\Event\AvailabilityHoldDeleted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: AvailabilityHoldDeleted::class)]
final readonly class AvailabilityHoldDeletedListener
{
    public function __construct(private AsyncCommandDispatcherInterface $commandDispatcher)
    {
    }

    public function __invoke(AvailabilityHoldDeleted $event): void
    {
        $this->commandDispatcher->dispatch(new RemoveSearchUnavailablePeriodBySourceCommand(
            sourceId: $event->reservationId,
        ));
    }
}
```

- [ ] **Step 12: Run lint**

```bash
make lint
```

- [ ] **Step 13: Commit**

```bash
git add src/Search/Infrastructure/EventListener/
git commit -m "feat(search): rewrite projector listeners to dispatch async commands"
```

---

### Task 8: Update `config/services/search.yaml` and validate

**Files:**
- Modify: `config/services/search.yaml`

- [ ] **Step 1: Replace the full content of `config/services/search.yaml`**

```yaml
parameters: {}

services:
    _defaults:
        autowire: true
        autoconfigure: true
    _instanceof:
        App\Shared\Application\Bus\SyncQueryHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: sync.query.bus}
        App\Shared\Application\Bus\AsyncCommandHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: messenger.bus.default}

    App\Search\Domain\:
        resource: '../../src/Search/Domain/'

    App\Search\Infrastructure\:
        resource: '../../src/Search/Infrastructure/'

    App\Search\UI\:
        resource: '../../src/Search/UI/'
        exclude:
            - '../../src/Search/UI/**/*Request.php'

    App\Search\Application\:
        resource: '../../src/Search/Application/'
        exclude:
            - '../../src/Search/Application/**/*Query.php'
            - '../../src/Search/Application/**/*Command.php'

    bookit.doctrine.middleware.search_path.search:
        class: App\Shared\Infrastructure\Doctrine\SearchPathMiddleware
        arguments:
            $schema: 'search'
        tags:
            - {name: doctrine.middleware, connection: search}

    # Query handler — reads from search schema
    App\Search\Application\UseCase\SearchAvailableRoomTypes\SearchAvailableRoomTypesQueryHandler:
        arguments:
            $connection: '@doctrine.dbal.search_connection'

    # Infrastructure writers — only DBAL dependencies live here
    App\Search\Infrastructure\Persistence\HotelRoomTypeWriter:
        arguments:
            $connection: '@doctrine.dbal.search_connection'
            $hotelConnection: '@doctrine.dbal.hotel_connection'

    App\Search\Infrastructure\Persistence\RoomIndexWriter:
        arguments:
            $connection: '@doctrine.dbal.search_connection'

    App\Search\Infrastructure\Persistence\UnavailablePeriodWriter:
        arguments:
            $connection: '@doctrine.dbal.search_connection'
```

Note: `AsyncCommandDispatcherInterface` is autowired by Symfony — no explicit binding needed. Handler-to-writer wiring is handled by the interface type-hint + autowire, because each handler injects a single interface that maps to exactly one implementation.

- [ ] **Step 2: Clear cache and verify container compiles**

```bash
docker compose exec php bin/console cache:clear
```

Expected: no errors.

- [ ] **Step 3: Run unit tests**

```bash
make unit-test
```

Expected: all PASS.

- [ ] **Step 4: Run full lint**

```bash
make lint
```

Expected: no CS Fixer, PHPStan, or deptrac errors. The Application layer now imports only `App\Search\Domain\Port\*` (project interfaces, no vendor) — deptrac is fully satisfied.

- [ ] **Step 5: Run full test suite**

```bash
make test
```

Expected: all PASS. Functional tests use the `in-memory://` transport override so no AMQP connection is attempted.

- [ ] **Step 6: Commit**

```bash
git add config/services/search.yaml
git commit -m "feat(search): wire Search writers and async handlers — no vendor in Application layer"
```

# Room Value Objects Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace `Room.number: string` with `RoomNumber` VO, and add a mandatory `RoomFloor` VO, propagating changes through all layers.

**Architecture:** Two `final readonly` VOs in `App\Room\Domain\ValueObject\`. Commands stay primitive (string/int). VOs are constructed in domain model and hydrated manually in the DBAL repository. CSV batch format gains a `floor` column; the parser returns `list<RoomCsvRow>`.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL (no ORM entity — manual hydration), PHPUnit, Docker via `make`.

---

## File Map

| Action | Path |
|---|---|
| Create | `src/Room/Domain/ValueObject/RoomNumber.php` |
| Create | `src/Room/Domain/ValueObject/RoomFloor.php` |
| Create | `src/Room/Application/Service/RoomCsvRow.php` |
| Create | `migrations/Version20260517000000.php` |
| Create | `tests/Room/Domain/ValueObject/RoomNumberTest.php` |
| Create | `tests/Room/Domain/ValueObject/RoomFloorTest.php` |
| Modify | `src/Room/Domain/Model/Room.php` |
| Modify | `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommand.php` |
| Modify | `src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommand.php` |
| Modify | `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php` |
| Modify | `src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandler.php` |
| Modify | `src/Room/Application/Service/RegisterRoomCommandFactory.php` |
| Modify | `src/Room/Application/Service/BatchRegisterRoomsCommandFactory.php` |
| Modify | `src/Room/Application/Service/CsvRoomNumbersParser.php` |
| Modify | `src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php` |
| Modify | `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomRequest.php` |
| Modify | `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomController.php` |
| Modify | `src/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsController.php` |
| Modify | `src/Room/UI/Http/Controller/RoomSerializer.php` |
| Modify | `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomRepository.php` |
| Modify | `tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php` |
| Modify | `tests/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandlerTest.php` |
| Modify | `tests/Room/Application/Service/CsvRoomNumbersParserTest.php` |
| Modify | `tests/Room/UI/Http/Controller/RegisterRoom/RegisterRoomControllerTest.php` |
| Modify | `tests/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsControllerTest.php` |

---

## Task 1: RoomNumber Value Object

**Files:**
- Create: `tests/Room/Domain/ValueObject/RoomNumberTest.php`
- Create: `src/Room/Domain/ValueObject/RoomNumber.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Room/Domain/ValueObject/RoomNumberTest.php
declare(strict_types=1);

namespace App\Tests\Room\Domain\ValueObject;

use App\Room\Domain\ValueObject\RoomNumber;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RoomNumberTest extends TestCase
{
    #[Test]
    public function itAcceptsNumericString(): void
    {
        $vo = new RoomNumber('101');
        self::assertSame('101', $vo->value);
    }

    #[Test]
    public function itAcceptsAlphanumericString(): void
    {
        $vo = new RoomNumber('2A');
        self::assertSame('2A', $vo->value);
    }

    #[Test]
    public function itAcceptsStringWithSpecialChars(): void
    {
        $vo = new RoomNumber('Suite #3');
        self::assertSame('Suite #3', $vo->value);
    }

    #[Test]
    public function itAcceptsMaxLength(): void
    {
        $vo = new RoomNumber(str_repeat('X', 50));
        self::assertSame(str_repeat('X', 50), $vo->value);
    }

    #[Test]
    public function itThrowsOnBlankString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RoomNumber('');
    }

    #[Test]
    public function itThrowsOnWhitespaceOnlyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RoomNumber('   ');
    }

    #[Test]
    public function itThrowsWhenExceeding50Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RoomNumber(str_repeat('X', 51));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
make unit-test-quiet ARGS="--filter RoomNumberTest"
```
Expected: FAIL with class not found.

- [ ] **Step 3: Write the implementation**

```php
<?php
// src/Room/Domain/ValueObject/RoomNumber.php
declare(strict_types=1);

namespace App\Room\Domain\ValueObject;

final readonly class RoomNumber
{
    public string $value;

    public function __construct(string $value)
    {
        if ('' === trim($value)) {
            throw new \InvalidArgumentException('Room number must not be blank.');
        }
        if (mb_strlen($value) > 50) {
            throw new \InvalidArgumentException('Room number must not exceed 50 characters.');
        }
        $this->value = $value;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
make unit-test-quiet ARGS="--filter RoomNumberTest"
```
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Room/Domain/ValueObject/RoomNumber.php tests/Room/Domain/ValueObject/RoomNumberTest.php
git commit -m "feat(room): add RoomNumber value object"
```

---

## Task 2: RoomFloor Value Object

**Files:**
- Create: `tests/Room/Domain/ValueObject/RoomFloorTest.php`
- Create: `src/Room/Domain/ValueObject/RoomFloor.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Room/Domain/ValueObject/RoomFloorTest.php
declare(strict_types=1);

namespace App\Tests\Room\Domain\ValueObject;

use App\Room\Domain\ValueObject\RoomFloor;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RoomFloorTest extends TestCase
{
    #[Test]
    public function itAcceptsGroundFloor(): void
    {
        $vo = new RoomFloor(0);
        self::assertSame(0, $vo->value);
    }

    #[Test]
    public function itAcceptsPositiveFloor(): void
    {
        $vo = new RoomFloor(5);
        self::assertSame(5, $vo->value);
    }

    #[Test]
    public function itAcceptsNegativeFloor(): void
    {
        $vo = new RoomFloor(-1);
        self::assertSame(-1, $vo->value);
    }

    #[Test]
    public function itAcceptsLowerBound(): void
    {
        $vo = new RoomFloor(-20);
        self::assertSame(-20, $vo->value);
    }

    #[Test]
    public function itAcceptsUpperBound(): void
    {
        $vo = new RoomFloor(300);
        self::assertSame(300, $vo->value);
    }

    #[Test]
    public function itThrowsBelowLowerBound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RoomFloor(-21);
    }

    #[Test]
    public function itThrowsAboveUpperBound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RoomFloor(301);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
make unit-test-quiet ARGS="--filter RoomFloorTest"
```
Expected: FAIL with class not found.

- [ ] **Step 3: Write the implementation**

```php
<?php
// src/Room/Domain/ValueObject/RoomFloor.php
declare(strict_types=1);

namespace App\Room\Domain\ValueObject;

final readonly class RoomFloor
{
    public int $value;

    public function __construct(int $value)
    {
        if ($value < -20 || $value > 300) {
            throw new \InvalidArgumentException('Room floor must be between -20 and 300.');
        }
        $this->value = $value;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
make unit-test-quiet ARGS="--filter RoomFloorTest"
```
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Room/Domain/ValueObject/RoomFloor.php tests/Room/Domain/ValueObject/RoomFloorTest.php
git commit -m "feat(room): add RoomFloor value object"
```

---

## Task 3: Core Domain — Room model, Commands, InMemoryRoomRepository, CommandHandlers

This task updates the full unit-testable domain core in one shot. All six steps must be done before running tests, because updating `Room.php` breaks handlers and the in-memory repo simultaneously.

**Files:**
- Modify: `src/Room/Domain/Model/Room.php`
- Modify: `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommand.php`
- Modify: `src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommand.php`
- Modify: `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php`
- Modify: `src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandler.php`
- Modify: `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomRepository.php`
- Modify: `tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php`
- Modify: `tests/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandlerTest.php`

- [ ] **Step 1: Update Room model**

Replace `src/Room/Domain/Model/Room.php` entirely:

```php
<?php
declare(strict_types=1);

namespace App\Room\Domain\Model;

use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;

final readonly class Room
{
    public function __construct(
        public string $id,
        public string $hotelId,
        public RoomNumber $number,
        public RoomFloor $floor,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 2: Update RegisterRoomCommand**

Replace `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommand.php`:

```php
<?php
declare(strict_types=1);

namespace App\Room\Application\UseCase\RegisterRoom;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class RegisterRoomCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public string $hotelId,
        public string $number,
        public int $floor,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 3: Update BatchRegisterRoomsCommand**

Replace `src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommand.php`:

```php
<?php
declare(strict_types=1);

namespace App\Room\Application\UseCase\BatchRegisterRooms;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class BatchRegisterRoomsCommand implements SyncCommandInterface
{
    /**
     * @param list<array{id: string, number: string, floor: int}> $entries
     */
    public function __construct(
        public string $hotelId,
        public array $entries,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 4: Update RegisterRoomCommandHandler**

Replace `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php`:

```php
<?php
declare(strict_types=1);

namespace App\Room\Application\UseCase\RegisterRoom;

use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomAlreadyExistsException;
use App\Room\Domain\Model\Room;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class RegisterRoomCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
        private HotelExistsInterface $hotelExists,
    ) {
    }

    public function __invoke(RegisterRoomCommand $command): void
    {
        if (!$this->hotelExists->exists($command->hotelId)) {
            throw new HotelNotFoundException($command->hotelId);
        }

        if ($this->roomRepository->existsByHotelIdAndNumber($command->hotelId, $command->number)) {
            throw new RoomAlreadyExistsException($command->number, $command->hotelId);
        }

        $this->roomRepository->add(new Room(
            $command->id,
            $command->hotelId,
            new RoomNumber($command->number),
            new RoomFloor($command->floor),
            $command->createdAt,
        ));
    }
}
```

- [ ] **Step 5: Update BatchRegisterRoomsCommandHandler**

Replace `src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandler.php`:

```php
<?php
declare(strict_types=1);

namespace App\Room\Application\UseCase\BatchRegisterRooms;

use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomBatchInvalidException;
use App\Room\Domain\Model\Room;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class BatchRegisterRoomsCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
        private HotelExistsInterface $hotelExists,
    ) {
    }

    public function __invoke(BatchRegisterRoomsCommand $command): void
    {
        if (!$this->hotelExists->exists($command->hotelId)) {
            throw new HotelNotFoundException($command->hotelId);
        }

        $violations = [];
        $seenNumbers = [];

        foreach ($command->entries as $index => $entry) {
            $lineField = \sprintf('line[%d]', $index + 2);
            $number = $entry['number'];
            $floor = $entry['floor'];

            if ('' === $number) {
                $violations[] = ['field' => $lineField, 'message' => 'Room number must not be blank.'];
                continue;
            }

            if (mb_strlen($number) > 50) {
                $violations[] = ['field' => $lineField, 'message' => 'Room number must not exceed 50 characters.'];
                continue;
            }

            if ($floor < -20 || $floor > 300) {
                $violations[] = ['field' => $lineField, 'message' => 'Room floor must be between -20 and 300.'];
                continue;
            }

            if (isset($seenNumbers[$number])) {
                $violations[] = ['field' => $lineField, 'message' => \sprintf('Room number "%s" is duplicated in this batch.', $number)];
                continue;
            }

            if ($this->roomRepository->existsByHotelIdAndNumber($command->hotelId, $number)) {
                $violations[] = ['field' => $lineField, 'message' => \sprintf('Room number "%s" already exists in this hotel.', $number)];
                continue;
            }

            $seenNumbers[$number] = true;
        }

        if ([] !== $violations) {
            throw new RoomBatchInvalidException($violations);
        }

        $rooms = array_map(
            fn(array $entry) => new Room(
                $entry['id'],
                $command->hotelId,
                new RoomNumber(trim($entry['number'])),
                new RoomFloor($entry['floor']),
                $command->createdAt,
            ),
            $command->entries,
        );

        $this->roomRepository->addAll($rooms);
    }
}
```

- [ ] **Step 6: Update InMemoryRoomRepository**

Replace `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomRepository.php`:

```php
<?php
declare(strict_types=1);

namespace App\Tests\Room\Infrastructure\Persistence\InMemory;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomPage;
use App\Room\Domain\Port\RoomRepositoryInterface;

final class InMemoryRoomRepository implements RoomRepositoryInterface
{
    /** @var array<string, Room> */
    private array $rooms = [];

    public function add(Room $room): void
    {
        $this->rooms[$room->id] = $room;
    }

    public function addAll(array $rooms): void
    {
        foreach ($rooms as $room) {
            $this->add($room);
        }
    }

    public function get(string $id): ?Room
    {
        return $this->rooms[$id] ?? null;
    }

    public function existsByHotelIdAndNumber(string $hotelId, string $number): bool
    {
        foreach ($this->rooms as $room) {
            if ($room->hotelId === $hotelId && $room->number->value === $number) {
                return true;
            }
        }

        return false;
    }

    public function list(string $hotelId, int $page, int $limit): RoomPage
    {
        $filtered = array_values(array_filter(
            $this->rooms,
            static fn(Room $r) => $r->hotelId === $hotelId,
        ));

        usort($filtered, static fn(Room $a, Room $b) => strcmp($a->number->value, $b->number->value));

        $total = count($filtered);
        $rooms = array_slice($filtered, ($page - 1) * $limit, $limit);

        return new RoomPage($rooms, $total);
    }
}
```

- [ ] **Step 7: Update RegisterRoomCommandHandlerTest**

Replace `tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\RegisterRoom;

use App\Room\Application\UseCase\RegisterRoom\RegisterRoomCommand;
use App\Room\Application\UseCase\RegisterRoom\RegisterRoomCommandHandler;
use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomAlreadyExistsException;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegisterRoomCommandHandlerTest extends TestCase
{
    private InMemoryRoomRepository $roomRepository;
    private FakeHotelExistenceChecker $hotelExistenceChecker;
    private RegisterRoomCommandHandler $handler;

    protected function setUp(): void
    {
        $this->roomRepository = new InMemoryRoomRepository();
        $this->hotelExistenceChecker = new FakeHotelExistenceChecker();
        $this->handler = new RegisterRoomCommandHandler($this->roomRepository, $this->hotelExistenceChecker);
    }

    #[Test]
    public function itPersistsTheRoom(): void
    {
        $command = new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 1,
            createdAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
        );

        ($this->handler)($command);

        $room = $this->roomRepository->get($command->id);
        self::assertNotNull($room);
        self::assertSame($command->id, $room->id);
        self::assertSame($command->hotelId, $room->hotelId);
        self::assertSame('101', $room->number->value);
        self::assertSame(1, $room->floor->value);
        self::assertEquals($command->createdAt, $room->createdAt);
    }

    #[Test]
    public function itThrowsWhenHotelDoesNotExist(): void
    {
        $this->hotelExistenceChecker->setExists(false);
        $this->expectException(HotelNotFoundException::class);

        ($this->handler)(new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 1,
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itThrowsWhenRoomNumberAlreadyExistsInHotel(): void
    {
        $command = new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 1,
            createdAt: new \DateTimeImmutable(),
        );
        ($this->handler)($command);

        $this->expectException(RoomAlreadyExistsException::class);

        ($this->handler)(new RegisterRoomCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 2,
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itAllowsSameNumberInDifferentHotels(): void
    {
        $command1 = new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440001',
            number: '101',
            floor: 1,
            createdAt: new \DateTimeImmutable(),
        );
        $command2 = new RegisterRoomCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            hotelId: '550e8400-e29b-41d4-a716-446655440002',
            number: '101',
            floor: 1,
            createdAt: new \DateTimeImmutable(),
        );

        ($this->handler)($command1);
        ($this->handler)($command2);

        self::assertNotNull($this->roomRepository->get($command1->id));
        self::assertNotNull($this->roomRepository->get($command2->id));
    }
}
```

- [ ] **Step 8: Update BatchRegisterRoomsCommandHandlerTest**

Replace `tests/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandlerTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\BatchRegisterRooms;

use App\Room\Application\UseCase\BatchRegisterRooms\BatchRegisterRoomsCommand;
use App\Room\Application\UseCase\BatchRegisterRooms\BatchRegisterRoomsCommandHandler;
use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomBatchInvalidException;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BatchRegisterRoomsCommandHandlerTest extends TestCase
{
    private const string HOTEL_ID = '550e8400-e29b-41d4-a716-446655440000';

    private InMemoryRoomRepository $roomRepository;
    private FakeHotelExistenceChecker $hotelExistenceChecker;
    private BatchRegisterRoomsCommandHandler $handler;

    protected function setUp(): void
    {
        $this->roomRepository = new InMemoryRoomRepository();
        $this->hotelExistenceChecker = new FakeHotelExistenceChecker();
        $this->handler = new BatchRegisterRoomsCommandHandler(
            $this->roomRepository,
            $this->hotelExistenceChecker,
        );
    }

    #[Test]
    public function itPersistsAllRooms(): void
    {
        $command = new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [
                ['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '101', 'floor' => 1],
                ['id' => 'aaaaaaaa-0000-4000-8000-000000000002', 'number' => '102', 'floor' => 1],
            ],
            createdAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
        );

        ($this->handler)($command);

        $room1 = $this->roomRepository->get('aaaaaaaa-0000-4000-8000-000000000001');
        $room2 = $this->roomRepository->get('aaaaaaaa-0000-4000-8000-000000000002');
        self::assertNotNull($room1);
        self::assertNotNull($room2);
        self::assertSame('101', $room1->number->value);
        self::assertSame('102', $room2->number->value);
        self::assertSame(1, $room1->floor->value);
    }

    #[Test]
    public function itSucceedsWithEmptyBatch(): void
    {
        $command = new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [],
            createdAt: new \DateTimeImmutable(),
        );

        $this->expectNotToPerformAssertions();

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenHotelDoesNotExist(): void
    {
        $this->hotelExistenceChecker->setExists(false);
        $this->expectException(HotelNotFoundException::class);

        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '101', 'floor' => 1]],
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itRejectsBlankNumber(): void
    {
        $this->expectException(RoomBatchInvalidException::class);

        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '', 'floor' => 1]],
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itRejectsNumberExceeding50Characters(): void
    {
        $this->expectException(RoomBatchInvalidException::class);

        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => str_repeat('X', 51), 'floor' => 1]],
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itRejectsFloorBelowMinimum(): void
    {
        $this->expectException(RoomBatchInvalidException::class);

        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '101', 'floor' => -21]],
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itRejectsFloorAboveMaximum(): void
    {
        $this->expectException(RoomBatchInvalidException::class);

        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '101', 'floor' => 301]],
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itRejectsDuplicateWithinBatch(): void
    {
        $exception = null;
        try {
            ($this->handler)(new BatchRegisterRoomsCommand(
                hotelId: self::HOTEL_ID,
                entries: [
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '101', 'floor' => 1],
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000002', 'number' => '101', 'floor' => 1],
                ],
                createdAt: new \DateTimeImmutable(),
            ));
        } catch (RoomBatchInvalidException $e) {
            $exception = $e;
        }

        self::assertNotNull($exception);
        self::assertCount(1, $exception->violations);
        self::assertSame('line[3]', $exception->violations[0]['field']);
    }

    #[Test]
    public function itRejectsDuplicateAlreadyInRepository(): void
    {
        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '101', 'floor' => 1]],
            createdAt: new \DateTimeImmutable(),
        ));

        $exception = null;
        try {
            ($this->handler)(new BatchRegisterRoomsCommand(
                hotelId: self::HOTEL_ID,
                entries: [['id' => 'aaaaaaaa-0000-4000-8000-000000000002', 'number' => '101', 'floor' => 1]],
                createdAt: new \DateTimeImmutable(),
            ));
        } catch (RoomBatchInvalidException $e) {
            $exception = $e;
        }

        self::assertNotNull($exception);
        self::assertCount(1, $exception->violations);
        self::assertSame('line[2]', $exception->violations[0]['field']);
    }

    #[Test]
    public function itReportsAllViolationsAtOnce(): void
    {
        $exception = null;
        try {
            ($this->handler)(new BatchRegisterRoomsCommand(
                hotelId: self::HOTEL_ID,
                entries: [
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '', 'floor' => 1],
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000002', 'number' => str_repeat('X', 51), 'floor' => 1],
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000003', 'number' => '101', 'floor' => 1],
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000004', 'number' => '101', 'floor' => 1],
                ],
                createdAt: new \DateTimeImmutable(),
            ));
        } catch (RoomBatchInvalidException $e) {
            $exception = $e;
        }

        self::assertNotNull($exception);
        self::assertCount(3, $exception->violations);
        self::assertSame('line[2]', $exception->violations[0]['field']);
        self::assertSame('line[3]', $exception->violations[1]['field']);
        self::assertSame('line[5]', $exception->violations[2]['field']);
    }

    #[Test]
    public function itDoesNotPersistAnythingWhenValidationFails(): void
    {
        try {
            ($this->handler)(new BatchRegisterRoomsCommand(
                hotelId: self::HOTEL_ID,
                entries: [
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '101', 'floor' => 1],
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000002', 'number' => '', 'floor' => 1],
                ],
                createdAt: new \DateTimeImmutable(),
            ));
        } catch (RoomBatchInvalidException) {
        }

        self::assertNull($this->roomRepository->get('aaaaaaaa-0000-4000-8000-000000000001'));
    }
}
```

- [ ] **Step 9: Run unit tests**

```bash
make unit-test-quiet
```
Expected: all unit tests PASS (including the new VO tests from Tasks 1 & 2).

- [ ] **Step 10: Commit**

```bash
git add \
  src/Room/Domain/Model/Room.php \
  src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommand.php \
  src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommand.php \
  src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php \
  src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandler.php \
  tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomRepository.php \
  tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php \
  tests/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandlerTest.php
git commit -m "feat(room): introduce RoomNumber and RoomFloor VOs into domain core"
```

---

## Task 4: RoomCsvRow DTO + CsvRoomNumbersParser

**Files:**
- Create: `src/Room/Application/Service/RoomCsvRow.php`
- Modify: `src/Room/Application/Service/CsvRoomNumbersParser.php`
- Modify: `tests/Room/Application/Service/CsvRoomNumbersParserTest.php`

- [ ] **Step 1: Update the failing test first**

Replace `tests/Room/Application/Service/CsvRoomNumbersParserTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Tests\Room\Application\Service;

use App\Room\Application\Exception\InvalidCsvFormatException;
use App\Room\Application\Service\CsvRoomNumbersParser;
use App\Room\Application\Service\RoomCsvRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Group('unit')]
final class CsvRoomNumbersParserTest extends TestCase
{
    private CsvRoomNumbersParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CsvRoomNumbersParser();
    }

    #[Test]
    public function itParsesValidCsvAndReturnsRows(): void
    {
        $rows = $this->parser->parse($this->makeCsvFile("number,floor\n101,1\n102,2\n2A,-1\n"));

        self::assertCount(3, $rows);
        self::assertSame('101', $rows[0]->number);
        self::assertSame(1, $rows[0]->floor);
        self::assertSame('102', $rows[1]->number);
        self::assertSame(2, $rows[1]->floor);
        self::assertSame('2A', $rows[2]->number);
        self::assertSame(-1, $rows[2]->floor);
    }

    #[Test]
    public function itReturnsEmptyArrayForHeaderOnlyCsv(): void
    {
        $rows = $this->parser->parse($this->makeCsvFile("number,floor\n"));

        self::assertSame([], $rows);
    }

    #[Test]
    public function itAcceptsNegativeAndZeroFloors(): void
    {
        $rows = $this->parser->parse($this->makeCsvFile("number,floor\n101,0\n102,-5\n"));

        self::assertSame(0, $rows[0]->floor);
        self::assertSame(-5, $rows[1]->floor);
    }

    #[Test]
    public function itThrowsWhenHeaderIsInvalid(): void
    {
        $this->expectException(InvalidCsvFormatException::class);

        $this->parser->parse($this->makeCsvFile("number\n101\n"));
    }

    #[Test]
    public function itThrowsWhenFloorIsNotAnInteger(): void
    {
        $this->expectException(InvalidCsvFormatException::class);

        $this->parser->parse($this->makeCsvFile("number,floor\n101,abc\n"));
    }

    #[Test]
    public function itThrowsWhenFloorIsDecimal(): void
    {
        $this->expectException(InvalidCsvFormatException::class);

        $this->parser->parse($this->makeCsvFile("number,floor\n101,1.5\n"));
    }

    private function makeCsvFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rooms_') . '.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'rooms.csv', 'text/csv', null, true);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
make unit-test-quiet ARGS="--filter CsvRoomNumbersParserTest"
```
Expected: FAIL (wrong return type, wrong header).

- [ ] **Step 3: Create RoomCsvRow DTO**

```php
<?php
// src/Room/Application/Service/RoomCsvRow.php
declare(strict_types=1);

namespace App\Room\Application\Service;

final readonly class RoomCsvRow
{
    public function __construct(
        public string $number,
        public int $floor,
    ) {
    }
}
```

- [ ] **Step 4: Update CsvRoomNumbersParser**

Replace `src/Room/Application/Service/CsvRoomNumbersParser.php`:

```php
<?php
declare(strict_types=1);

namespace App\Room\Application\Service;

use App\Room\Application\Exception\InvalidCsvFormatException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class CsvRoomNumbersParser
{
    /** @return list<RoomCsvRow> */
    public function parse(UploadedFile $file): array
    {
        $handle = fopen($file->getPathname(), 'r');
        if (false === $handle) {
            throw new InvalidCsvFormatException('Could not read the uploaded file.');
        }

        $header = fgetcsv($handle, escape: '');
        if ($header !== ['number', 'floor']) {
            fclose($handle);
            throw new InvalidCsvFormatException('Invalid CSV format: expected "number,floor" header columns.');
        }

        $rows = [];
        while (false !== ($row = fgetcsv($handle, escape: ''))) {
            $rawFloor = trim($row[1] ?? '');
            $floor = filter_var($rawFloor, FILTER_VALIDATE_INT);
            if (false === $floor) {
                fclose($handle);
                throw new InvalidCsvFormatException(\sprintf('Invalid CSV format: floor value "%s" is not a valid integer.', $rawFloor));
            }
            $rows[] = new RoomCsvRow($row[0] ?? '', $floor);
        }
        fclose($handle);

        return $rows;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
make unit-test-quiet ARGS="--filter CsvRoomNumbersParserTest"
```
Expected: PASS (6 tests).

- [ ] **Step 6: Run full unit suite**

```bash
make unit-test-quiet
```
Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
git add \
  src/Room/Application/Service/RoomCsvRow.php \
  src/Room/Application/Service/CsvRoomNumbersParser.php \
  tests/Room/Application/Service/CsvRoomNumbersParserTest.php
git commit -m "feat(room): add RoomCsvRow DTO, update CSV parser to require number,floor columns"
```

---

## Task 5: Application Service Factories

**Files:**
- Modify: `src/Room/Application/Service/RegisterRoomCommandFactory.php`
- Modify: `src/Room/Application/Service/BatchRegisterRoomsCommandFactory.php`

- [ ] **Step 1: Update RegisterRoomCommandFactory**

Replace `src/Room/Application/Service/RegisterRoomCommandFactory.php`:

```php
<?php
declare(strict_types=1);

namespace App\Room\Application\Service;

use App\Room\Application\UseCase\RegisterRoom\RegisterRoomCommand;
use Psr\Clock\ClockInterface;

final readonly class RegisterRoomCommandFactory
{
    public function __construct(
        private RoomIdGeneratorInterface $roomIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(string $hotelId, ?string $number, ?int $floor): RegisterRoomCommand
    {
        if (null === $number) {
            throw new \InvalidArgumentException('Room number is required.');
        }
        if (null === $floor) {
            throw new \InvalidArgumentException('Room floor is required.');
        }

        return new RegisterRoomCommand(
            $this->roomIdGenerator->generate(),
            $hotelId,
            $number,
            $floor,
            $this->clock->now(),
        );
    }
}
```

- [ ] **Step 2: Update BatchRegisterRoomsCommandFactory**

Replace `src/Room/Application/Service/BatchRegisterRoomsCommandFactory.php`:

```php
<?php
declare(strict_types=1);

namespace App\Room\Application\Service;

use App\Room\Application\UseCase\BatchRegisterRooms\BatchRegisterRoomsCommand;
use Psr\Clock\ClockInterface;

final readonly class BatchRegisterRoomsCommandFactory
{
    public function __construct(
        private RoomIdGeneratorInterface $roomIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    /** @param list<RoomCsvRow> $rows */
    public function create(string $hotelId, array $rows): BatchRegisterRoomsCommand
    {
        $entries = array_map(
            fn(RoomCsvRow $row) => [
                'id' => $this->roomIdGenerator->generate(),
                'number' => trim($row->number),
                'floor' => $row->floor,
            ],
            $rows,
        );

        return new BatchRegisterRoomsCommand($hotelId, $entries, $this->clock->now());
    }
}
```

- [ ] **Step 3: Run unit tests**

```bash
make unit-test-quiet
```
Expected: all PASS.

- [ ] **Step 4: Commit**

```bash
git add \
  src/Room/Application/Service/RegisterRoomCommandFactory.php \
  src/Room/Application/Service/BatchRegisterRoomsCommandFactory.php
git commit -m "feat(room): update command factories to accept floor"
```

---

## Task 6: Infrastructure — Migration + RoomRepository

**Files:**
- Create: `migrations/Version20260517000000.php`
- Modify: `src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php`

- [ ] **Step 1: Create the migration**

```php
<?php
// migrations/Version20260517000000.php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename room.number to room_number, add room_floor column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE room RENAME COLUMN number TO room_number');
        $this->addSql('ALTER TABLE room ADD COLUMN room_floor INTEGER NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE room ALTER COLUMN room_floor DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE room DROP COLUMN room_floor');
        $this->addSql('ALTER TABLE room RENAME COLUMN room_number TO number');
    }
}
```

- [ ] **Step 2: Apply the migration**

```bash
make migrate
```
Expected: migration applied successfully, output mentions `Version20260517000000`.

- [ ] **Step 3: Update RoomRepository**

Replace `src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php`:

```php
<?php
declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomPage;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use Doctrine\DBAL\Connection;

final readonly class RoomRepository implements RoomRepositoryInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function add(Room $room): void
    {
        $this->bookit->insert('room', [
            'id' => $room->id,
            'hotel_id' => $room->hotelId,
            'room_number' => $room->number->value,
            'room_floor' => $room->floor->value,
            'created_at' => $room->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function addAll(array $rooms): void
    {
        $this->bookit->transactional(function () use ($rooms): void {
            foreach ($rooms as $room) {
                $this->bookit->insert('room', [
                    'id' => $room->id,
                    'hotel_id' => $room->hotelId,
                    'room_number' => $room->number->value,
                    'room_floor' => $room->floor->value,
                    'created_at' => $room->createdAt->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }

    public function get(string $id): ?Room
    {
        /** @var array{id: string, hotel_id: string, room_number: string, room_floor: int|string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, hotel_id, room_number, room_floor, created_at FROM room WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return new Room(
            $row['id'],
            $row['hotel_id'],
            new RoomNumber($row['room_number']),
            new RoomFloor((int) $row['room_floor']),
            new \DateTimeImmutable($row['created_at']),
        );
    }

    public function existsByHotelIdAndNumber(string $hotelId, string $number): bool
    {
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM room WHERE hotel_id = :hotelId AND room_number = :number',
            ['hotelId' => $hotelId, 'number' => $number],
        );

        return $count > 0;
    }

    public function list(string $hotelId, int $page, int $limit): RoomPage
    {
        /** @var int|string $count */
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM room WHERE hotel_id = :hotelId',
            ['hotelId' => $hotelId],
        );
        $total = (int) $count;

        /** @var list<array{id: string, hotel_id: string, room_number: string, room_floor: int|string, created_at: string}> $rows */
        $rows = $this->bookit->fetchAllAssociative(
            'SELECT id, hotel_id, room_number, room_floor, created_at FROM room WHERE hotel_id = :hotelId ORDER BY room_number ASC LIMIT :limit OFFSET :offset',
            ['hotelId' => $hotelId, 'limit' => $limit, 'offset' => ($page - 1) * $limit],
        );

        $rooms = array_map(
            fn(array $row) => new Room(
                $row['id'],
                $row['hotel_id'],
                new RoomNumber($row['room_number']),
                new RoomFloor((int) $row['room_floor']),
                new \DateTimeImmutable($row['created_at']),
            ),
            $rows,
        );

        return new RoomPage($rooms, $total);
    }
}
```

- [ ] **Step 4: Run unit tests**

```bash
make unit-test-quiet
```
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add \
  migrations/Version20260517000000.php \
  src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php
git commit -m "feat(room): add room_floor column, rename number to room_number, update DBAL repository"
```

---

## Task 7: UI Layer + Functional Tests

**Files:**
- Modify: `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomRequest.php`
- Modify: `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomController.php`
- Modify: `src/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsController.php`
- Modify: `src/Room/UI/Http/Controller/RoomSerializer.php`
- Modify: `tests/Room/UI/Http/Controller/RegisterRoom/RegisterRoomControllerTest.php`
- Modify: `tests/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsControllerTest.php`

- [ ] **Step 1: Update RegisterRoomRequest**

Replace `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomRequest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Room\UI\Http\Controller\RegisterRoom;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterRoomRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 50)]
        #[OA\Property(type: 'string', example: '101', maxLength: 50, minLength: 1)]
        public ?string $number = null,

        #[Assert\NotNull]
        #[Assert\Range(min: -20, max: 300)]
        #[OA\Property(type: 'integer', example: 1, minimum: -20, maximum: 300)]
        public ?int $floor = null,
    ) {
    }
}
```

- [ ] **Step 2: Update RoomSerializer**

Replace `src/Room/UI/Http/Controller/RoomSerializer.php`:

```php
<?php
declare(strict_types=1);

namespace App\Room\UI\Http\Controller;

use App\Room\Domain\Model\Room;

final class RoomSerializer
{
    /**
     * @return array{id: string, hotelId: string, number: string, floor: int, createdAt: int}
     */
    public function serialize(Room $room): array
    {
        return [
            'id' => $room->id,
            'hotelId' => $room->hotelId,
            'number' => $room->number->value,
            'floor' => $room->floor->value,
            'createdAt' => $room->createdAt->getTimestamp(),
        ];
    }
}
```

- [ ] **Step 3: Update RegisterRoomController**

Replace `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomController.php`:

```php
<?php
declare(strict_types=1);

namespace App\Room\UI\Http\Controller\RegisterRoom;

use App\Room\Application\Service\RegisterRoomCommandFactory;
use App\Room\Application\UseCase\GetRoom\GetRoomQuery;
use App\Room\UI\Http\Controller\RoomSerializer;
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

final readonly class RegisterRoomController
{
    public function __construct(
        private RegisterRoomCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private RoomSerializer $roomSerializer,
    ) {
    }

    #[Route('/api/hotels/{hotelId}/rooms', name: 'room_register_room', requirements: ['hotelId' => Requirement::UUID_V4], methods: ['POST'])]
    #[OA\Post(
        summary: 'Register a new room in a hotel',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: RegisterRoomRequest::class)),
        ),
        tags: ['Rooms'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Room registered',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'hotelId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'number', type: 'string', example: '101'),
                        new OA\Property(property: 'floor', type: 'integer', example: 1),
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
            new OA\Response(
                response: Response::HTTP_CONFLICT,
                description: 'Room already exists',
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
        string $hotelId,
        #[MapRequestPayload(acceptFormat: 'json')] RegisterRoomRequest $request,
    ): Response {
        $command = $this->commandFactory->create($hotelId, $request->number, $request->floor);
        $this->commandBus->execute($command);

        $room = $this->queryBus->ask(new GetRoomQuery($command->id));
        if (null === $room) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->roomSerializer->serialize($room), Response::HTTP_CREATED);
    }
}
```

- [ ] **Step 4: Update BatchRegisterRoomsController**

Replace `src/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsController.php`:

```php
<?php
declare(strict_types=1);

namespace App\Room\UI\Http\Controller\BatchRegisterRooms;

use App\Room\Application\Exception\InvalidCsvFormatException;
use App\Room\Application\Service\BatchRegisterRoomsCommandFactory;
use App\Room\Application\Service\CsvRoomNumbersParser;
use App\Room\Domain\Model\Room;
use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use App\Room\UI\Http\Controller\RoomSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class BatchRegisterRoomsController
{
    public function __construct(
        private BatchRegisterRoomsCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private RoomSerializer $roomSerializer,
        private CsvRoomNumbersParser $csvParser,
    ) {
    }

    #[Route('/api/hotels/{hotelId}/rooms/batch', name: 'room_batch_register_rooms', requirements: ['hotelId' => Requirement::UUID_V4], methods: ['POST'])]
    #[OA\Post(
        summary: 'Import multiple rooms in a hotel from a CSV file',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['csv'],
                    properties: [
                        new OA\Property(
                            property: 'csv',
                            description: 'CSV file with "number,floor" header columns and one room per row',
                            type: 'string',
                            format: 'binary',
                        ),
                    ],
                    type: 'object',
                ),
            ),
        ),
        tags: ['Rooms'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'All rooms registered',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'hotelId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'number', type: 'string', example: '101'),
                            new OA\Property(property: 'floor', type: 'integer', example: 1),
                            new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                        ],
                    ),
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
            new OA\Response(
                response: Response::HTTP_UNPROCESSABLE_ENTITY,
                description: 'Validation error (invalid CSV format or room constraint violations)',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'),
                ),
            ),
        ],
    )]
    public function __invoke(string $hotelId, Request $request): Response
    {
        $file = $request->files->get('csv');
        if (!$file instanceof UploadedFile) {
            throw new InvalidCsvFormatException('A CSV file is required.');
        }

        $rows = $this->csvParser->parse($file);

        $command = $this->commandFactory->create($hotelId, $rows);

        $this->commandBus->execute($command);

        $rooms = array_map(
            fn(array $entry) => $this->roomSerializer->serialize(
                new Room(
                    $entry['id'],
                    $command->hotelId,
                    new RoomNumber($entry['number']),
                    new RoomFloor($entry['floor']),
                    $command->createdAt,
                )
            ),
            $command->entries,
        );

        return new JsonResponse($rooms, Response::HTTP_CREATED);
    }
}
```

- [ ] **Step 5: Update RegisterRoomControllerTest**

Replace `tests/Room/UI/Http/Controller/RegisterRoom/RegisterRoomControllerTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\RegisterRoom;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class RegisterRoomControllerTest extends WebTestCase
{
    private const array HOTEL_PAYLOAD = [
        'name' => 'Hotel Test',
        'streetAddress' => '1 rue de la Paix',
        'postalCode' => '75001',
        'city' => 'Paris',
        'country' => 'FR',
    ];

    #[Test]
    public function itRegistersARoomAndReturns201(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{id: string, hotelId: string, number: string, floor: int, createdAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['id']);
        self::assertSame($hotelId, $body['hotelId']);
        self::assertSame('101', $body['number']);
        self::assertSame(1, $body['floor']);
        self::assertGreaterThan(0, $body['createdAt']);
    }

    #[Test]
    public function itReturns409WhenRoomNumberAlreadyExistsInHotel(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 2], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-already-exists', $body['type']);
        self::assertSame('Room Already Exists', $body['title']);
        self::assertSame(Response::HTTP_CONFLICT, $body['status']);
    }

    #[Test]
    public function itReturns404WhenHotelDoesNotExist(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/hotels/00000000-0000-4000-8000-000000000000/rooms',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/hotel-not-found', $body['type']);
        self::assertSame('Hotel Not Found', $body['title']);
        self::assertSame(Response::HTTP_NOT_FOUND, $body['status']);
    }

    #[Test]
    public function itReturns422WhenNumberIsMissing(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['floor' => 1], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns422WhenFloorIsMissing(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101'], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns422WhenFloorIsOutOfRange(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 301], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns404WhenHotelIdIsNotAValidUuidV4(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/hotels/not-a-uuid/rooms',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itAllowsSameRoomNumberInDifferentHotels(): void
    {
        $client = static::createClient();
        $hotelId1 = $this->registerHotelAndGetId($client);

        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(array_merge(self::HOTEL_PAYLOAD, ['name' => 'Hotel Test 2']), \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $hotel2Body */
        $hotel2Body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $hotelId2 = $hotel2Body['id'];

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId1}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId2}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}
```

- [ ] **Step 6: Update BatchRegisterRoomsControllerTest**

Replace `tests/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsControllerTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\BatchRegisterRooms;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class BatchRegisterRoomsControllerTest extends WebTestCase
{
    private const array HOTEL_PAYLOAD = [
        'name' => 'Hotel Test',
        'streetAddress' => '1 rue de la Paix',
        'postalCode' => '75001',
        'city' => 'Paris',
        'country' => 'FR',
    ];

    #[Test]
    public function itImportsBatchAndReturns201(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $csv = $this->makeCsvFile("number,floor\n101,1\n102,2\n2A,-1\n");
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var list<array{id: string, hotelId: string, number: string, floor: int, createdAt: int}> $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(3, $body);
        $numbers = array_column($body, 'number');
        self::assertContains('101', $numbers);
        self::assertContains('102', $numbers);
        self::assertContains('2A', $numbers);
        foreach ($body as $room) {
            self::assertNotEmpty($room['id']);
            self::assertSame($hotelId, $room['hotelId']);
            self::assertGreaterThan(0, $room['createdAt']);
            self::assertArrayHasKey('floor', $room);
        }
    }

    #[Test]
    public function itReturns201WithEmptyArrayForHeaderOnlyCsv(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $csv = $this->makeCsvFile("number,floor\n");
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var list<mixed> $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([], $body);
    }

    #[Test]
    public function itReturns404WhenHotelDoesNotExist(): void
    {
        $client = static::createClient();

        $csv = $this->makeCsvFile("number,floor\n101,1\n");
        $client->request(
            method: 'POST',
            uri: '/api/hotels/00000000-0000-4000-8000-000000000000/rooms/batch',
            files: ['csv' => $csv],
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns404WhenHotelIdIsNotUuidV4(): void
    {
        $client = static::createClient();

        $csv = $this->makeCsvFile("number,floor\n101,1\n");
        $client->request(
            method: 'POST',
            uri: '/api/hotels/not-a-uuid/rooms/batch',
            files: ['csv' => $csv],
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WithViolationsWhenDuplicateInBatch(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $csv = $this->makeCsvFile("number,floor\n101,1\n101,2\n");
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, violations: list<array{field: string, message: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-batch-invalid', $body['type']);
        self::assertCount(1, $body['violations']);
        self::assertSame('line[3]', $body['violations'][0]['field']);
    }

    #[Test]
    public function itReturns422WhenNumberAlreadyExistsInHotel(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1], \JSON_THROW_ON_ERROR),
        );

        $csv = $this->makeCsvFile("number,floor\n101,1\n102,2\n");
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{violations: list<array{field: string, message: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(1, $body['violations']);
        self::assertSame('line[2]', $body['violations'][0]['field']);
    }

    #[Test]
    public function itReturns422WithAllViolationsAtOnce(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $csv = $this->makeCsvFile("number,floor\n,1\n101,1\n101,2\n");
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{violations: list<array{field: string, message: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['violations']);
    }

    #[Test]
    public function itReturns422WhenCsvHeaderIsInvalid(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $csv = $this->makeCsvFile("number\n101\n");
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns422WhenNoCsvFileProvided(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    private function makeCsvFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rooms_') . '.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'rooms.csv', 'text/csv', null, true);
    }
}
```

- [ ] **Step 7: Run unit tests**

```bash
make unit-test-quiet
```
Expected: all PASS.

- [ ] **Step 8: Run functional tests**

```bash
make functional-test
```
Expected: all PASS. If failures, check that `make migrate` was run (Task 6 Step 2).

- [ ] **Step 9: Run static analysis**

```bash
make lint
```
Expected: no errors.

- [ ] **Step 10: Regenerate OpenAPI spec**

```bash
make openapi
```
Expected: no warnings. `openapi.yaml` updated with `floor` field in room responses and request bodies.

- [ ] **Step 11: Commit**

```bash
git add \
  src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomRequest.php \
  src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomController.php \
  src/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsController.php \
  src/Room/UI/Http/Controller/RoomSerializer.php \
  tests/Room/UI/Http/Controller/RegisterRoom/RegisterRoomControllerTest.php \
  tests/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsControllerTest.php \
  openapi.yaml
git commit -m "feat(room): expose floor in API request/response, update functional tests"
```

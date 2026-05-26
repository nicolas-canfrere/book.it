# Room Context Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the Room bounded context with three use cases — Room Registration, Get Room by ID, and Room Catalogue (paginated list).

**Architecture:** Hexagonal architecture mirroring the Hotel context (`src/Room/`). Room references Hotel by `hotelId` (UUID string) across context boundaries; hotel existence is validated via a `HotelExistsInterface` port declared in Room's domain and implemented in Room's infrastructure. Exception mappings are consolidated into a dedicated `config/services/exceptions.yaml`.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL (raw SQL, named connection `bookit`), Symfony Messenger (command/query buses), NelmioApiDocBundle, PHPUnit.

---

## File Map

**Create (src):**
- `src/Room/Domain/Model/Room.php`
- `src/Room/Domain/Model/RoomPage.php`
- `src/Room/Domain/Exception/RoomAlreadyExistsException.php`
- `src/Room/Domain/Exception/HotelNotFoundException.php`
- `src/Room/Domain/Port/RoomRepositoryInterface.php`
- `src/Room/Domain/Port/HotelExistsInterface.php`
- `src/Room/Application/Service/RoomIdGeneratorInterface.php`
- `src/Room/Application/Service/RegisterRoomCommandFactory.php`
- `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommand.php`
- `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php`
- `src/Room/Application/UseCase/GetRoom/GetRoomQuery.php`
- `src/Room/Application/UseCase/GetRoom/GetRoomQueryHandler.php`
- `src/Room/Application/UseCase/ListRooms/ListRoomsQuery.php`
- `src/Room/Application/UseCase/ListRooms/ListRoomsQueryHandler.php`
- `src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php`
- `src/Room/Infrastructure/Persistence/Doctrine/HotelExistenceChecker.php`
- `src/Room/Infrastructure/Service/RoomIdGenerator.php`
- `src/Room/UI/Http/Controller/RoomSerializer.php`
- `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomRequest.php`
- `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomController.php`
- `src/Room/UI/Http/Controller/GetRoom/GetRoomController.php`
- `src/Room/UI/Http/Controller/ListRooms/ListRoomsRequest.php`
- `src/Room/UI/Http/Controller/ListRooms/RoomCatalogueSerializer.php`
- `src/Room/UI/Http/Controller/ListRooms/ListRoomsController.php`

**Create (tests):**
- `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomRepository.php`
- `tests/Room/Infrastructure/FakeHotelExistenceChecker.php`
- `tests/Room/Application/Service/RegisterRoomCommandFactoryTest.php`
- `tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php`
- `tests/Room/Application/UseCase/ListRooms/ListRoomsQueryHandlerTest.php`
- `tests/Room/UI/Http/Controller/RegisterRoom/RegisterRoomControllerTest.php`
- `tests/Room/UI/Http/Controller/GetRoom/GetRoomControllerTest.php`
- `tests/Room/UI/Http/Controller/ListRooms/ListRoomsControllerTest.php`

**Create (config + migration):**
- `config/services/room.yaml`
- `config/services/exceptions.yaml`
- `migrations/Version20260516000000.php`

**Modify:**
- `config/services.yaml` — add `room.yaml` and `exceptions.yaml` imports
- `config/services/hotel.yaml` — remove `ExceptionProblemRegistry` definition
- `config/services/shared.yaml` — remove `ExceptionProblemRegistry` definition

---

## Task 1: Domain models, exceptions, and ports

**Files:**
- Create: `src/Room/Domain/Model/Room.php`
- Create: `src/Room/Domain/Model/RoomPage.php`
- Create: `src/Room/Domain/Exception/RoomAlreadyExistsException.php`
- Create: `src/Room/Domain/Exception/HotelNotFoundException.php`
- Create: `src/Room/Domain/Port/RoomRepositoryInterface.php`
- Create: `src/Room/Domain/Port/HotelExistsInterface.php`

- [x] **Step 1: Create `src/Room/Domain/Model/Room.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Domain\Model;

final readonly class Room
{
    public function __construct(
        public string $id,
        public string $hotelId,
        public string $number,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [x] **Step 2: Create `src/Room/Domain/Model/RoomPage.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Domain\Model;

final readonly class RoomPage
{
    /** @param list<Room> $rooms */
    public function __construct(
        public array $rooms,
        public int $total,
    ) {
    }
}
```

- [x] **Step 3: Create `src/Room/Domain/Exception/RoomAlreadyExistsException.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Domain\Exception;

final class RoomAlreadyExistsException extends \DomainException
{
    public function __construct(string $number, string $hotelId)
    {
        parent::__construct(\sprintf('A room with number "%s" already exists in hotel %s.', $number, $hotelId));
    }
}
```

- [x] **Step 4: Create `src/Room/Domain/Exception/HotelNotFoundException.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Domain\Exception;

final class HotelNotFoundException extends \DomainException
{
    public function __construct(string $hotelId)
    {
        parent::__construct(\sprintf('Hotel with id "%s" does not exist.', $hotelId));
    }
}
```

- [x] **Step 5: Create `src/Room/Domain/Port/RoomRepositoryInterface.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomPage;

interface RoomRepositoryInterface
{
    public function add(Room $room): void;

    public function get(string $id): ?Room;

    public function existsByHotelIdAndNumber(string $hotelId, string $number): bool;

    public function list(string $hotelId, int $page, int $limit): RoomPage;
}
```

- [x] **Step 6: Create `src/Room/Domain/Port/HotelExistsInterface.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

interface HotelExistsInterface
{
    public function exists(string $hotelId): bool;
}
```

- [x] **Step 7: Commit**

```bash
git add src/Room/Domain/
git commit -m "feat(room): add Room domain models, exceptions, and ports"
```

---

## Task 2: Test doubles

**Files:**
- Create: `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomRepository.php`
- Create: `tests/Room/Infrastructure/FakeHotelExistenceChecker.php`

- [x] **Step 1: Create `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomRepository.php`**

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

    public function get(string $id): ?Room
    {
        return $this->rooms[$id] ?? null;
    }

    public function existsByHotelIdAndNumber(string $hotelId, string $number): bool
    {
        foreach ($this->rooms as $room) {
            if ($room->hotelId === $hotelId && $room->number === $number) {
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

        usort($filtered, static fn(Room $a, Room $b) => strcmp($a->number, $b->number));

        $total = count($filtered);
        $rooms = array_slice($filtered, ($page - 1) * $limit, $limit);

        return new RoomPage($rooms, $total);
    }
}
```

- [x] **Step 2: Create `tests/Room/Infrastructure/FakeHotelExistenceChecker.php`**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Infrastructure;

use App\Room\Domain\Port\HotelExistsInterface;

final class FakeHotelExistenceChecker implements HotelExistsInterface
{
    private bool $hotelExists = true;

    public function setExists(bool $exists): void
    {
        $this->hotelExists = $exists;
    }

    public function exists(string $hotelId): bool
    {
        return $this->hotelExists;
    }
}
```

- [x] **Step 3: Commit**

```bash
git add tests/Room/
git commit -m "test(room): add InMemoryRoomRepository and FakeHotelExistenceChecker"
```

---

## Task 3: RegisterRoom use case (TDD integration test)

**Files:**
- Create: `src/Room/Application/Service/RoomIdGeneratorInterface.php`
- Create: `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommand.php`
- Create: `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php`
- Create: `tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php`

- [x] **Step 1: Write the failing test**

Create `tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\RegisterRoom;

use App\Room\Application\UseCase\RegisterRoom\RegisterRoomCommand;
use App\Room\Application\UseCase\RegisterRoom\RegisterRoomCommandHandler;
use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomAlreadyExistsException;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Tests\Room\Infrastructure\FakeHotelExistenceChecker;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class RegisterRoomCommandHandlerTest extends KernelTestCase
{
    private InMemoryRoomRepository $roomRepository;
    private FakeHotelExistenceChecker $hotelExistenceChecker;
    private RegisterRoomCommandHandler $handler;

    protected function setUp(): void
    {
        $this->roomRepository = new InMemoryRoomRepository();
        $this->hotelExistenceChecker = new FakeHotelExistenceChecker();
        static::getContainer()->set(RoomRepositoryInterface::class, $this->roomRepository);
        static::getContainer()->set(HotelExistsInterface::class, $this->hotelExistenceChecker);
        $this->handler = static::getContainer()->get(RegisterRoomCommandHandler::class);
    }

    #[Test]
    public function itPersistsTheRoom(): void
    {
        $command = new RegisterRoomCommand(
            id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            createdAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
        );

        ($this->handler)($command);

        $room = $this->roomRepository->get($command->id);
        self::assertNotNull($room);
        self::assertSame($command->id, $room->id);
        self::assertSame($command->hotelId, $room->hotelId);
        self::assertSame('101', $room->number);
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
            createdAt: new \DateTimeImmutable(),
        );
        ($this->handler)($command);

        $this->expectException(RoomAlreadyExistsException::class);

        ($this->handler)(new RegisterRoomCommand(
            id: 'b1ffcd00-ad1c-5fg9-cc7e-7cc0ce491b22',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
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
            createdAt: new \DateTimeImmutable(),
        );
        $command2 = new RegisterRoomCommand(
            id: 'b1ffcd00-ad1c-5fg9-cc7e-7cc0ce491b22',
            hotelId: '550e8400-e29b-41d4-a716-446655440002',
            number: '101',
            createdAt: new \DateTimeImmutable(),
        );

        ($this->handler)($command1);
        ($this->handler)($command2);

        self::assertNotNull($this->roomRepository->get($command1->id));
        self::assertNotNull($this->roomRepository->get($command2->id));
    }
}
```

- [x] **Step 2: Run test to confirm it fails (classes not yet created)**

```bash
make unit-test-quiet ARGS="--filter RegisterRoomCommandHandlerTest"
```

Expected: FAIL — `RegisterRoomCommand`, `RegisterRoomCommandHandler` not found.

- [x] **Step 3: Create `src/Room/Application/Service/RoomIdGeneratorInterface.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\Service;

interface RoomIdGeneratorInterface
{
    public function generate(): string;
}
```

- [x] **Step 4: Create `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommand.php`**

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
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [x] **Step 5: Create `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\RegisterRoom;

use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomAlreadyExistsException;
use App\Room\Domain\Model\Room;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;
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
            $command->number,
            $command->createdAt,
        ));
    }
}
```

- [x] **Step 6: Run test to confirm it passes**

```bash
make unit-test-quiet ARGS="--filter RegisterRoomCommandHandlerTest"
```

Expected: 4 tests pass.

- [x] **Step 7: Commit**

```bash
git add src/Room/Application/ tests/Room/Application/UseCase/RegisterRoom/
git commit -m "feat(room): add RegisterRoom command and handler"
```

---

## Task 4: GetRoom and ListRooms use cases

**Files:**
- Create: `src/Room/Application/UseCase/GetRoom/GetRoomQuery.php`
- Create: `src/Room/Application/UseCase/GetRoom/GetRoomQueryHandler.php`
- Create: `src/Room/Application/UseCase/ListRooms/ListRoomsQuery.php`
- Create: `src/Room/Application/UseCase/ListRooms/ListRoomsQueryHandler.php`
- Create: `tests/Room/Application/UseCase/ListRooms/ListRoomsQueryHandlerTest.php`

- [x] **Step 1: Write failing test for ListRooms**

Create `tests/Room/Application/UseCase/ListRooms/ListRoomsQueryHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\UseCase\ListRooms;

use App\Room\Application\UseCase\ListRooms\ListRoomsQuery;
use App\Room\Application\UseCase\ListRooms\ListRoomsQueryHandler;
use App\Room\Domain\Model\Room;
use App\Tests\Room\Infrastructure\Persistence\InMemory\InMemoryRoomRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ListRoomsQueryHandlerTest extends TestCase
{
    private InMemoryRoomRepository $repository;
    private ListRoomsQueryHandler $handler;
    private const string HOTEL_ID = '550e8400-e29b-41d4-a716-446655440000';

    protected function setUp(): void
    {
        $this->repository = new InMemoryRoomRepository();
        $this->handler = new ListRoomsQueryHandler($this->repository);
    }

    #[Test]
    public function itReturnsEmptyPageWhenNoRoomsExist(): void
    {
        $result = ($this->handler)(new ListRoomsQuery(self::HOTEL_ID));

        self::assertCount(0, $result->rooms);
        self::assertSame(0, $result->total);
    }

    #[Test]
    public function itReturnsRoomsSortedByNumberAscending(): void
    {
        $this->repository->add($this->makeRoom('1', self::HOTEL_ID, '202'));
        $this->repository->add($this->makeRoom('2', self::HOTEL_ID, '101'));

        $result = ($this->handler)(new ListRoomsQuery(self::HOTEL_ID));

        self::assertCount(2, $result->rooms);
        self::assertSame('101', $result->rooms[0]->number);
        self::assertSame('202', $result->rooms[1]->number);
    }

    #[Test]
    public function itOnlyReturnsRoomsForTheGivenHotel(): void
    {
        $otherHotelId = '550e8400-e29b-41d4-a716-446655440001';
        $this->repository->add($this->makeRoom('1', self::HOTEL_ID, '101'));
        $this->repository->add($this->makeRoom('2', $otherHotelId, '201'));

        $result = ($this->handler)(new ListRoomsQuery(self::HOTEL_ID));

        self::assertCount(1, $result->rooms);
        self::assertSame(1, $result->total);
        self::assertSame('101', $result->rooms[0]->number);
    }

    #[Test]
    public function itPaginatesResults(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->repository->add($this->makeRoom((string) $i, self::HOTEL_ID, \sprintf('%03d', $i)));
        }

        $result = ($this->handler)(new ListRoomsQuery(self::HOTEL_ID, page: 2, limit: 2));

        self::assertCount(2, $result->rooms);
        self::assertSame(5, $result->total);
    }

    #[Test]
    public function itReturnsCorrectTotalWhenPageExceedsResults(): void
    {
        $this->repository->add($this->makeRoom('1', self::HOTEL_ID, '101'));

        $result = ($this->handler)(new ListRoomsQuery(self::HOTEL_ID, page: 99, limit: 20));

        self::assertCount(0, $result->rooms);
        self::assertSame(1, $result->total);
    }

    private function makeRoom(string $id, string $hotelId, string $number): Room
    {
        return new Room($id, $hotelId, $number, new \DateTimeImmutable('2024-01-01'));
    }
}
```

- [x] **Step 2: Run test to confirm it fails**

```bash
make unit-test-quiet ARGS="--filter ListRoomsQueryHandlerTest"
```

Expected: FAIL — `ListRoomsQuery`, `ListRoomsQueryHandler` not found.

- [x] **Step 3: Create `src/Room/Application/UseCase/GetRoom/GetRoomQuery.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\GetRoom;

use App\Room\Domain\Model\Room;
use App\Shared\Application\Bus\SyncQueryInterface;

/**
 * @implements SyncQueryInterface<Room|null>
 */
final readonly class GetRoomQuery implements SyncQueryInterface
{
    public function __construct(
        public string $roomId,
    ) {
    }
}
```

- [x] **Step 4: Create `src/Room/Application/UseCase/GetRoom/GetRoomQueryHandler.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\GetRoom;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetRoomQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
    ) {
    }

    public function __invoke(GetRoomQuery $query): ?Room
    {
        return $this->roomRepository->get($query->roomId);
    }
}
```

- [x] **Step 5: Create `src/Room/Application/UseCase/ListRooms/ListRoomsQuery.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\ListRooms;

use App\Room\Domain\Model\RoomPage;
use App\Shared\Application\Bus\SyncQueryInterface;

/**
 * @implements SyncQueryInterface<RoomPage>
 */
final readonly class ListRoomsQuery implements SyncQueryInterface
{
    public function __construct(
        public string $hotelId,
        public int $page = 1,
        public int $limit = 20,
    ) {
    }
}
```

- [x] **Step 6: Create `src/Room/Application/UseCase/ListRooms/ListRoomsQueryHandler.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\ListRooms;

use App\Room\Domain\Model\RoomPage;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class ListRoomsQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
    ) {
    }

    public function __invoke(ListRoomsQuery $query): RoomPage
    {
        return $this->roomRepository->list($query->hotelId, $query->page, $query->limit);
    }
}
```

- [x] **Step 7: Run test to confirm it passes**

```bash
make unit-test-quiet ARGS="--filter ListRoomsQueryHandlerTest"
```

Expected: 5 tests pass.

- [x] **Step 8: Commit**

```bash
git add src/Room/Application/UseCase/GetRoom/ src/Room/Application/UseCase/ListRooms/ tests/Room/Application/UseCase/ListRooms/
git commit -m "feat(room): add GetRoom and ListRooms query handlers"
```

---

## Task 5: RegisterRoomCommandFactory (TDD unit test)

**Files:**
- Create: `src/Room/Application/Service/RegisterRoomCommandFactory.php`
- Create: `tests/Room/Application/Service/RegisterRoomCommandFactoryTest.php`

- [x] **Step 1: Write the failing test**

Create `tests/Room/Application/Service/RegisterRoomCommandFactoryTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\Service;

use App\Room\Application\Service\RegisterRoomCommandFactory;
use App\Room\Application\Service\RoomIdGeneratorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

#[Group('unit')]
final class RegisterRoomCommandFactoryTest extends TestCase
{
    private RegisterRoomCommandFactory $factory;

    protected function setUp(): void
    {
        $idGenerator = $this->createStub(RoomIdGeneratorInterface::class);
        $idGenerator->method('generate')->willReturn(Uuid::v4()->toRfc4122());

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable());

        $this->factory = new RegisterRoomCommandFactory($idGenerator, $clock);
    }

    #[Test]
    public function itThrowsWhenNumberIsNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->factory->create('550e8400-e29b-41d4-a716-446655440000', null);
    }

    #[Test]
    public function itCreatesCommandWithCorrectValues(): void
    {
        $command = $this->factory->create('550e8400-e29b-41d4-a716-446655440000', '101');

        self::assertNotEmpty($command->id);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $command->hotelId);
        self::assertSame('101', $command->number);
    }
}
```

- [x] **Step 2: Run test to confirm it fails**

```bash
make unit-test-quiet ARGS="--filter RegisterRoomCommandFactoryTest"
```

Expected: FAIL — `RegisterRoomCommandFactory` not found.

- [x] **Step 3: Create `src/Room/Application/Service/RegisterRoomCommandFactory.php`**

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

    public function create(string $hotelId, ?string $number): RegisterRoomCommand
    {
        if (null === $number) {
            throw new \InvalidArgumentException('Room number is required.');
        }

        return new RegisterRoomCommand(
            $this->roomIdGenerator->generate(),
            $hotelId,
            $number,
            $this->clock->now(),
        );
    }
}
```

- [x] **Step 4: Run test to confirm it passes**

```bash
make unit-test-quiet ARGS="--filter RegisterRoomCommandFactoryTest"
```

Expected: 2 tests pass.

- [x] **Step 5: Commit**

```bash
git add src/Room/Application/Service/ tests/Room/Application/Service/
git commit -m "feat(room): add RegisterRoomCommandFactory"
```

---

## Task 6: Doctrine migration

**Files:**
- Create: `migrations/Version20260516000000.php`

- [x] **Step 1: Create `migrations/Version20260516000000.php`**

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create room table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE room (
                id UUID NOT NULL,
                hotel_id UUID NOT NULL,
                number VARCHAR(50) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql("COMMENT ON COLUMN room.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX uniq_room_hotel_number ON room (hotel_id, number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE room');
    }
}
```

- [x] **Step 2: Commit**

```bash
git add migrations/
git commit -m "feat(room): add room table migration"
```

---

## Task 7: Doctrine infrastructure

**Files:**
- Create: `src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php`
- Create: `src/Room/Infrastructure/Persistence/Doctrine/HotelExistenceChecker.php`
- Create: `src/Room/Infrastructure/Service/RoomIdGenerator.php`

- [x] **Step 1: Create `src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomPage;
use App\Room\Domain\Port\RoomRepositoryInterface;
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
            'number' => $room->number,
            'created_at' => $room->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $id): ?Room
    {
        /** @var array{id: string, hotel_id: string, number: string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, hotel_id, number, created_at FROM room WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return new Room($row['id'], $row['hotel_id'], $row['number'], new \DateTimeImmutable($row['created_at']));
    }

    public function existsByHotelIdAndNumber(string $hotelId, string $number): bool
    {
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM room WHERE hotel_id = :hotelId AND number = :number',
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

        /** @var list<array{id: string, hotel_id: string, number: string, created_at: string}> $rows */
        $rows = $this->bookit->fetchAllAssociative(
            'SELECT id, hotel_id, number, created_at FROM room WHERE hotel_id = :hotelId ORDER BY number ASC LIMIT :limit OFFSET :offset',
            ['hotelId' => $hotelId, 'limit' => $limit, 'offset' => ($page - 1) * $limit],
        );

        $rooms = array_map(
            fn(array $row) => new Room($row['id'], $row['hotel_id'], $row['number'], new \DateTimeImmutable($row['created_at'])),
            $rows,
        );

        return new RoomPage($rooms, $total);
    }
}
```

- [x] **Step 2: Create `src/Room/Infrastructure/Persistence/Doctrine/HotelExistenceChecker.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Port\HotelExistsInterface;
use Doctrine\DBAL\Connection;

final readonly class HotelExistenceChecker implements HotelExistsInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function exists(string $hotelId): bool
    {
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM hotel WHERE id = :id',
            ['id' => $hotelId],
        );

        return $count > 0;
    }
}
```

- [x] **Step 3: Create `src/Room/Infrastructure/Service/RoomIdGenerator.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Service;

use App\Room\Application\Service\RoomIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class RoomIdGenerator implements RoomIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}
```

- [x] **Step 4: Commit**

```bash
git add src/Room/Infrastructure/
git commit -m "feat(room): add Doctrine RoomRepository, HotelExistenceChecker, RoomIdGenerator"
```

---

## Task 8: Services config

**Files:**
- Create: `config/services/room.yaml`
- Create: `config/services/exceptions.yaml`
- Modify: `config/services.yaml`
- Modify: `config/services/hotel.yaml`
- Modify: `config/services/shared.yaml`

- [x] **Step 1: Create `config/services/room.yaml`**

```yaml
parameters: {}

services:
    _defaults:
        autowire: true
        autoconfigure: true

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
```

- [x] **Step 2: Create `config/services/exceptions.yaml`**

This file centralises all exception-to-ProblemDetail mappings so context-specific files don't override each other.

```yaml
parameters: {}

services:
    App\Shared\Infrastructure\Http\ExceptionProblemRegistry:
        arguments:
            $map:
                App\Hotel\Domain\Exception\HotelAlreadyExistsException:
                    type: 'https://book.it/problems/hotel-already-exists'
                    title: 'Hotel Already Exists'
                    status: 409
                App\Room\Domain\Exception\RoomAlreadyExistsException:
                    type: 'https://book.it/problems/room-already-exists'
                    title: 'Room Already Exists'
                    status: 409
                App\Room\Domain\Exception\HotelNotFoundException:
                    type: 'https://book.it/problems/hotel-not-found'
                    title: 'Hotel Not Found'
                    status: 404
```

- [x] **Step 3: Remove `ExceptionProblemRegistry` definition from `config/services/shared.yaml`**

Remove these lines from `config/services/shared.yaml`:

```yaml
    App\Shared\Infrastructure\Http\ExceptionProblemRegistry:
        arguments:
            $map: []
```

The file should end with only:

```yaml
parameters: {}

services:
    _defaults:
        autowire: true
        autoconfigure: true
    App\Shared\:
        resource: '../../src/Shared/'
        exclude:
            - '../../src/Shared/**/*Exception.php'
```

- [x] **Step 4: Remove `ExceptionProblemRegistry` definition from `config/services/hotel.yaml`**

Remove these lines from `config/services/hotel.yaml`:

```yaml
    App\Shared\Infrastructure\Http\ExceptionProblemRegistry:
        arguments:
            $map:
                App\Hotel\Domain\Exception\HotelAlreadyExistsException:
                    type: 'https://book.it/problems/hotel-already-exists'
                    title: 'Hotel Already Exists'
                    status: 409
```

- [x] **Step 5: Update `config/services.yaml`**

```yaml
imports:
    - { resource: './services/shared.yaml' }
    - { resource: './services/hotel.yaml' }
    - { resource: './services/room.yaml' }
    - { resource: './services/exceptions.yaml' }
```

- [x] **Step 6: Verify the config compiles**

```bash
make php-cli
# inside container:
bin/console debug:container App\\Shared\\Infrastructure\\Http\\ExceptionProblemRegistry
```

Expected: service shown with 3 mapped exceptions.

- [x] **Step 7: Commit**

```bash
git add config/
git commit -m "feat(room): add room services config and consolidate exception registry"
```

---

## Task 9: UI layer — serializers and controllers

**Files:**
- Create: `src/Room/UI/Http/Controller/RoomSerializer.php`
- Create: `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomRequest.php`
- Create: `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomController.php`
- Create: `src/Room/UI/Http/Controller/GetRoom/GetRoomController.php`
- Create: `src/Room/UI/Http/Controller/ListRooms/ListRoomsRequest.php`
- Create: `src/Room/UI/Http/Controller/ListRooms/RoomCatalogueSerializer.php`
- Create: `src/Room/UI/Http/Controller/ListRooms/ListRoomsController.php`

- [x] **Step 1: Create `src/Room/UI/Http/Controller/RoomSerializer.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller;

use App\Room\Domain\Model\Room;

final class RoomSerializer
{
    /**
     * @return array{id: string, hotelId: string, number: string, createdAt: int}
     */
    public function serialize(Room $room): array
    {
        return [
            'id' => $room->id,
            'hotelId' => $room->hotelId,
            'number' => $room->number,
            'createdAt' => $room->createdAt->getTimestamp(),
        ];
    }
}
```

- [x] **Step 2: Create `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomRequest.php`**

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
    ) {
    }
}
```

- [x] **Step 3: Create `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomController.php`**

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
        $command = $this->commandFactory->create($hotelId, $request->number);
        $this->commandBus->execute($command);

        $room = $this->queryBus->ask(new GetRoomQuery($command->id));
        if (null === $room) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->roomSerializer->serialize($room), Response::HTTP_CREATED);
    }
}
```

- [x] **Step 4: Create `src/Room/UI/Http/Controller/GetRoom/GetRoomController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\GetRoom;

use App\Room\Application\UseCase\GetRoom\GetRoomQuery;
use App\Room\UI\Http\Controller\RoomSerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetRoomController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private RoomSerializer $roomSerializer,
    ) {
    }

    #[Route('/api/rooms/{id}', name: 'room_get_room', requirements: ['id' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a room by ID',
        tags: ['Rooms'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Room found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'hotelId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'number', type: 'string', example: '101'),
                        new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Room not found',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
                ),
            ),
        ],
    )]
    public function __invoke(string $id): Response
    {
        $room = $this->queryBus->ask(new GetRoomQuery($id));

        if (null === $room) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->roomSerializer->serialize($room));
    }
}
```

- [x] **Step 5: Create `src/Room/UI/Http/Controller/ListRooms/ListRoomsRequest.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\ListRooms;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListRoomsRequest
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

- [x] **Step 6: Create `src/Room/UI/Http/Controller/ListRooms/RoomCatalogueSerializer.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\ListRooms;

use App\Room\Domain\Model\RoomPage;
use App\Room\UI\Http\Controller\RoomSerializer;

final class RoomCatalogueSerializer
{
    public function __construct(private RoomSerializer $roomSerializer)
    {
    }

    /**
     * @return array{
     *     data: list<array{id: string, hotelId: string, number: string, createdAt: int}>,
     *     meta: array{page: int, limit: int, total: int, totalPages: int}
     * }
     */
    public function serialize(RoomPage $roomPage, int $page, int $limit): array
    {
        return [
            'data' => array_map($this->roomSerializer->serialize(...), $roomPage->rooms),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $roomPage->total,
                'totalPages' => (int) ceil($roomPage->total / $limit),
            ],
        ];
    }
}
```

- [x] **Step 7: Create `src/Room/UI/Http/Controller/ListRooms/ListRoomsController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\ListRooms;

use App\Room\Application\UseCase\ListRooms\ListRoomsQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class ListRoomsController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private RoomCatalogueSerializer $serializer,
    ) {
    }

    #[Route('/api/hotels/{hotelId}/rooms', name: 'room_list_rooms', requirements: ['hotelId' => Requirement::UUID_V4], methods: ['GET'])]
    #[OA\Get(
        summary: 'List rooms of a hotel (paginated)',
        tags: ['Rooms'],
        parameters: [
            new OA\Parameter(name: 'hotelId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Paginated room catalogue',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'hotelId', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'number', type: 'string'),
                                    new OA\Property(property: 'createdAt', type: 'integer'),
                                ],
                                type: 'object',
                            ),
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [
                                new OA\Property(property: 'page', type: 'integer', example: 1),
                                new OA\Property(property: 'limit', type: 'integer', example: 20),
                                new OA\Property(property: 'total', type: 'integer', example: 10),
                                new OA\Property(property: 'totalPages', type: 'integer', example: 1),
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
        string $hotelId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ListRoomsRequest $request = new ListRoomsRequest(),
    ): Response {
        $roomPage = $this->queryBus->ask(new ListRoomsQuery(
            $hotelId,
            $request->page,
            $request->limit,
        ));

        return new JsonResponse(
            $this->serializer->serialize($roomPage, $request->page, $request->limit),
        );
    }
}
```

- [x] **Step 8: Commit**

```bash
git add src/Room/UI/
git commit -m "feat(room): add Room UI controllers and serializers"
```

---

## Task 10: Functional tests — RegisterRoom

**Files:**
- Create: `tests/Room/UI/Http/Controller/RegisterRoom/RegisterRoomControllerTest.php`

- [x] **Step 1: Run migration**

```bash
make migrate
```

Expected: migration `Version20260516000000` applied.

- [x] **Step 2: Write the failing functional test**

Create `tests/Room/UI/Http/Controller/RegisterRoom/RegisterRoomControllerTest.php`:

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
    #[Test]
    public function itRegistersARoomAndReturns201(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101'], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{id: string, hotelId: string, number: string, createdAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['id']);
        self::assertSame($hotelId, $body['hotelId']);
        self::assertSame('101', $body['number']);
        self::assertGreaterThan(0, $body['createdAt']);
    }

    #[Test]
    public function itReturns409WhenRoomAlreadyExists(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);
        $payload = json_encode(['number' => '101'], \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/hotels/{$hotelId}/rooms", server: ['CONTENT_TYPE' => 'application/json'], content: $payload);
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request('POST', "/api/hotels/{$hotelId}/rooms", server: ['CONTENT_TYPE' => 'application/json'], content: $payload);

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int, detail: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-already-exists', $body['type']);
        self::assertSame('Room Already Exists', $body['title']);
        self::assertSame(Response::HTTP_CONFLICT, $body['status']);
        self::assertNotEmpty($body['detail']);
    }

    #[Test]
    public function itReturns404WhenHotelDoesNotExist(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/hotels/550e8400-e29b-41d4-a716-446655440000/rooms',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101'], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
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
            content: json_encode(['number' => '101'], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenNumberIsMissing(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    private function registerHotel(KernelBrowser $client): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Hotel Test',
                'streetAddress' => '1 rue de la Paix',
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
            ], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}
```

- [x] **Step 3: Run test to confirm it fails**

```bash
make functional-test ARGS="--filter RegisterRoomControllerTest"
```

Expected: FAIL — route not found or service wiring issue.

- [x] **Step 4: Run test to confirm it passes**

After verifying the config from Task 8 is applied:

```bash
make functional-test ARGS="--filter RegisterRoomControllerTest"
```

Expected: 5 tests pass.

- [x] **Step 5: Commit**

```bash
git add tests/Room/UI/Http/Controller/RegisterRoom/
git commit -m "test(room): add RegisterRoom functional tests"
```

---

## Task 11: Functional tests — GetRoom

**Files:**
- Create: `tests/Room/UI/Http/Controller/GetRoom/GetRoomControllerTest.php`

- [x] **Step 1: Write the test**

Create `tests/Room/UI/Http/Controller/GetRoom/GetRoomControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\GetRoom;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetRoomControllerTest extends WebTestCase
{
    #[Test]
    public function itReturns200WithCorrectRoomShape(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);
        $roomId = $this->registerRoom($client, $hotelId, '101');

        $client->request('GET', "/api/rooms/{$roomId}");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{id: string, hotelId: string, number: string, createdAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($roomId, $body['id']);
        self::assertSame($hotelId, $body['hotelId']);
        self::assertSame('101', $body['number']);
        self::assertGreaterThan(0, $body['createdAt']);
    }

    #[Test]
    public function itReturns404WhenRoomDoesNotExist(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rooms/550e8400-e29b-41d4-a716-446655440000');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns404WhenIdIsNotAValidUuidV4(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rooms/not-a-uuid');
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    private function registerHotel(KernelBrowser $client): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Hotel Test',
                'streetAddress' => '1 rue de la Paix',
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
            ], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    private function registerRoom(KernelBrowser $client, string $hotelId, string $number): string
    {
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => $number], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}
```

- [x] **Step 2: Run the test**

```bash
make functional-test ARGS="--filter GetRoomControllerTest"
```

Expected: 3 tests pass.

- [x] **Step 3: Commit**

```bash
git add tests/Room/UI/Http/Controller/GetRoom/
git commit -m "test(room): add GetRoom functional tests"
```

---

## Task 12: Functional tests — ListRooms

**Files:**
- Create: `tests/Room/UI/Http/Controller/ListRooms/ListRoomsControllerTest.php`

- [x] **Step 1: Write the test**

Create `tests/Room/UI/Http/Controller/ListRooms/ListRoomsControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\ListRooms;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class ListRoomsControllerTest extends WebTestCase
{
    #[Test]
    public function itReturns200WithEmptyDataWhenNoRoomsExist(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);

        $client->request('GET', "/api/hotels/{$hotelId}/rooms");

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
    public function itReturnsRoomsSortedByNumberAscending(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);

        $this->registerRoom($client, $hotelId, '202');
        $this->registerRoom($client, $hotelId, '101');

        $client->request('GET', "/api/hotels/{$hotelId}/rooms");

        /** @var array{data: list<array{number: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(2, $body['meta']['total']);
        self::assertSame('101', $body['data'][0]['number']);
        self::assertSame('202', $body['data'][1]['number']);
    }

    #[Test]
    public function itReturnsCorrectRoomShape(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);
        $this->registerRoom($client, $hotelId, '101');

        $client->request('GET', "/api/hotels/{$hotelId}/rooms");

        /** @var array{data: list<array{id: string, hotelId: string, number: string, createdAt: int}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $room = $body['data'][0];
        self::assertNotEmpty($room['id']);
        self::assertSame($hotelId, $room['hotelId']);
        self::assertSame('101', $room['number']);
        self::assertGreaterThan(0, $room['createdAt']);
    }

    #[Test]
    public function itOnlyReturnsRoomsForTheGivenHotel(): void
    {
        $client = static::createClient();
        $hotel1Id = $this->registerHotel($client, 'Hotel One', '1 rue Alpha');
        $hotel2Id = $this->registerHotel($client, 'Hotel Two', '2 rue Beta');

        $this->registerRoom($client, $hotel1Id, '101');
        $this->registerRoom($client, $hotel2Id, '201');

        $client->request('GET', "/api/hotels/{$hotel1Id}/rooms");

        /** @var array{data: list<array{number: string}>, meta: array{total: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['meta']['total']);
        self::assertSame('101', $body['data'][0]['number']);
    }

    #[Test]
    public function itPaginatesWithDefaultPageSize(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);

        for ($i = 1; $i <= 25; ++$i) {
            $this->registerRoom($client, $hotelId, \sprintf('%03d', $i));
        }

        $client->request('GET', "/api/hotels/{$hotelId}/rooms");

        /** @var array{data: list<mixed>, meta: array{total: int, totalPages: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(20, $body['data']);
        self::assertSame(25, $body['meta']['total']);
        self::assertSame(2, $body['meta']['totalPages']);
    }

    #[Test]
    public function itReturnsSecondPage(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);

        for ($i = 1; $i <= 5; ++$i) {
            $this->registerRoom($client, $hotelId, \sprintf('%03d', $i));
        }

        $client->request('GET', "/api/hotels/{$hotelId}/rooms?page=2&limit=2");

        /** @var array{data: list<array{number: string}>, meta: array{page: int, total: int, totalPages: int}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['data']);
        self::assertSame(2, $body['meta']['page']);
        self::assertSame(5, $body['meta']['total']);
        self::assertSame(3, $body['meta']['totalPages']);
        self::assertSame('003', $body['data'][0]['number']);
        self::assertSame('004', $body['data'][1]['number']);
    }

    #[Test]
    public function itReturns422WhenPageIsZero(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotel($client);

        $client->request('GET', "/api/hotels/{$hotelId}/rooms?page=0");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns404WhenHotelIdIsNotAValidUuidV4(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/hotels/not-a-uuid/rooms');
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    private function registerHotel(KernelBrowser $client, string $name = 'Hotel Test', string $streetAddress = '1 rue de la Paix'): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => $name,
                'streetAddress' => $streetAddress,
                'postalCode' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
            ], \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    private function registerRoom(KernelBrowser $client, string $hotelId, string $number): void
    {
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => $number], \JSON_THROW_ON_ERROR),
        );
    }
}
```

- [x] **Step 2: Run the test**

```bash
make functional-test ARGS="--filter ListRoomsControllerTest"
```

Expected: 8 tests pass.

- [x] **Step 3: Run the full test suite**

```bash
make test
```

Expected: all tests pass (unit + integration + functional).

- [x] **Step 4: Run linter**

```bash
make lint
```

Expected: no PHPStan or CS Fixer errors.

- [x] **Step 5: Commit**

```bash
git add tests/Room/UI/Http/Controller/ListRooms/
git commit -m "test(room): add ListRooms functional tests"
```

---

## Task 13: Regenerate OpenAPI spec

- [x] **Step 1: Regenerate**

```bash
make openapi
```

Expected: `openapi.yaml` updated, no warnings.

- [x] **Step 2: Commit**

```bash
git add openapi.yaml
git commit -m "docs(room): regenerate OpenAPI spec with Room endpoints"
```

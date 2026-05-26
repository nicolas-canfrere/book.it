# Room Types Plan B — Room ← RoomType Wiring

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire Room Type as a mandatory field on every Room — the `room_type_id` column on the `room` table is added, every Room Registration requires a valid Room Type from the same hotel, and `roomTypeId` appears in all Room API responses.

**Architecture:** Plan A established the `room_type` catalogue (table + CRUD endpoints). Plan B (this plan) adds `roomTypeId` to the `Room` domain model, propagates it through commands, factories, handlers, the DBAL repository, and the UI layer. A new `RoomTypeExistsInterface` port guards existence at registration time. The `RoomTypeHasRoomsChecker` stub (which always returned `false`) is replaced with a real query, finally enabling the 409 guard on `DeleteRoomType`.

**Tech Stack:** PHP 8.4, Symfony 8.0, PostgreSQL 16, Doctrine DBAL, PHPUnit

---

## File map

### New files

| Path | Purpose |
|------|---------|
| `src/Room/Domain/Port/RoomTypeExistsInterface.php` | Port — checks whether a Room Type id exists |
| `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeExistenceChecker.php` | DBAL implementation of `RoomTypeExistsInterface` |
| `tests/Room/Infrastructure/FakeRoomTypeExistenceChecker.php` | Test double for `RoomTypeExistsInterface` |

### Modified files

| Path | What changes |
|------|-------------|
| `src/Room/Domain/Model/Room.php` | Add `roomTypeId: string` constructor param (between `floor` and `createdAt`) |
| `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommand.php` | Add `roomTypeId: string` |
| `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php` | Inject `RoomTypeExistsInterface`, add existence check, pass `roomTypeId` to `Room` |
| `src/Room/Application/Service/RegisterRoomCommandFactory.php` | Add `roomTypeId: string` param |
| `src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommand.php` | Add `roomTypeId: string` (top-level, before `entries`) |
| `src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandler.php` | Inject `RoomTypeExistsInterface`, add existence check, pass `roomTypeId` to `Room` |
| `src/Room/Application/Service/BatchRegisterRoomsCommandFactory.php` | Add `roomTypeId: string` param |
| `src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php` | Add `room_type_id` to INSERT, SELECT, and `Room` reconstruction |
| `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeHasRoomsChecker.php` | Replace stub with real `SELECT COUNT(*) FROM room WHERE room_type_id = :id` |
| `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomRequest.php` | Add `roomTypeId: ?string` with UUID v4 + NotBlank validation |
| `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomController.php` | Pass `request->roomTypeId` to factory; update OA response + add 404 for Room Type |
| `src/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsController.php` | Read `roomTypeId` multipart field + validate; pass to factory; update `Room` construction for response; update OA schema |
| `src/Room/UI/Http/Controller/RoomSerializer.php` | Add `roomTypeId` to serialized output |
| `config/services/room.yaml` | Wire `RoomTypeExistsInterface → RoomTypeExistenceChecker` |
| `tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php` | Add `FakeRoomTypeExistenceChecker`, update commands + ctor, add roomType 404 test |
| `tests/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandlerTest.php` | Add `FakeRoomTypeExistenceChecker`, update commands + ctor, add roomType 404 test |
| `tests/Room/UI/Http/Controller/RegisterRoom/RegisterRoomControllerTest.php` | Add `roomTypeId` to all payloads, add helper, add roomType-not-found test, assert `roomTypeId` in response |
| `tests/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsControllerTest.php` | Add `roomTypeId` multipart field, add helper, update response assertions |
| `tests/Room/UI/Http/Controller/DeleteRoomType/DeleteRoomTypeControllerTest.php` | Add 409 test for "Room Type has Rooms" |

---

## Task 1: Structural foundation — add `roomTypeId` throughout (make it compile)

This task makes all compilation-level changes atomically. After this task, unit tests pass. Functional tests will fail until Task 4 (migration) and Task 5 (config) complete — that is expected.

**Files:**
- Modify: `src/Room/Domain/Model/Room.php`
- Create: `src/Room/Domain/Port/RoomTypeExistsInterface.php`
- Modify: `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommand.php`
- Modify: `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php`
- Modify: `src/Room/Application/Service/RegisterRoomCommandFactory.php`
- Modify: `src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommand.php`
- Modify: `src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandler.php`
- Modify: `src/Room/Application/Service/BatchRegisterRoomsCommandFactory.php`
- Modify: `src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php`
- Modify: `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomRequest.php`
- Modify: `src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomController.php`
- Modify: `src/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsController.php`
- Modify: `src/Room/UI/Http/Controller/RoomSerializer.php`
- Create: `tests/Room/Infrastructure/FakeRoomTypeExistenceChecker.php`
- Modify: `tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php`
- Modify: `tests/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandlerTest.php`

- [ ] **Step 1: Add `roomTypeId` to the `Room` model**

```php
// src/Room/Domain/Model/Room.php
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
        public string $roomTypeId,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 2: Create `RoomTypeExistsInterface` port**

```php
// src/Room/Domain/Port/RoomTypeExistsInterface.php
<?php

declare(strict_types=1);

namespace App\Room\Domain\Port;

interface RoomTypeExistsInterface
{
    public function exists(string $roomTypeId): bool;
}
```

- [ ] **Step 3: Add `roomTypeId` to `RegisterRoomCommand`**

```php
// src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommand.php
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
        public string $roomTypeId,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 4: Update `RegisterRoomCommandHandler` — pass `roomTypeId` to `Room` (no existence check yet)**

```php
// src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php
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
            $command->roomTypeId,
            $command->createdAt,
        ));
    }
}
```

- [ ] **Step 5: Update `RegisterRoomCommandFactory` — add `roomTypeId` param**

```php
// src/Room/Application/Service/RegisterRoomCommandFactory.php
<?php

declare(strict_types=1);

namespace App\Room\Application\Service;

use App\Room\Application\UseCase\RegisterRoom\RegisterRoomCommand;
use App\Room\Domain\Port\RoomIdGeneratorInterface;
use Psr\Clock\ClockInterface;

final readonly class RegisterRoomCommandFactory
{
    public function __construct(
        private RoomIdGeneratorInterface $roomIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(string $hotelId, ?string $number, ?int $floor, ?string $roomTypeId): RegisterRoomCommand
    {
        if (null === $number) {
            throw new \InvalidArgumentException('Room number is required.');
        }
        if (null === $floor) {
            throw new \InvalidArgumentException('Room floor is required.');
        }
        if (null === $roomTypeId) {
            throw new \InvalidArgumentException('Room type ID is required.');
        }

        return new RegisterRoomCommand(
            $this->roomIdGenerator->generate(),
            $hotelId,
            $number,
            $floor,
            $roomTypeId,
            $this->clock->now(),
        );
    }
}
```

- [ ] **Step 6: Add `roomTypeId` to `BatchRegisterRoomsCommand`**

```php
// src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommand.php
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
        public string $roomTypeId,
        public array $entries,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 7: Update `BatchRegisterRoomsCommandHandler` — pass `roomTypeId` to `Room` (no existence check yet)**

```php
// src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandler.php
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
            $number = trim($entry['number']);
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
                $command->roomTypeId,
                $command->createdAt,
            ),
            $command->entries,
        );

        $this->roomRepository->addAll($rooms);
    }
}
```

- [ ] **Step 8: Update `BatchRegisterRoomsCommandFactory` — add `roomTypeId` param**

```php
// src/Room/Application/Service/BatchRegisterRoomsCommandFactory.php
<?php

declare(strict_types=1);

namespace App\Room\Application\Service;

use App\Room\Application\UseCase\BatchRegisterRooms\BatchRegisterRoomsCommand;
use App\Room\Domain\Port\RoomIdGeneratorInterface;
use Psr\Clock\ClockInterface;

final readonly class BatchRegisterRoomsCommandFactory
{
    public function __construct(
        private RoomIdGeneratorInterface $roomIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    /** @param list<RoomCsvRow> $rows */
    public function create(string $hotelId, string $roomTypeId, array $rows): BatchRegisterRoomsCommand
    {
        $entries = array_map(
            fn(RoomCsvRow $row) => [
                'id' => $this->roomIdGenerator->generate(),
                'number' => trim($row->number),
                'floor' => $row->floor,
            ],
            $rows,
        );

        return new BatchRegisterRoomsCommand($hotelId, $roomTypeId, $entries, $this->clock->now());
    }
}
```

- [ ] **Step 9: Update `RoomRepository` — add `room_type_id` everywhere**

The SELECT queries gain `room_type_id`. The INSERT adds the column. The `Room` reconstruction passes `$row['room_type_id']`.

```php
// src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php
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
            'room_type_id' => $room->roomTypeId,
            'created_at' => $room->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function addAll(array $rooms): void
    {
        $this->bookit->transactional(static function () use ($rooms, &$bookit): void {
            foreach ($rooms as $room) {
                $bookit->insert('room', [
                    'id' => $room->id,
                    'hotel_id' => $room->hotelId,
                    'room_number' => $room->number->value,
                    'room_floor' => $room->floor->value,
                    'room_type_id' => $room->roomTypeId,
                    'created_at' => $room->createdAt->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }

    public function get(string $id): ?Room
    {
        /** @var array{id: string, hotel_id: string, room_number: string, room_floor: int|string, room_type_id: string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, hotel_id, room_number, room_floor, room_type_id, created_at FROM room WHERE id = :id',
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
            $row['room_type_id'],
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

        /** @var list<array{id: string, hotel_id: string, room_number: string, room_floor: int|string, room_type_id: string, created_at: string}> $rows */
        $rows = $this->bookit->fetchAllAssociative(
            'SELECT id, hotel_id, room_number, room_floor, room_type_id, created_at FROM room WHERE hotel_id = :hotelId ORDER BY room_number ASC LIMIT :limit OFFSET :offset',
            ['hotelId' => $hotelId, 'limit' => $limit, 'offset' => ($page - 1) * $limit],
        );

        $rooms = array_map(
            fn(array $row) => new Room(
                $row['id'],
                $row['hotel_id'],
                new RoomNumber($row['room_number']),
                new RoomFloor((int) $row['room_floor']),
                $row['room_type_id'],
                new \DateTimeImmutable($row['created_at']),
            ),
            $rows,
        );

        return new RoomPage($rooms, $total);
    }
}
```

> **Note:** `addAll` uses a `static` closure with `$bookit` passed by reference. Check if the existing pattern in the repo uses `$this->bookit` inside the closure — if so, remove the `static` keyword and capture `$this->bookit` directly. The CLAUDE.md constraint is that `transactional()` must receive a `Closure`, not a callable.

Actually, looking at the original implementation more carefully, `$this->bookit` is accessible from within the closure via `use`. Replace Step 9's `addAll` with:

```php
    public function addAll(array $rooms): void
    {
        $bookit = $this->bookit;
        $this->bookit->transactional(static function () use ($bookit, $rooms): void {
            foreach ($rooms as $room) {
                $bookit->insert('room', [
                    'id' => $room->id,
                    'hotel_id' => $room->hotelId,
                    'room_number' => $room->number->value,
                    'room_floor' => $room->floor->value,
                    'room_type_id' => $room->roomTypeId,
                    'created_at' => $room->createdAt->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }
```

- [ ] **Step 10: Update `RegisterRoomRequest` — add `roomTypeId` field**

```php
// src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomRequest.php
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
        #[OA\Property(type: 'integer', example: 1, minimum: -20, maximum: 300, nullable: false)]
        public ?int $floor = null,
        #[Assert\NotBlank]
        #[Assert\Uuid(versions: [4])]
        #[OA\Property(type: 'string', format: 'uuid', example: '7f4d1234-0000-4000-8000-000000000001')]
        public ?string $roomTypeId = null,
    ) {
    }
}
```

- [ ] **Step 11: Update `RegisterRoomController` — pass `roomTypeId` to factory + update OA annotations**

```php
// src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomController.php
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

    #[Route('/hotels/{hotelId}/rooms', name: 'room_register_room', requirements: ['hotelId' => Requirement::UUID_V4], methods: ['POST'])]
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
                        new OA\Property(property: 'roomTypeId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Hotel not found or Room Type not found',
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
        $command = $this->commandFactory->create($hotelId, $request->number, $request->floor, $request->roomTypeId);
        $this->commandBus->execute($command);

        $room = $this->queryBus->ask(new GetRoomQuery($command->id));
        if (null === $room) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->roomSerializer->serialize($room), Response::HTTP_CREATED);
    }
}
```

- [ ] **Step 12: Update `BatchRegisterRoomsController` — read `roomTypeId` from multipart, update OA, update `Room` construction**

```php
// src/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsController.php
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

    #[Route('/hotels/{hotelId}/rooms/batch', name: 'room_batch_register_rooms', requirements: ['hotelId' => Requirement::UUID_V4], methods: ['POST'])]
    #[OA\Post(
        summary: 'Import multiple rooms in a hotel from a CSV file',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['csv', 'roomTypeId'],
                    properties: [
                        new OA\Property(
                            property: 'roomTypeId',
                            description: 'UUID v4 of the Room Type to assign to all imported rooms',
                            type: 'string',
                            format: 'uuid',
                        ),
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
                            new OA\Property(property: 'roomTypeId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                        ],
                    ),
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Hotel not found or Room Type not found',
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
        $roomTypeId = $request->request->get('roomTypeId');
        if (!is_string($roomTypeId) || 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $roomTypeId)) {
            throw new InvalidCsvFormatException('roomTypeId must be a valid UUID v4.');
        }

        $file = $request->files->get('csv');
        if (!$file instanceof UploadedFile) {
            throw new InvalidCsvFormatException('A CSV file is required.');
        }

        $rows = $this->csvParser->parse($file->getPathname());

        $command = $this->commandFactory->create($hotelId, $roomTypeId, $rows);

        $this->commandBus->execute($command);

        $rooms = array_map(
            fn(array $entry) => $this->roomSerializer->serialize(
                new Room(
                    $entry['id'],
                    $command->hotelId,
                    new RoomNumber($entry['number']),
                    new RoomFloor($entry['floor']),
                    $command->roomTypeId,
                    $command->createdAt,
                )
            ),
            $command->entries,
        );

        return new JsonResponse($rooms, Response::HTTP_CREATED);
    }
}
```

- [ ] **Step 13: Update `RoomSerializer` — add `roomTypeId` to output**

```php
// src/Room/UI/Http/Controller/RoomSerializer.php
<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller;

use App\Room\Domain\Model\Room;

final class RoomSerializer
{
    /**
     * @return array{id: string, hotelId: string, number: string, floor: int, roomTypeId: string, createdAt: int}
     */
    public function serialize(Room $room): array
    {
        return [
            'id' => $room->id,
            'hotelId' => $room->hotelId,
            'number' => $room->number->value,
            'floor' => $room->floor->value,
            'roomTypeId' => $room->roomTypeId,
            'createdAt' => $room->createdAt->getTimestamp(),
        ];
    }
}
```

- [ ] **Step 14: Create `FakeRoomTypeExistenceChecker` test double**

```php
// tests/Room/Infrastructure/FakeRoomTypeExistenceChecker.php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Infrastructure;

use App\Room\Domain\Port\RoomTypeExistsInterface;

final class FakeRoomTypeExistenceChecker implements RoomTypeExistsInterface
{
    private bool $roomTypeExists = true;

    public function setExists(bool $exists): void
    {
        $this->roomTypeExists = $exists;
    }

    public function exists(string $roomTypeId): bool
    {
        return $this->roomTypeExists;
    }
}
```

- [ ] **Step 15: Update `RegisterRoomCommandHandlerTest` — add `roomTypeId` to commands, keep existing tests passing**

```php
// tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php
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
    private const string ROOM_TYPE_ID = 'cccccccc-0000-4000-8000-000000000001';

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
            roomTypeId: self::ROOM_TYPE_ID,
            createdAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
        );

        ($this->handler)($command);

        $room = $this->roomRepository->get($command->id);
        self::assertNotNull($room);
        self::assertSame($command->id, $room->id);
        self::assertSame($command->hotelId, $room->hotelId);
        self::assertSame('101', $room->number->value);
        self::assertSame(1, $room->floor->value);
        self::assertSame(self::ROOM_TYPE_ID, $room->roomTypeId);
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
            roomTypeId: self::ROOM_TYPE_ID,
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
            roomTypeId: self::ROOM_TYPE_ID,
            createdAt: new \DateTimeImmutable(),
        );
        ($this->handler)($command);

        $this->expectException(RoomAlreadyExistsException::class);

        ($this->handler)(new RegisterRoomCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            hotelId: '550e8400-e29b-41d4-a716-446655440000',
            number: '101',
            floor: 2,
            roomTypeId: self::ROOM_TYPE_ID,
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
            roomTypeId: self::ROOM_TYPE_ID,
            createdAt: new \DateTimeImmutable(),
        );
        $command2 = new RegisterRoomCommand(
            id: 'b1ffcd00-ad1c-4ef9-cc7e-7cc0ce491b22',
            hotelId: '550e8400-e29b-41d4-a716-446655440002',
            number: '101',
            floor: 1,
            roomTypeId: self::ROOM_TYPE_ID,
            createdAt: new \DateTimeImmutable(),
        );

        ($this->handler)($command1);
        ($this->handler)($command2);

        self::assertNotNull($this->roomRepository->get($command1->id));
        self::assertNotNull($this->roomRepository->get($command2->id));
    }
}
```

- [ ] **Step 16: Update `BatchRegisterRoomsCommandHandlerTest` — add `roomTypeId` to commands**

```php
// tests/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandlerTest.php
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
    private const string ROOM_TYPE_ID = 'cccccccc-0000-4000-8000-000000000001';

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
            roomTypeId: self::ROOM_TYPE_ID,
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
        self::assertSame(self::ROOM_TYPE_ID, $room1->roomTypeId);
    }

    #[Test]
    public function itSucceedsWithEmptyBatch(): void
    {
        $command = new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            roomTypeId: self::ROOM_TYPE_ID,
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
            roomTypeId: self::ROOM_TYPE_ID,
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
            roomTypeId: self::ROOM_TYPE_ID,
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
            roomTypeId: self::ROOM_TYPE_ID,
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
            roomTypeId: self::ROOM_TYPE_ID,
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
            roomTypeId: self::ROOM_TYPE_ID,
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
                roomTypeId: self::ROOM_TYPE_ID,
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
            roomTypeId: self::ROOM_TYPE_ID,
            entries: [['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '101', 'floor' => 1]],
            createdAt: new \DateTimeImmutable(),
        ));

        $exception = null;
        try {
            ($this->handler)(new BatchRegisterRoomsCommand(
                hotelId: self::HOTEL_ID,
                roomTypeId: self::ROOM_TYPE_ID,
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
                roomTypeId: self::ROOM_TYPE_ID,
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
                roomTypeId: self::ROOM_TYPE_ID,
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

- [ ] **Step 17: Run unit tests**

```bash
make unit-test
```

Expected: all unit tests PASS (no DB required). Functional tests will fail until Tasks 4 and 5 — do not investigate those failures now.

- [ ] **Step 18: Commit**

```bash
git add src/Room/Domain/Model/Room.php \
        src/Room/Domain/Port/RoomTypeExistsInterface.php \
        src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommand.php \
        src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php \
        src/Room/Application/Service/RegisterRoomCommandFactory.php \
        src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommand.php \
        src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandler.php \
        src/Room/Application/Service/BatchRegisterRoomsCommandFactory.php \
        src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php \
        src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomRequest.php \
        src/Room/UI/Http/Controller/RegisterRoom/RegisterRoomController.php \
        src/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsController.php \
        src/Room/UI/Http/Controller/RoomSerializer.php \
        tests/Room/Infrastructure/FakeRoomTypeExistenceChecker.php \
        tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php \
        tests/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandlerTest.php
git commit -m "feat(room): add roomTypeId to Room model and propagate through command/handler/UI stack"
```

---

## Task 2: TDD — `roomTypeId` existence guard in handlers

**Files:**
- Modify: `src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php`
- Modify: `src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandler.php`
- Modify: `tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php`
- Modify: `tests/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandlerTest.php`

- [ ] **Step 1: Add `FakeRoomTypeExistenceChecker` to `RegisterRoomCommandHandlerTest` setUp + write failing test**

Update `setUp()`:
```php
private FakeRoomTypeExistenceChecker $roomTypeExistenceChecker;

protected function setUp(): void
{
    $this->roomRepository = new InMemoryRoomRepository();
    $this->hotelExistenceChecker = new FakeHotelExistenceChecker();
    $this->roomTypeExistenceChecker = new FakeRoomTypeExistenceChecker();
    $this->handler = new RegisterRoomCommandHandler(
        $this->roomRepository,
        $this->hotelExistenceChecker,
        $this->roomTypeExistenceChecker,
    );
}
```

Add this test:
```php
#[Test]
public function itThrowsWhenRoomTypeDoesNotExist(): void
{
    $this->roomTypeExistenceChecker->setExists(false);
    $this->expectException(\App\Room\Domain\Exception\RoomTypeNotFoundException::class);

    ($this->handler)(new RegisterRoomCommand(
        id: 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
        hotelId: '550e8400-e29b-41d4-a716-446655440000',
        number: '101',
        floor: 1,
        roomTypeId: self::ROOM_TYPE_ID,
        createdAt: new \DateTimeImmutable(),
    ));
}
```

Add the `use` statement at top:
```php
use App\Tests\Room\Infrastructure\FakeRoomTypeExistenceChecker;
```

- [ ] **Step 2: Run the new test to confirm it fails**

```bash
make unit-test
```

Expected: FAIL — `RegisterRoomCommandHandler::__construct()` does not accept a third argument yet.

- [ ] **Step 3: Add `RoomTypeExistsInterface` to `RegisterRoomCommandHandler` and add the guard**

```php
// src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php
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

final readonly class RegisterRoomCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
        private HotelExistsInterface $hotelExists,
        private RoomTypeExistsInterface $roomTypeExists,
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
    }
}
```

- [ ] **Step 4: Run unit tests for RegisterRoom**

```bash
make unit-test
```

Expected: all tests PASS.

- [ ] **Step 5: Add `FakeRoomTypeExistenceChecker` to `BatchRegisterRoomsCommandHandlerTest` setUp + write failing test**

Update `setUp()`:
```php
private FakeRoomTypeExistenceChecker $roomTypeExistenceChecker;

protected function setUp(): void
{
    $this->roomRepository = new InMemoryRoomRepository();
    $this->hotelExistenceChecker = new FakeHotelExistenceChecker();
    $this->roomTypeExistenceChecker = new FakeRoomTypeExistenceChecker();
    $this->handler = new BatchRegisterRoomsCommandHandler(
        $this->roomRepository,
        $this->hotelExistenceChecker,
        $this->roomTypeExistenceChecker,
    );
}
```

Add these `use` statements:
```php
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Tests\Room\Infrastructure\FakeRoomTypeExistenceChecker;
```

Add this test:
```php
#[Test]
public function itThrowsWhenRoomTypeDoesNotExist(): void
{
    $this->roomTypeExistenceChecker->setExists(false);
    $this->expectException(RoomTypeNotFoundException::class);

    ($this->handler)(new BatchRegisterRoomsCommand(
        hotelId: self::HOTEL_ID,
        roomTypeId: self::ROOM_TYPE_ID,
        entries: [['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '101', 'floor' => 1]],
        createdAt: new \DateTimeImmutable(),
    ));
}
```

- [ ] **Step 6: Run tests to confirm the batch test fails**

```bash
make unit-test
```

Expected: FAIL — `BatchRegisterRoomsCommandHandler::__construct()` does not accept a third argument yet.

- [ ] **Step 7: Add `RoomTypeExistsInterface` to `BatchRegisterRoomsCommandHandler` and add the guard**

```php
// src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandler.php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\BatchRegisterRooms;

use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomBatchInvalidException;
use App\Room\Domain\Exception\RoomTypeNotFoundException;
use App\Room\Domain\Model\Room;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Room\Domain\Port\RoomTypeExistsInterface;
use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class BatchRegisterRoomsCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
        private HotelExistsInterface $hotelExists,
        private RoomTypeExistsInterface $roomTypeExists,
    ) {
    }

    public function __invoke(BatchRegisterRoomsCommand $command): void
    {
        if (!$this->hotelExists->exists($command->hotelId)) {
            throw new HotelNotFoundException($command->hotelId);
        }

        if (!$this->roomTypeExists->exists($command->roomTypeId)) {
            throw new RoomTypeNotFoundException($command->roomTypeId);
        }

        $violations = [];
        $seenNumbers = [];

        foreach ($command->entries as $index => $entry) {
            $lineField = \sprintf('line[%d]', $index + 2);
            $number = trim($entry['number']);
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
                $command->roomTypeId,
                $command->createdAt,
            ),
            $command->entries,
        );

        $this->roomRepository->addAll($rooms);
    }
}
```

- [ ] **Step 8: Run all unit tests**

```bash
make unit-test
```

Expected: all PASS.

- [ ] **Step 9: Commit**

```bash
git add src/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandler.php \
        src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandler.php \
        tests/Room/Application/UseCase/RegisterRoom/RegisterRoomCommandHandlerTest.php \
        tests/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandlerTest.php
git commit -m "feat(room): guard Room Registration against non-existent Room Type"
```

---

## Task 3: Infrastructure — `RoomTypeExistenceChecker` + fix `RoomTypeHasRoomsChecker`

**Files:**
- Create: `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeExistenceChecker.php`
- Modify: `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeHasRoomsChecker.php`

- [ ] **Step 1: Create `RoomTypeExistenceChecker`**

```php
// src/Room/Infrastructure/Persistence/Doctrine/RoomTypeExistenceChecker.php
<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Port\RoomTypeExistsInterface;
use Doctrine\DBAL\Connection;

final readonly class RoomTypeExistenceChecker implements RoomTypeExistsInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function exists(string $roomTypeId): bool
    {
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM room_type WHERE id = :id',
            ['id' => $roomTypeId],
        );

        return $count > 0;
    }
}
```

- [ ] **Step 2: Replace the `RoomTypeHasRoomsChecker` stub with a real query**

```php
// src/Room/Infrastructure/Persistence/Doctrine/RoomTypeHasRoomsChecker.php
<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Port\RoomTypeHasRoomsInterface;
use Doctrine\DBAL\Connection;

final readonly class RoomTypeHasRoomsChecker implements RoomTypeHasRoomsInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function hasRooms(string $roomTypeId): bool
    {
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM room WHERE room_type_id = :id',
            ['id' => $roomTypeId],
        );

        return $count > 0;
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Room/Infrastructure/Persistence/Doctrine/RoomTypeExistenceChecker.php \
        src/Room/Infrastructure/Persistence/Doctrine/RoomTypeHasRoomsChecker.php
git commit -m "feat(room): implement RoomTypeExistenceChecker and RoomTypeHasRoomsChecker (replaces stub)"
```

---

## Task 4: Database — migration + `RoomRepository` (queries already updated in Task 1)

**Files:**
- Create: `migrations/VersionYYYYMMDDHHmmss.php` (generated)

The `RoomRepository` queries were already updated in Task 1 (Step 9). This task only creates the migration so the `room_type_id` column exists in the DB.

- [ ] **Step 1: Generate migration file**

```bash
make generate-migration
```

Since `Room` is not managed by Doctrine ORM (DBAL only), the auto-diff will produce an empty migration. Inspect the generated file and replace the `up()` and `down()` bodies:

```php
public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE room ADD COLUMN room_type_id UUID NOT NULL REFERENCES room_type(id)');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE room DROP COLUMN room_type_id');
}
```

> **Note:** If the auto-diff generates nothing (empty migration), just add the SQL above. If the table already has rows in dev, run: `ALTER TABLE room ADD COLUMN room_type_id UUID REFERENCES room_type(id)` without NOT NULL, backfill, then add the constraint.

- [ ] **Step 2: Run the migration**

```bash
make migrate
```

Expected: migration runs without error. The `room` table now has a `room_type_id` column.

- [ ] **Step 3: Commit**

```bash
git add migrations/
git commit -m "feat(room): add room_type_id column to room table"
```

---

## Task 5: Config — wire `RoomTypeExistsInterface`

**Files:**
- Modify: `config/services/room.yaml`

- [ ] **Step 1: Add the binding to `room.yaml`**

In `config/services/room.yaml`, add after the existing `RoomTypeHasRoomsInterface` binding:

```yaml
    App\Room\Domain\Port\RoomTypeExistsInterface:
        class: App\Room\Infrastructure\Persistence\Doctrine\RoomTypeExistenceChecker
```

The `services` block should now contain:
```yaml
    App\Room\Domain\Port\RoomTypeIdGeneratorInterface:
        class: App\Room\Infrastructure\Service\RoomTypeIdGenerator

    App\Room\Domain\Port\RoomTypeHasRoomsInterface:
        class: App\Room\Infrastructure\Persistence\Doctrine\RoomTypeHasRoomsChecker

    App\Room\Domain\Port\RoomTypeExistsInterface:
        class: App\Room\Infrastructure\Persistence\Doctrine\RoomTypeExistenceChecker
```

- [ ] **Step 2: Verify the container compiles**

```bash
make lint
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add config/services/room.yaml
git commit -m "feat(room): wire RoomTypeExistsInterface to RoomTypeExistenceChecker"
```

---

## Task 6: Functional tests — `RegisterRoomControllerTest`

**Files:**
- Modify: `tests/Room/UI/Http/Controller/RegisterRoom/RegisterRoomControllerTest.php`

All tests in this file now need:
1. A Room Type registered before a Room can be registered
2. `roomTypeId` included in every Room Registration request body
3. `roomTypeId` asserted in the 201 response
4. One new test: 404 when `roomTypeId` references a non-existent Room Type

- [ ] **Step 1: Rewrite `RegisterRoomControllerTest`**

```php
// tests/Room/UI/Http/Controller/RegisterRoom/RegisterRoomControllerTest.php
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

    private const array ROOM_TYPE_PAYLOAD = [
        'name' => 'Single',
        'livingSpaceCount' => 1,
        'guestCapacity' => 1,
        'isAccessible' => false,
        'bedComposition' => [['type' => 'single', 'count' => 1]],
    ];

    #[Test]
    public function itRegistersARoomAndReturns201(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1, 'roomTypeId' => $roomTypeId], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{id: string, hotelId: string, number: string, floor: int, roomTypeId: string, createdAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['id']);
        self::assertSame($hotelId, $body['hotelId']);
        self::assertSame('101', $body['number']);
        self::assertSame(1, $body['floor']);
        self::assertSame($roomTypeId, $body['roomTypeId']);
        self::assertGreaterThan(0, $body['createdAt']);
    }

    #[Test]
    public function itReturns409WhenRoomNumberAlreadyExistsInHotel(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1, 'roomTypeId' => $roomTypeId], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 2, 'roomTypeId' => $roomTypeId], \JSON_THROW_ON_ERROR),
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
            uri: '/api/v1/hotels/00000000-0000-4000-8000-000000000000/rooms',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1, 'roomTypeId' => '00000000-0000-4000-8000-000000000001'], \JSON_THROW_ON_ERROR),
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
    public function itReturns404WhenRoomTypeDoesNotExist(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1, 'roomTypeId' => '00000000-0000-4000-8000-000000000001'], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-type-not-found', $body['type']);
        self::assertSame('Room Type Not Found', $body['title']);
        self::assertSame(Response::HTTP_NOT_FOUND, $body['status']);
    }

    #[Test]
    public function itReturns422WhenNumberIsMissing(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['floor' => 1, 'roomTypeId' => $roomTypeId], \JSON_THROW_ON_ERROR),
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
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'roomTypeId' => $roomTypeId], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns422WhenRoomTypeIdIsMissing(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1], \JSON_THROW_ON_ERROR),
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
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 301, 'roomTypeId' => $roomTypeId], \JSON_THROW_ON_ERROR),
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
            uri: '/api/v1/hotels/not-a-uuid/rooms',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1, 'roomTypeId' => '00000000-0000-4000-8000-000000000001'], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itAllowsSameRoomNumberInDifferentHotels(): void
    {
        $client = static::createClient();
        $hotelId1 = $this->registerHotelAndGetId($client);
        $roomTypeId1 = $this->registerRoomTypeAndGetId($client, $hotelId1);

        $client->request(
            method: 'POST',
            uri: '/api/v1/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(array_merge(self::HOTEL_PAYLOAD, ['name' => 'Hotel Test 2']), \JSON_THROW_ON_ERROR),
        );
        /** @var array{id: string} $hotel2Body */
        $hotel2Body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $hotelId2 = $hotel2Body['id'];
        $roomTypeId2 = $this->registerRoomTypeAndGetId($client, $hotelId2);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId1}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1, 'roomTypeId' => $roomTypeId1], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId2}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1, 'roomTypeId' => $roomTypeId2], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/v1/hotels',
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
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/room-types",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::ROOM_TYPE_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}
```

- [ ] **Step 2: Run functional tests for RegisterRoom**

```bash
make functional-test -- --filter RegisterRoomController
```

Expected: all PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Room/UI/Http/Controller/RegisterRoom/RegisterRoomControllerTest.php
git commit -m "test(room): update RegisterRoom functional tests to include roomTypeId"
```

---

## Task 7: Functional tests — `BatchRegisterRoomsControllerTest`

**Files:**
- Modify: `tests/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsControllerTest.php`

All tests need `roomTypeId` added as a multipart field. The `itReturns422WhenNumberAlreadyExistsInHotel` test also makes a single-room registration request which now needs `roomTypeId`. The success test must also assert `roomTypeId` in the response.

- [ ] **Step 1: Rewrite `BatchRegisterRoomsControllerTest`**

```php
// tests/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsControllerTest.php
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

    private const array ROOM_TYPE_PAYLOAD = [
        'name' => 'Single',
        'livingSpaceCount' => 1,
        'guestCapacity' => 1,
        'isAccessible' => false,
        'bedComposition' => [['type' => 'single', 'count' => 1]],
    ];

    #[Test]
    public function itImportsBatchAndReturns201(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $csv = $this->makeCsvFile("number,floor\n101,1\n102,2\n2A,-1\n");
        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms/batch",
            parameters: ['roomTypeId' => $roomTypeId],
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var list<array{id: string, hotelId: string, number: string, floor: int, roomTypeId: string, createdAt: int}> $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(3, $body);
        $numbers = array_column($body, 'number');
        $floors = array_column($body, 'floor');
        self::assertContains('101', $numbers);
        self::assertContains('102', $numbers);
        self::assertContains('2A', $numbers);
        self::assertContains(1, $floors);
        self::assertContains(2, $floors);
        self::assertContains(-1, $floors);
        foreach ($body as $room) {
            self::assertNotEmpty($room['id']);
            self::assertSame($hotelId, $room['hotelId']);
            self::assertSame($roomTypeId, $room['roomTypeId']);
            self::assertGreaterThan(0, $room['createdAt']);
        }
    }

    #[Test]
    public function itReturns201WithEmptyArrayForHeaderOnlyCsv(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $csv = $this->makeCsvFile("number,floor\n");
        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms/batch",
            parameters: ['roomTypeId' => $roomTypeId],
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
            uri: '/api/v1/hotels/00000000-0000-4000-8000-000000000000/rooms/batch',
            parameters: ['roomTypeId' => '00000000-0000-4000-8000-000000000001'],
            files: ['csv' => $csv],
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns404WhenRoomTypeDoesNotExist(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $csv = $this->makeCsvFile("number,floor\n101,1\n");
        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms/batch",
            parameters: ['roomTypeId' => '00000000-0000-4000-8000-000000000001'],
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-type-not-found', $body['type']);
        self::assertSame('Room Type Not Found', $body['title']);
        self::assertSame(Response::HTTP_NOT_FOUND, $body['status']);
    }

    #[Test]
    public function itReturns404WhenHotelIdIsNotUuidV4(): void
    {
        $client = static::createClient();

        $csv = $this->makeCsvFile("number,floor\n101,1\n");
        $client->request(
            method: 'POST',
            uri: '/api/v1/hotels/not-a-uuid/rooms/batch',
            parameters: ['roomTypeId' => '00000000-0000-4000-8000-000000000001'],
            files: ['csv' => $csv],
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WithViolationsWhenDuplicateInBatch(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $csv = $this->makeCsvFile("number,floor\n101,1\n101,2\n");
        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms/batch",
            parameters: ['roomTypeId' => $roomTypeId],
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
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101', 'floor' => 1, 'roomTypeId' => $roomTypeId], \JSON_THROW_ON_ERROR),
        );

        $csv = $this->makeCsvFile("number,floor\n101,1\n102,2\n");
        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms/batch",
            parameters: ['roomTypeId' => $roomTypeId],
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
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $csv = $this->makeCsvFile("number,floor\n,1\n101,1\n101,2\n");
        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms/batch",
            parameters: ['roomTypeId' => $roomTypeId],
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
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $csv = $this->makeCsvFile("number\n101\n");
        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms/batch",
            parameters: ['roomTypeId' => $roomTypeId],
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
        $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms/batch",
            parameters: ['roomTypeId' => $roomTypeId],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns422WhenRoomTypeIdIsMissingFromRequest(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $csv = $this->makeCsvFile("number,floor\n101,1\n");
        $client->request(
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/v1/hotels',
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
            method: 'POST',
            uri: "/api/v1/hotels/{$hotelId}/room-types",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::ROOM_TYPE_PAYLOAD, \JSON_THROW_ON_ERROR),
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

> **Note on multipart fields:** Symfony's `KernelBrowser::request()` sends `parameters` as POST form fields alongside `files` in a multipart request. Using `parameters: ['roomTypeId' => $roomTypeId]` correctly sends it as a form field readable via `$request->request->get('roomTypeId')`.

- [ ] **Step 2: Run functional tests for BatchRegisterRooms**

```bash
make functional-test -- --filter BatchRegisterRoomsController
```

Expected: all PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsControllerTest.php
git commit -m "test(room): update BatchRegisterRooms functional tests to include roomTypeId"
```

---

## Task 8: Functional tests — `DeleteRoomTypeControllerTest` (409 guard now real)

**Files:**
- Modify: `tests/Room/UI/Http/Controller/DeleteRoomType/DeleteRoomTypeControllerTest.php`

- [ ] **Step 1: Add `itReturns409WhenRoomTypeHasRooms` test**

Add this test and the `ROOM_PAYLOAD` constant to the existing class:

```php
private const array ROOM_PAYLOAD_TEMPLATE = [
    'number' => '101',
    'floor' => 1,
];
```

Add this test method:
```php
#[Test]
public function itReturns409WhenRoomTypeHasRooms(): void
{
    $client = static::createClient();
    $hotelId = $this->registerHotelAndGetId($client);
    $roomTypeId = $this->registerRoomTypeAndGetId($client, $hotelId);

    // Register a room assigned to this Room Type
    $client->request(
        method: 'POST',
        uri: "/api/v1/hotels/{$hotelId}/rooms",
        server: ['CONTENT_TYPE' => 'application/json'],
        content: json_encode(
            ['number' => '101', 'floor' => 1, 'roomTypeId' => $roomTypeId],
            \JSON_THROW_ON_ERROR
        ),
    );
    self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

    // Attempt to delete the Room Type
    $client->request('DELETE', "/api/v1/hotels/{$hotelId}/room-types/{$roomTypeId}");

    $response = $client->getResponse();
    self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

    /** @var array{type: string, title: string, status: int} $body */
    $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    self::assertSame('https://book.it/problems/room-type-has-rooms', $body['type']);
    self::assertSame('Room Type Has Rooms', $body['title']);
    self::assertSame(Response::HTTP_CONFLICT, $body['status']);
}
```

- [ ] **Step 2: Run functional tests for DeleteRoomType**

```bash
make functional-test -- --filter DeleteRoomTypeController
```

Expected: all PASS including the new 409 test.

- [ ] **Step 3: Commit**

```bash
git add tests/Room/UI/Http/Controller/DeleteRoomType/DeleteRoomTypeControllerTest.php
git commit -m "test(room): add 409 test for DeleteRoomType when rooms are assigned"
```

---

## Task 9: OpenAPI, lint, and full test suite

- [ ] **Step 1: Regenerate the OpenAPI spec**

```bash
make openapi
```

Expected: spec updated with the new `roomTypeId` fields in Room Registration request and response. No error.

- [ ] **Step 2: Run the full linter**

```bash
make lint
```

Expected: deptrac passes, CS Fixer finds nothing, PHPStan passes. Fix any issue before proceeding.

- [ ] **Step 3: Run all tests**

```bash
make test
```

Expected: all unit, integration, and functional tests PASS.

- [ ] **Step 4: Commit the updated spec**

```bash
git add docs/openapi/
git commit -m "docs(openapi): regenerate spec after Plan B Room Type wiring"
```

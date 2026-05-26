# Batch Room Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `POST /api/hotels/{hotelId}/rooms/batch` to import multiple rooms from a CSV file in one all-or-nothing operation.

**Architecture:** The controller parses the CSV, extracts room numbers (one per data row), and dispatches a `BatchRegisterRoomsCommand` carrying pre-generated IDs. The command handler validates all entries (hotel existence, format, duplicates against DB and within batch) before writing anything; if any validation fails it throws `RoomBatchInvalidException` with the full violation list. On success the controller builds the response directly from command data — no round-trip query needed.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL (raw queries, no ORM), PHPUnit. All commands via `make`.

---

## File map

| File | Status | Responsibility |
|---|---|---|
| `src/Room/Domain/Exception/RoomBatchInvalidException.php` | Create | Domain exception carrying violations list |
| `src/Room/Domain/Port/RoomRepositoryInterface.php` | Modify | Add `addAll(list<Room> $rooms): void` |
| `src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php` | Modify | Implement `addAll` with DBAL transaction |
| `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomRepository.php` | Modify | Implement `addAll` (loop) |
| `src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommand.php` | Create | Command DTO: hotelId + pre-generated entries |
| `src/Room/Application/Service/BatchRegisterRoomsCommandFactory.php` | Create | Generates IDs + stamps clock, returns command |
| `src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandler.php` | Create | Full validation + `addAll` |
| `src/Room/Application/Exception/InvalidCsvFormatException.php` | Create | Thrown by parser when CSV structure is invalid |
| `src/Room/Application/Service/CsvRoomNumbersParser.php` | Create | Parses CSV file, returns `list<string>` of room numbers |
| `tests/Room/Application/Service/CsvRoomNumbersParserTest.php` | Create | Unit tests for parser |
| `src/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsController.php` | Create | Delegates CSV parsing, dispatches command, returns 201 or 422 |
| `tests/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsControllerTest.php` | Create | Functional tests for endpoint |

---

## Task 1: Domain exception `RoomBatchInvalidException`

**Files:**
- Create: `src/Room/Domain/Exception/RoomBatchInvalidException.php`

- [ ] **Step 1: Create the exception**

```php
<?php

declare(strict_types=1);

namespace App\Room\Domain\Exception;

final class RoomBatchInvalidException extends \DomainException
{
    /**
     * @param list<array{field: string, message: string}> $violations
     */
    public function __construct(public readonly array $violations)
    {
        parent::__construct('Room batch import failed due to validation errors.');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Room/Domain/Exception/RoomBatchInvalidException.php
git commit -m "feat(room): add RoomBatchInvalidException with violations list"
```

---

## Task 2: `addAll` on repository

**Files:**
- Modify: `src/Room/Domain/Port/RoomRepositoryInterface.php`
- Modify: `src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php`
- Modify: `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomRepository.php`

- [ ] **Step 1: Add method to port**

In `src/Room/Domain/Port/RoomRepositoryInterface.php`, add after `add()`:

```php
/** @param list<Room> $rooms */
public function addAll(array $rooms): void;
```

- [ ] **Step 2: Implement in Doctrine repository**

In `src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php`, add after `add()`:

```php
public function addAll(array $rooms): void
{
    $this->bookit->transactional(function () use ($rooms): void {
        foreach ($rooms as $room) {
            $this->bookit->insert('room', [
                'id' => $room->id,
                'hotel_id' => $room->hotelId,
                'number' => $room->number,
                'created_at' => $room->createdAt->format('Y-m-d H:i:s'),
            ]);
        }
    });
}
```

- [ ] **Step 3: Implement in InMemory repository**

In `tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomRepository.php`, add after `add()`:

```php
public function addAll(array $rooms): void
{
    foreach ($rooms as $room) {
        $this->add($room);
    }
}
```

- [ ] **Step 4: Run existing unit tests to confirm no regression**

```bash
make unit-test-quiet
```

Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add src/Room/Domain/Port/RoomRepositoryInterface.php \
        src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php \
        tests/Room/Infrastructure/Persistence/InMemory/InMemoryRoomRepository.php
git commit -m "feat(room): add addAll to room repository with transactional DBAL impl"
```

---

## Task 3: Command, factory, and handler (TDD)

**Files:**
- Create: `src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommand.php`
- Create: `src/Room/Application/Service/BatchRegisterRoomsCommandFactory.php`
- Create: `src/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandler.php`
- Create: `tests/Room/Application/UseCase/BatchRegisterRooms/BatchRegisterRoomsCommandHandlerTest.php`

- [ ] **Step 1: Create the command DTO**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\BatchRegisterRooms;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class BatchRegisterRoomsCommand implements SyncCommandInterface
{
    /**
     * @param list<array{id: string, number: string}> $entries
     */
    public function __construct(
        public string $hotelId,
        public array $entries,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 2: Create the factory**

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

    /** @param list<string> $numbers */
    public function create(string $hotelId, array $numbers): BatchRegisterRoomsCommand
    {
        $entries = array_map(
            fn(string $number) => ['id' => $this->roomIdGenerator->generate(), 'number' => $number],
            $numbers,
        );

        return new BatchRegisterRoomsCommand($hotelId, $entries, $this->clock->now());
    }
}
```

- [ ] **Step 3: Write failing unit tests**

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
                ['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '101'],
                ['id' => 'aaaaaaaa-0000-4000-8000-000000000002', 'number' => '102'],
            ],
            createdAt: new \DateTimeImmutable('2024-01-01 10:00:00'),
        );

        ($this->handler)($command);

        $room1 = $this->roomRepository->get('aaaaaaaa-0000-4000-8000-000000000001');
        $room2 = $this->roomRepository->get('aaaaaaaa-0000-4000-8000-000000000002');
        self::assertNotNull($room1);
        self::assertNotNull($room2);
        self::assertSame('101', $room1->number);
        self::assertSame('102', $room2->number);
    }

    #[Test]
    public function itSucceedsWithEmptyBatch(): void
    {
        $command = new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [],
            createdAt: new \DateTimeImmutable(),
        );

        ($this->handler)($command);

        self::assertTrue(true);
    }

    #[Test]
    public function itThrowsWhenHotelDoesNotExist(): void
    {
        $this->hotelExistenceChecker->setExists(false);
        $this->expectException(HotelNotFoundException::class);

        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '101']],
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itRejectsBlankNumber(): void
    {
        $this->expectException(RoomBatchInvalidException::class);

        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '']],
            createdAt: new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itRejectsNumberExceeding50Characters(): void
    {
        $this->expectException(RoomBatchInvalidException::class);

        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => str_repeat('X', 51)]],
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
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '101'],
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000002', 'number' => '101'],
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
        // pre-seed room 101
        ($this->handler)(new BatchRegisterRoomsCommand(
            hotelId: self::HOTEL_ID,
            entries: [['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '101']],
            createdAt: new \DateTimeImmutable(),
        ));

        $exception = null;
        try {
            ($this->handler)(new BatchRegisterRoomsCommand(
                hotelId: self::HOTEL_ID,
                entries: [['id' => 'aaaaaaaa-0000-4000-8000-000000000002', 'number' => '101']],
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
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => ''],
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000002', 'number' => str_repeat('X', 51)],
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000003', 'number' => '101'],
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000004', 'number' => '101'],
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
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'number' => '101'],
                    ['id' => 'aaaaaaaa-0000-4000-8000-000000000002', 'number' => ''],
                ],
                createdAt: new \DateTimeImmutable(),
            ));
        } catch (RoomBatchInvalidException) {
        }

        self::assertNull($this->roomRepository->get('aaaaaaaa-0000-4000-8000-000000000001'));
    }
}
```

- [ ] **Step 4: Run tests to confirm they fail**

```bash
make unit-test-quiet ARGS="--filter BatchRegisterRoomsCommandHandlerTest"
```

Expected: FAIL (class not found).

- [ ] **Step 5: Implement the command handler**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\UseCase\BatchRegisterRooms;

use App\Room\Domain\Exception\HotelNotFoundException;
use App\Room\Domain\Exception\RoomBatchInvalidException;
use App\Room\Domain\Model\Room;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;
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

            if ('' === trim($number)) {
                $violations[] = ['field' => $lineField, 'message' => 'Room number must not be blank.'];
                continue;
            }

            if (mb_strlen($number) > 50) {
                $violations[] = ['field' => $lineField, 'message' => 'Room number must not exceed 50 characters.'];
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
            fn(array $entry) => new Room($entry['id'], $command->hotelId, $entry['number'], $command->createdAt),
            $command->entries,
        );

        $this->roomRepository->addAll($rooms);
    }
}
```

- [ ] **Step 6: Run tests to confirm they pass**

```bash
make unit-test-quiet ARGS="--filter BatchRegisterRoomsCommandHandlerTest"
```

Expected: all green.

- [ ] **Step 7: Run full unit test suite for regressions**

```bash
make unit-test-quiet
```

Expected: all green.

- [ ] **Step 8: Commit**

```bash
git add src/Room/Application/UseCase/BatchRegisterRooms/ \
        src/Room/Application/Service/BatchRegisterRoomsCommandFactory.php \
        tests/Room/Application/UseCase/BatchRegisterRooms/
git commit -m "feat(room): add BatchRegisterRoomsCommand, factory, and handler with full validation"
```

---

## Task 4: `CsvRoomNumbersParser` service (TDD)

**Files:**
- Create: `src/Room/Application/Exception/InvalidCsvFormatException.php`
- Create: `src/Room/Application/Service/CsvRoomNumbersParser.php`
- Create: `tests/Room/Application/Service/CsvRoomNumbersParserTest.php`

- [ ] **Step 1: Create the exception**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\Exception;

final class InvalidCsvFormatException extends \InvalidArgumentException
{
    public function __construct(string $detail)
    {
        parent::__construct($detail);
    }
}
```

- [ ] **Step 2: Write failing unit tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Room\Application\Service;

use App\Room\Application\Exception\InvalidCsvFormatException;
use App\Room\Application\Service\CsvRoomNumbersParser;
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
    public function itParsesValidCsvAndReturnsNumbers(): void
    {
        $numbers = $this->parser->parse($this->makeCsvFile("number\n101\n102\n2A\n"));

        self::assertSame(['101', '102', '2A'], $numbers);
    }

    #[Test]
    public function itReturnsEmptyArrayForHeaderOnlyCsv(): void
    {
        $numbers = $this->parser->parse($this->makeCsvFile("number\n"));

        self::assertSame([], $numbers);
    }

    #[Test]
    public function itThrowsWhenHeaderIsInvalid(): void
    {
        $this->expectException(InvalidCsvFormatException::class);

        $this->parser->parse($this->makeCsvFile("room_number\n101\n"));
    }

    private function makeCsvFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rooms_') . '.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'rooms.csv', 'text/csv', null, true);
    }
}
```

- [ ] **Step 3: Run tests to confirm they fail**

```bash
make unit-test-quiet ARGS="--filter CsvRoomNumbersParserTest"
```

Expected: FAIL (class not found).

- [ ] **Step 4: Implement the parser**

```php
<?php

declare(strict_types=1);

namespace App\Room\Application\Service;

use App\Room\Application\Exception\InvalidCsvFormatException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class CsvRoomNumbersParser
{
    /** @return list<string> */
    public function parse(UploadedFile $file): array
    {
        $handle = fopen($file->getPathname(), 'r');
        if (false === $handle) {
            throw new InvalidCsvFormatException('Could not read the uploaded file.');
        }

        $header = fgetcsv($handle);
        if ($header !== ['number']) {
            fclose($handle);
            throw new InvalidCsvFormatException('Invalid CSV format: expected a single "number" header column.');
        }

        $numbers = [];
        while (false !== ($row = fgetcsv($handle))) {
            $numbers[] = $row[0] ?? '';
        }
        fclose($handle);

        return $numbers;
    }
}
```

- [ ] **Step 5: Run tests to confirm they pass**

```bash
make unit-test-quiet ARGS="--filter CsvRoomNumbersParserTest"
```

Expected: all green.

- [ ] **Step 6: Run full unit test suite for regressions**

```bash
make unit-test-quiet
```

Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add src/Room/Application/Exception/InvalidCsvFormatException.php \
        src/Room/Application/Service/CsvRoomNumbersParser.php \
        tests/Room/Application/Service/CsvRoomNumbersParserTest.php
git commit -m "feat(room): add CsvRoomNumbersParser service with unit tests"
```

---

## Task 5: Controller and functional tests (TDD)

**Files:**
- Create: `src/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsController.php`
- Create: `tests/Room/UI/Http/Controller/BatchRegisterRooms/BatchRegisterRoomsControllerTest.php`

- [ ] **Step 1: Write failing functional tests**

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

        $csv = $this->makeCsvFile("number\n101\n102\n2A\n");
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var list<array{id: string, hotelId: string, number: string, createdAt: int}> $body */
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
        }
    }

    #[Test]
    public function itReturns201WithEmptyArrayForHeaderOnlyCsv(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $csv = $this->makeCsvFile("number\n");
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

        $csv = $this->makeCsvFile("number\n101\n");
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

        $csv = $this->makeCsvFile("number\n101\n");
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

        $csv = $this->makeCsvFile("number\n101\n101\n");
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

        // pre-register room 101
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101'], \JSON_THROW_ON_ERROR),
        );

        $csv = $this->makeCsvFile("number\n101\n102\n");
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

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

        $csv = $this->makeCsvFile("number\n\n101\n101\n");
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        /** @var array{violations: list<array{field: string, message: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['violations']);
    }

    #[Test]
    public function itReturns422WhenCsvHeaderIsInvalid(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $csv = $this->makeCsvFile("room_number\n101\n");
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

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
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

- [ ] **Step 2: Run tests to confirm they fail**

```bash
make functional-test ARGS="--filter BatchRegisterRoomsControllerTest"
```

Expected: FAIL (route not found — 404 on all cases).

- [ ] **Step 3: Implement the controller**

```php
<?php

declare(strict_types=1);

namespace App\Room\UI\Http\Controller\BatchRegisterRooms;

use App\Room\Application\Exception\InvalidCsvFormatException;
use App\Room\Application\Service\BatchRegisterRoomsCommandFactory;
use App\Room\Application\Service\CsvRoomNumbersParser;
use App\Room\Domain\Exception\RoomBatchInvalidException;
use App\Room\UI\Http\Controller\RoomSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Infrastructure\Http\ProblemDetail;
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
                            description: 'CSV file with a "number" header column and one room number per row',
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
            return $this->invalidCsvResponse('A CSV file is required.');
        }

        try {
            $numbers = $this->csvParser->parse($file);
        } catch (InvalidCsvFormatException $e) {
            return $this->invalidCsvResponse($e->getMessage());
        }

        $command = $this->commandFactory->create($hotelId, $numbers);

        try {
            $this->commandBus->execute($command);
        } catch (RoomBatchInvalidException $e) {
            $problem = new ProblemDetail(
                type: 'https://book.it/problems/room-batch-invalid',
                title: 'Room Batch Import Failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                detail: 'One or more rooms could not be registered.',
                violations: $e->violations,
            );

            return new JsonResponse($problem->toArray(), $problem->status, ['Content-Type' => 'application/problem+json']);
        }

        $rooms = array_map(
            fn(array $entry) => array_merge(
                $this->roomSerializer->serialize(
                    new \App\Room\Domain\Model\Room($entry['id'], $command->hotelId, $entry['number'], $command->createdAt)
                )
            ),
            $command->entries,
        );

        return new JsonResponse($rooms, Response::HTTP_CREATED);
    }

    private function invalidCsvResponse(string $detail): JsonResponse
    {
        $problem = new ProblemDetail(
            type: 'about:blank',
            title: 'Unprocessable Content',
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            detail: $detail,
        );

        return new JsonResponse($problem->toArray(), $problem->status, ['Content-Type' => 'application/problem+json']);
    }
}
```

- [ ] **Step 4: Run functional tests**

```bash
make functional-test ARGS="--filter BatchRegisterRoomsControllerTest"
```

Expected: all green.

- [ ] **Step 5: Run full test suite for regressions**

```bash
make test
```

Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add src/Room/UI/Http/Controller/BatchRegisterRooms/ \
        tests/Room/UI/Http/Controller/BatchRegisterRooms/
git commit -m "feat(room): add batch room import endpoint POST /api/hotels/{hotelId}/rooms/batch"
```

---

## Task 6: Static analysis, OpenAPI spec

**Files:**
- Modified: `openapi.yaml` (regenerated)

- [ ] **Step 1: Run static analysis**

```bash
make static-code-analysis
```

Fix any PHPStan errors before continuing.

- [ ] **Step 2: Apply code style**

```bash
make apply-cs
```

- [ ] **Step 3: Regenerate OpenAPI spec**

```bash
make openapi
```

Check the output for warnings. Verify the new `POST /api/hotels/{hotelId}/rooms/batch` route appears in `openapi.yaml`.

- [ ] **Step 4: Commit**

```bash
git add openapi.yaml
git commit -m "docs(openapi): regenerate spec with batch room import endpoint"
```

---

## Self-review checklist

- [x] **Happy path** (multiple rooms) → covered in `itImportsBatchAndReturns201`
- [x] **Empty batch** (header only) → covered in `itReturns201WithEmptyArrayForHeaderOnlyCsv`
- [x] **Hotel not found** → covered
- [x] **Invalid UUID in path** → covered
- [x] **Duplicate within batch** → covered
- [x] **Duplicate against existing DB** → covered
- [x] **All violations reported at once** → covered in `itReturns422WithAllViolationsAtOnce`
- [x] **Missing CSV file** → covered in `itReturns422WhenNoCsvFileProvided`
- [x] **Invalid CSV header** → covered in `itReturns422WhenCsvHeaderIsInvalid`
- [x] **No new domain concept** (ADR 0004)
- [x] **All-or-nothing** (transactional `addAll`)
- [x] **RFC 7807** error format with `violations`
- [x] **OpenAPI docs** on controller

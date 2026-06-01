# Search Index Rebuild CLI — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `search:rebuild-index` Symfony console command that truncates the three search tables (`hotel_room_types`, `room_index`, `unavailable_periods`) and repopulates them entirely from the source schemas.

**Architecture:** A single console command in `Search/UI/Console/` injects the three existing writers plus four DBAL connections for reading source data. No new application service — the truncate+read+write is an infrastructure admin operation, not a domain flow.

**Tech Stack:** PHP 8.4, Symfony Console, Doctrine DBAL, PostgreSQL (multi-schema: hotel, room, pricing, availability, search)

---

## File Map

| Action | Path |
|--------|------|
| Create | `src/Search/UI/Console/RebuildSearchIndexCommand.php` |
| Create | `tests/Search/Functional/Console/RebuildSearchIndexCommandTest.php` |

> **DI note:** All `Connection` arguments are autowired by Symfony via the `{connectionName}Connection` naming convention — no explicit YAML wiring needed.

---

## Task 1 — Command skeleton + failing test

**Files:**
- Create: `src/Search/UI/Console/RebuildSearchIndexCommand.php`
- Create: `tests/Search/Functional/Console/RebuildSearchIndexCommandTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Search/Functional/Console/RebuildSearchIndexCommandTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Search\Functional\Console;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('functional')]
final class RebuildSearchIndexCommandTest extends KernelTestCase
{
    private Connection $hotelConnection;
    private Connection $roomConnection;
    private Connection $pricingConnection;
    private Connection $availabilityConnection;
    private Connection $searchConnection;

    private const HOTEL_ID = '11111111-1111-1111-1111-111111111111';
    private const ROOM_TYPE_ID = '22222222-2222-2222-2222-222222222222';
    private const ROOM_ID = '33333333-3333-3333-3333-333333333333';
    private const HOLD_ID = '44444444-4444-4444-4444-444444444444';
    private const RESERVATION_ID = '55555555-5555-5555-5555-555555555555';
    private const BLOCKED_PERIOD_ID = '66666666-6666-6666-6666-666666666666';

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->hotelConnection = $container->get('doctrine.dbal.hotel_connection');
        $this->roomConnection = $container->get('doctrine.dbal.room_connection');
        $this->pricingConnection = $container->get('doctrine.dbal.pricing_connection');
        $this->availabilityConnection = $container->get('doctrine.dbal.availability_connection');
        $this->searchConnection = $container->get('doctrine.dbal.search_connection');

        $this->cleanUp();
        $this->insertFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    #[Test]
    public function itExitsSuccessfully(): void
    {
        $application = new Application(static::$kernel);
        $tester = new CommandTester($application->find('search:rebuild-index'));

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
    }

    private function insertFixtures(): void
    {
        $this->hotelConnection->executeStatement(
            "INSERT INTO hotel (id, name, street_address, postal_code, city, country, search_key, created_at)
             VALUES (:id, :name, :street, :postal, :city, :country, :key, NOW())",
            [
                'id' => self::HOTEL_ID,
                'name' => 'Test Hotel',
                'street' => '1 rue de la Paix',
                'postal' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
                'key' => 'test-hotel-paris-fr',
            ],
        );

        $this->roomConnection->executeStatement(
            "INSERT INTO room_type (id, hotel_id, name, living_space_count, guest_capacity, is_accessible, bed_composition, created_at)
             VALUES (:id, :hotelId, :name, 1, 2, false, :beds, NOW())",
            [
                'id' => self::ROOM_TYPE_ID,
                'hotelId' => self::HOTEL_ID,
                'name' => 'Standard Double',
                'beds' => '[{"type":"double","count":1}]',
            ],
        );

        $this->roomConnection->executeStatement(
            "INSERT INTO room (id, hotel_id, room_type_id, room_number, room_floor, created_at)
             VALUES (:id, :hotelId, :roomTypeId, '101', 1, NOW())",
            [
                'id' => self::ROOM_ID,
                'hotelId' => self::HOTEL_ID,
                'roomTypeId' => self::ROOM_TYPE_ID,
            ],
        );

        $this->pricingConnection->executeStatement(
            "INSERT INTO base_rate (room_id, amount_cents, updated_at) VALUES (:roomId, 9900, NOW())",
            ['roomId' => self::ROOM_ID],
        );

        $this->availabilityConnection->executeStatement(
            "INSERT INTO hold (id, room_id, reservation_id, check_in, check_out, expires_at, created_at)
             VALUES (:id, :roomId, :reservationId, '2027-07-01', '2027-07-05', NOW() + INTERVAL '15 minutes', NOW())",
            ['id' => self::HOLD_ID, 'roomId' => self::ROOM_ID, 'reservationId' => self::RESERVATION_ID],
        );

        $this->availabilityConnection->executeStatement(
            "INSERT INTO blocked_period (id, room_id, check_in, check_out, created_at)
             VALUES (:id, :roomId, '2027-08-01', '2027-08-10', NOW())",
            ['id' => self::BLOCKED_PERIOD_ID, 'roomId' => self::ROOM_ID],
        );
    }

    private function cleanUp(): void
    {
        $this->searchConnection->executeStatement('DELETE FROM unavailable_periods');
        $this->searchConnection->executeStatement('DELETE FROM room_index');
        $this->searchConnection->executeStatement('DELETE FROM hotel_room_types');

        $this->availabilityConnection->executeStatement('DELETE FROM blocked_period WHERE id = :id', ['id' => self::BLOCKED_PERIOD_ID]);
        $this->availabilityConnection->executeStatement('DELETE FROM hold WHERE id = :id', ['id' => self::HOLD_ID]);
        $this->pricingConnection->executeStatement('DELETE FROM base_rate WHERE room_id = :id', ['id' => self::ROOM_ID]);
        $this->roomConnection->executeStatement('DELETE FROM room WHERE id = :id', ['id' => self::ROOM_ID]);
        $this->roomConnection->executeStatement('DELETE FROM room_type WHERE id = :id', ['id' => self::ROOM_TYPE_ID]);
        $this->hotelConnection->executeStatement('DELETE FROM hotel WHERE id = :id', ['id' => self::HOTEL_ID]);
    }
}
```

- [ ] **Step 2: Run the test — confirm it fails (command not found)**

```bash
make functional-test ARGS="--filter=RebuildSearchIndexCommandTest"
```

Expected: error like `InvalidArgumentException: The command "search:rebuild-index" does not exist.`

- [ ] **Step 3: Create the command skeleton**

Create `src/Search/UI/Console/RebuildSearchIndexCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Search\UI\Console;

use App\Search\Domain\Port\HotelRoomTypeWriterInterface;
use App\Search\Domain\Port\RoomIndexWriterInterface;
use App\Search\Domain\Port\UnavailablePeriodWriterInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'search:rebuild-index', description: 'Truncate and rebuild the search read model from source data')]
final class RebuildSearchIndexCommand extends Command
{
    public function __construct(
        private readonly HotelRoomTypeWriterInterface $hotelRoomTypeWriter,
        private readonly RoomIndexWriterInterface $roomIndexWriter,
        private readonly UnavailablePeriodWriterInterface $unavailablePeriodWriter,
        private readonly Connection $searchConnection,
        private readonly Connection $roomConnection,
        private readonly Connection $pricingConnection,
        private readonly Connection $availabilityConnection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Rebuilding search index...');
        $output->writeln('<comment>Warning: search results will be empty during this operation.</comment>');

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 4: Run the test — confirm it passes**

```bash
make functional-test ARGS="--filter=RebuildSearchIndexCommandTest"
```

Expected: PASS (exit code 0)

- [ ] **Step 5: Commit**

```bash
git add src/Search/UI/Console/RebuildSearchIndexCommand.php \
        config/services/search.yaml \
        tests/Search/Functional/Console/RebuildSearchIndexCommandTest.php
git commit -m "feat(search): add search:rebuild-index command skeleton with DI wiring"
```

---

## Task 2 — Truncate + rebuild hotel_room_types

**Files:**
- Modify: `src/Search/UI/Console/RebuildSearchIndexCommand.php`
- Modify: `tests/Search/Functional/Console/RebuildSearchIndexCommandTest.php`

- [ ] **Step 1: Add assertion to the test**

Add a second test method in `RebuildSearchIndexCommandTest`:

```php
#[Test]
public function itRebuildsHotelRoomTypes(): void
{
    $application = new Application(static::$kernel);
    $tester = new CommandTester($application->find('search:rebuild-index'));
    $tester->execute([]);

    /** @var array{room_type_id: string, hotel_id: string, hotel_name: string, city: string, guest_capacity: string}|false $row */
    $row = $this->searchConnection->fetchAssociative(
        'SELECT room_type_id, hotel_id, hotel_name, city, guest_capacity FROM hotel_room_types WHERE room_type_id = :id',
        ['id' => self::ROOM_TYPE_ID],
    );

    self::assertNotFalse($row);
    self::assertSame(self::HOTEL_ID, $row['hotel_id']);
    self::assertSame('Test Hotel', $row['hotel_name']);
    self::assertSame('Paris', $row['city']);
    self::assertSame('2', $row['guest_capacity']);
}
```

- [ ] **Step 2: Run the test — confirm it fails**

```bash
make functional-test ARGS="--filter=RebuildSearchIndexCommandTest"
```

Expected: `itRebuildsHotelRoomTypes` fails — `hotel_room_types` is empty.

- [ ] **Step 3: Implement truncate + room type loop in the command**

Replace the `execute` method body:

```php
protected function execute(InputInterface $input, OutputInterface $output): int
{
    $output->writeln('Rebuilding search index...');
    $output->writeln('<comment>Warning: search results will be empty during this operation.</comment>');

    $output->write('[1/6] Truncating search tables... ');
    $this->searchConnection->executeStatement('TRUNCATE unavailable_periods, room_index, hotel_room_types CASCADE');
    $output->writeln('done');

    $output->write('[2/6] Rebuilding hotel_room_types... ');
    /** @var list<array{id: string, hotel_id: string, name: string, guest_capacity: int|string, bed_composition: string}> $roomTypes */
    $roomTypes = $this->roomConnection->fetchAllAssociative(
        'SELECT id, hotel_id, name, guest_capacity, bed_composition FROM room_type',
    );
    foreach ($roomTypes as $rt) {
        /** @var list<array{type: string, count: int}> $beds */
        $beds = json_decode($rt['bed_composition'], true, 512, \JSON_THROW_ON_ERROR);
        $this->hotelRoomTypeWriter->upsertRoomType(
            roomTypeId: $rt['id'],
            hotelId: $rt['hotel_id'],
            name: $rt['name'],
            guestCapacity: (int) $rt['guest_capacity'],
            bedComposition: $beds,
        );
    }
    $output->writeln(sprintf('%d room types inserted', count($roomTypes)));

    return Command::SUCCESS;
}
```

- [ ] **Step 4: Run the test — confirm it passes**

```bash
make functional-test ARGS="--filter=RebuildSearchIndexCommandTest"
```

Expected: both tests pass.

---

## Task 3 — Rebuild room_index

**Files:**
- Modify: `src/Search/UI/Console/RebuildSearchIndexCommand.php`
- Modify: `tests/Search/Functional/Console/RebuildSearchIndexCommandTest.php`

- [ ] **Step 1: Add assertion to the test**

Add in `RebuildSearchIndexCommandTest`:

```php
#[Test]
public function itRebuildsRoomIndex(): void
{
    $application = new Application(static::$kernel);
    $tester = new CommandTester($application->find('search:rebuild-index'));
    $tester->execute([]);

    /** @var array{room_id: string, room_type_id: string, hotel_id: string}|false $row */
    $row = $this->searchConnection->fetchAssociative(
        'SELECT room_id, room_type_id, hotel_id FROM room_index WHERE room_id = :id',
        ['id' => self::ROOM_ID],
    );

    self::assertNotFalse($row);
    self::assertSame(self::ROOM_TYPE_ID, $row['room_type_id']);
    self::assertSame(self::HOTEL_ID, $row['hotel_id']);
}
```

- [ ] **Step 2: Run the test — confirm it fails**

```bash
make functional-test ARGS="--filter=RebuildSearchIndexCommandTest"
```

Expected: `itRebuildsRoomIndex` fails.

- [ ] **Step 3: Add room loop to the command, after the room types block**

In `execute`, after the `hotel_room_types` block and before `return Command::SUCCESS`:

```php
$output->write('[3/6] Rebuilding room_index... ');
/** @var list<array{id: string, room_type_id: string, hotel_id: string}> $rooms */
$rooms = $this->roomConnection->fetchAllAssociative(
    'SELECT id, room_type_id, hotel_id FROM room',
);
foreach ($rooms as $room) {
    $this->roomIndexWriter->upsert(
        roomId: $room['id'],
        roomTypeId: $room['room_type_id'],
        hotelId: $room['hotel_id'],
    );
}
$output->writeln(sprintf('%d rooms inserted', count($rooms)));
```

- [ ] **Step 4: Run tests**

```bash
make functional-test ARGS="--filter=RebuildSearchIndexCommandTest"
```

Expected: all tests pass.

---

## Task 4 — Apply base rates

**Files:**
- Modify: `src/Search/UI/Console/RebuildSearchIndexCommand.php`
- Modify: `tests/Search/Functional/Console/RebuildSearchIndexCommandTest.php`

- [ ] **Step 1: Add assertion to the test**

Add in `RebuildSearchIndexCommandTest`:

```php
#[Test]
public function itAppliesBaseRates(): void
{
    $application = new Application(static::$kernel);
    $tester = new CommandTester($application->find('search:rebuild-index'));
    $tester->execute([]);

    /** @var array{base_price_cents: string}|false $row */
    $row = $this->searchConnection->fetchAssociative(
        'SELECT base_price_cents FROM hotel_room_types WHERE room_type_id = :id',
        ['id' => self::ROOM_TYPE_ID],
    );

    self::assertNotFalse($row);
    self::assertSame('9900', $row['base_price_cents']);
}
```

- [ ] **Step 2: Run the test — confirm it fails**

```bash
make functional-test ARGS="--filter=RebuildSearchIndexCommandTest"
```

Expected: `itAppliesBaseRates` fails — `base_price_cents` is null.

- [ ] **Step 3: Add base rate loop to the command, after the room_index block**

```php
$output->write('[4/6] Applying base rates... ');
/** @var list<array{room_id: string, amount_cents: int|string}> $baseRates */
$baseRates = $this->pricingConnection->fetchAllAssociative(
    'SELECT room_id, amount_cents FROM base_rate',
);
foreach ($baseRates as $rate) {
    $this->hotelRoomTypeWriter->updateBaseRateByRoom(
        roomId: $rate['room_id'],
        amountCents: (int) $rate['amount_cents'],
    );
}
$output->writeln(sprintf('%d rates applied', count($baseRates)));
```

- [ ] **Step 4: Run tests**

```bash
make functional-test ARGS="--filter=RebuildSearchIndexCommandTest"
```

Expected: all tests pass.

---

## Task 5 — Rebuild unavailable_periods

**Files:**
- Modify: `src/Search/UI/Console/RebuildSearchIndexCommand.php`
- Modify: `tests/Search/Functional/Console/RebuildSearchIndexCommandTest.php`

- [ ] **Step 1: Add assertion to the test**

Add in `RebuildSearchIndexCommandTest`:

```php
#[Test]
public function itRebuildsUnavailablePeriods(): void
{
    $application = new Application(static::$kernel);
    $tester = new CommandTester($application->find('search:rebuild-index'));
    $tester->execute([]);

    $count = $this->searchConnection->fetchOne(
        'SELECT COUNT(*) FROM unavailable_periods WHERE room_id = :roomId',
        ['roomId' => self::ROOM_ID],
    );

    self::assertSame('2', (string) $count); // 1 hold + 1 blocked period
}
```

- [ ] **Step 2: Run the test — confirm it fails**

```bash
make functional-test ARGS="--filter=RebuildSearchIndexCommandTest"
```

Expected: `itRebuildsUnavailablePeriods` fails — `unavailable_periods` is empty.

- [ ] **Step 3: Add holds + blocked periods loops to the command, after the base rates block**

```php
$output->write('[5/6] Rebuilding holds... ');
/** @var list<array{id: string, room_id: string, check_in: string, check_out: string}> $holds */
$holds = $this->availabilityConnection->fetchAllAssociative(
    "SELECT id, room_id, check_in, check_out FROM hold WHERE expires_at > NOW()",
);
foreach ($holds as $hold) {
    $this->unavailablePeriodWriter->add(
        sourceId: $hold['id'],
        roomId: $hold['room_id'],
        checkIn: new \DateTimeImmutable($hold['check_in']),
        checkOut: new \DateTimeImmutable($hold['check_out']),
    );
}
$output->writeln(sprintf('%d holds inserted', count($holds)));

$output->write('[6/6] Rebuilding blocked periods... ');
/** @var list<array{id: string, room_id: string, check_in: string, check_out: string}> $blockedPeriods */
$blockedPeriods = $this->availabilityConnection->fetchAllAssociative(
    'SELECT id, room_id, check_in, check_out FROM blocked_period',
);
foreach ($blockedPeriods as $bp) {
    $this->unavailablePeriodWriter->add(
        sourceId: $bp['id'],
        roomId: $bp['room_id'],
        checkIn: new \DateTimeImmutable($bp['check_in']),
        checkOut: new \DateTimeImmutable($bp['check_out']),
    );
}
$output->writeln(sprintf('%d periods inserted', count($blockedPeriods)));

$output->writeln('Done.');
```

- [ ] **Step 4: Run tests**

```bash
make functional-test ARGS="--filter=RebuildSearchIndexCommandTest"
```

Expected: all tests pass.

---

## Task 6 — Verify truncate clears stale data + final checks

**Files:**
- Modify: `tests/Search/Functional/Console/RebuildSearchIndexCommandTest.php`

- [ ] **Step 1: Add a stale-data truncate test**

Add in `RebuildSearchIndexCommandTest`:

```php
#[Test]
public function itClearsStaleDataBeforeRebuilding(): void
{
    // Insert stale data in search tables with a different (non-existent) room type
    $this->searchConnection->executeStatement(
        "INSERT INTO hotel_room_types
            (room_type_id, hotel_id, hotel_name, city, country, room_type_name, guest_capacity, bed_composition, room_amenities, hotel_amenities)
         VALUES
            ('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', :hotelId, 'Stale Hotel', 'Lyon', 'FR', 'Stale Room', 1, '[]', '[]', '[]')",
        ['hotelId' => self::HOTEL_ID],
    );

    $application = new Application(static::$kernel);
    $tester = new CommandTester($application->find('search:rebuild-index'));
    $tester->execute([]);

    $count = $this->searchConnection->fetchOne('SELECT COUNT(*) FROM hotel_room_types');
    self::assertSame('1', (string) $count); // only the real one remains
}
```

- [ ] **Step 2: Run tests**

```bash
make functional-test ARGS="--filter=RebuildSearchIndexCommandTest"
```

Expected: all 6 tests pass.

- [ ] **Step 3: Run full test suite**

```bash
make test
```

Expected: no regressions.

- [ ] **Step 4: Run static analysis and lint**

```bash
make lint
```

Expected: no errors.

- [ ] **Step 5: Final commit**

```bash
git add src/Search/UI/Console/RebuildSearchIndexCommand.php \
        config/services/search.yaml \
        tests/Search/Functional/Console/RebuildSearchIndexCommandTest.php
git commit -m "feat(search): implement search:rebuild-index CLI command

Truncates hotel_room_types, room_index, unavailable_periods then
repopulates from hotel, room, pricing, and availability source schemas."
```

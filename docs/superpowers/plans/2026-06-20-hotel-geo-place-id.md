# Hotel GeoPlaceId Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an optional `geoPlaceId` to the Hotel aggregate's `Address`, validated at registration against the Geo context's referential, per step 4 of the GeoNames referential plan (spec: `docs/superpowers/specs/2026-06-20-hotel-geo-place-id-design.md`).

**Architecture:** Geo publishes a `GeoPlaceCheckerInterface` contract (`App\Geo\Application\Contract`); Hotel consumes it through its own domain port, validating any supplied `geoPlaceId` before persisting a new Hotel. `Address.city` stays free text — `geoPlaceId` is additive and optional everywhere.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL, PostgreSQL 16, PHPUnit.

## Global Constraints

- Naming: PHP properties/params, JSON keys, and the SQL column are all `geoPlaceId` / `geo_place_id` (never `geonamesId`).
- `Address.city` (free text) is unchanged; `geoPlaceId` is optional everywhere (no breaking change to `RegisterHotel`).
- Hotel must not import Geo internals — only `App\Geo\Application\Contract\*` (ADR 0015 / `deptrac-contexts.yaml`).
- No cross-schema foreign key between `hotel.hotel.geo_place_id` and `geo.geo_place` — consistency is enforced at the application layer via the contract.
- Search context read model and availability filtering are out of scope (steps 5-7 of the note).

---

### Task 1: Geo publishes a `GeoPlaceCheckerInterface` contract

**Files:**
- Create: `src/Geo/Application/Contract/GeoPlaceCheckerInterface.php`
- Create: `src/Geo/Infrastructure/Contract/DbalGeoPlaceChecker.php`
- Test: `tests/Geo/Infrastructure/Contract/DbalGeoPlaceCheckerTest.php`
- Modify: `deptrac-contexts.yaml`

**Interfaces:**
- Produces: `App\Geo\Application\Contract\GeoPlaceCheckerInterface::exists(App\Shared\Domain\ValueObject\GeoPlaceId $id): bool`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Geo\Infrastructure\Contract;

use App\Geo\Infrastructure\Contract\DbalGeoPlaceChecker;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DbalGeoPlaceCheckerTest extends TestCase
{
    #[Test]
    public function itReturnsTrueWhenGeoPlaceExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('FROM geo_place'),
                ['id' => '2988507'],
            )
            ->willReturn(1);

        $checker = new DbalGeoPlaceChecker($connection);

        self::assertTrue($checker->exists(new GeoPlaceId('2988507')));
    }

    #[Test]
    public function itReturnsFalseWhenGeoPlaceIsMissing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(0);

        $checker = new DbalGeoPlaceChecker($connection);

        self::assertFalse($checker->exists(new GeoPlaceId('9999999')));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `make unit-test`
Expected: FAIL — `Class "App\Geo\Infrastructure\Contract\DbalGeoPlaceChecker" not found`

- [ ] **Step 3: Create the published contract interface**

```php
<?php

declare(strict_types=1);

namespace App\Geo\Application\Contract;

use App\Shared\Domain\ValueObject\GeoPlaceId;

interface GeoPlaceCheckerInterface
{
    public function exists(GeoPlaceId $id): bool;
}
```

- [ ] **Step 4: Implement the DBAL adapter**

```php
<?php

declare(strict_types=1);

namespace App\Geo\Infrastructure\Contract;

use App\Geo\Application\Contract\GeoPlaceCheckerInterface;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use Doctrine\DBAL\Connection;

final readonly class DbalGeoPlaceChecker implements GeoPlaceCheckerInterface
{
    public function __construct(private Connection $geoConnection)
    {
    }

    public function exists(GeoPlaceId $id): bool
    {
        $count = $this->geoConnection->fetchOne(
            'SELECT COUNT(*) FROM geo_place WHERE geoname_id = :id',
            ['id' => $id->value],
        );

        return $count > 0;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `make unit-test`
Expected: PASS

- [ ] **Step 6: Carve out the `GeoContract` deptrac layer**

In `deptrac-contexts.yaml`, replace the existing `Geo` layer:

```yaml
        -
            name: Geo
            collectors:
                -
                    type: classLike
                    value: 'App\\Geo\\.*'
```

with:

```yaml
        -
            name: Geo
            collectors:
                -
                    type: bool
                    must:
                        -
                            type: classLike
                            value: 'App\\Geo\\.*'
                    must_not:
                        -
                            type: classLike
                            value: 'App\\Geo\\Application\\Contract\\.*'
        -
            name: GeoContract
            collectors:
                -
                    type: classLike
                    value: 'App\\Geo\\Application\\Contract\\.*'
```

In the `ruleset:` section, add a `GeoContract` entry (next to the other `*Contract` entries, after `BookerContract`):

```yaml
        GeoContract:
            - Shared
```

- [ ] **Step 7: Run deptrac to confirm no violations**

Run: `make deptrac`
Expected: PASS, no violations reported for `Geo` or `GeoContract`

- [ ] **Step 8: Commit**

```bash
git add src/Geo/Application/Contract/GeoPlaceCheckerInterface.php src/Geo/Infrastructure/Contract/DbalGeoPlaceChecker.php tests/Geo/Infrastructure/Contract/DbalGeoPlaceCheckerTest.php deptrac-contexts.yaml
git commit -m "feat(geo): publish GeoPlaceCheckerInterface contract"
```

---

### Task 2: Hotel consumes the Geo contract through its own domain port

**Files:**
- Create: `src/Hotel/Domain/Port/GeoPlaceCheckerInterface.php`
- Create: `src/Hotel/Infrastructure/Service/GeoPlaceChecker.php`
- Test: `tests/Hotel/Infrastructure/Service/GeoPlaceCheckerTest.php`
- Modify: `deptrac-contexts.yaml`

**Interfaces:**
- Consumes: `App\Geo\Application\Contract\GeoPlaceCheckerInterface::exists(GeoPlaceId $id): bool` (Task 1)
- Produces: `App\Hotel\Domain\Port\GeoPlaceCheckerInterface::exists(App\Shared\Domain\ValueObject\GeoPlaceId $id): bool`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Infrastructure\Service;

use App\Geo\Application\Contract\GeoPlaceCheckerInterface as GeoPlaceCheckerContract;
use App\Hotel\Infrastructure\Service\GeoPlaceChecker;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GeoPlaceCheckerTest extends TestCase
{
    #[Test]
    public function itDelegatesToTheGeoPublishedContract(): void
    {
        $contract = $this->createStub(GeoPlaceCheckerContract::class);
        $contract->method('exists')->willReturn(true);

        $checker = new GeoPlaceChecker($contract);

        self::assertTrue($checker->exists(new GeoPlaceId('2988507')));
    }

    #[Test]
    public function itReturnsFalseWhenTheContractReportsNoMatch(): void
    {
        $contract = $this->createStub(GeoPlaceCheckerContract::class);
        $contract->method('exists')->willReturn(false);

        $checker = new GeoPlaceChecker($contract);

        self::assertFalse($checker->exists(new GeoPlaceId('0')));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `make unit-test`
Expected: FAIL — `Class "App\Hotel\Infrastructure\Service\GeoPlaceChecker" not found`

- [ ] **Step 3: Create the Hotel domain port**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Port;

use App\Shared\Domain\ValueObject\GeoPlaceId;

interface GeoPlaceCheckerInterface
{
    public function exists(GeoPlaceId $id): bool;
}
```

- [ ] **Step 4: Implement the adapter delegating to Geo's contract**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Infrastructure\Service;

use App\Geo\Application\Contract\GeoPlaceCheckerInterface as GeoPlaceCheckerContract;
use App\Hotel\Domain\Port\GeoPlaceCheckerInterface;
use App\Shared\Domain\ValueObject\GeoPlaceId;

final readonly class GeoPlaceChecker implements GeoPlaceCheckerInterface
{
    public function __construct(private GeoPlaceCheckerContract $geoPlaceChecker)
    {
    }

    public function exists(GeoPlaceId $id): bool
    {
        return $this->geoPlaceChecker->exists($id);
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `make unit-test`
Expected: PASS

- [ ] **Step 6: Allow Hotel to depend on GeoContract in deptrac**

In `deptrac-contexts.yaml`, `ruleset:` section, change:

```yaml
        Hotel:
            - HotelContract
            - Shared
            - Vendor
```

to:

```yaml
        Hotel:
            - HotelContract
            - GeoContract
            - Shared
            - Vendor
```

- [ ] **Step 7: Run deptrac to confirm no violations**

Run: `make deptrac`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add src/Hotel/Domain/Port/GeoPlaceCheckerInterface.php src/Hotel/Infrastructure/Service/GeoPlaceChecker.php tests/Hotel/Infrastructure/Service/GeoPlaceCheckerTest.php deptrac-contexts.yaml
git commit -m "feat(hotel): consume Geo's GeoPlaceCheckerInterface contract"
```

---

### Task 3: Validate `geoPlaceId` at Hotel registration

**Files:**
- Modify: `src/Hotel/Domain/Model/Address.php`
- Create: `src/Hotel/Domain/Exception/InvalidGeoPlaceException.php`
- Modify: `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandler.php`
- Modify: `tests/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandlerTest.php`
- Modify: `CONTEXT.md`

**Interfaces:**
- Consumes: `App\Hotel\Domain\Port\GeoPlaceCheckerInterface::exists(GeoPlaceId $id): bool` (Task 2)
- Produces: `App\Hotel\Domain\Model\Address::$geoPlaceId` (`?App\Shared\Domain\ValueObject\GeoPlaceId`, constructor param order: `streetAddress, postalCode, city, country, geoPlaceId = null`); `App\Hotel\Domain\Exception\InvalidGeoPlaceException`

- [ ] **Step 1: Update the handler test (red) — add the new constructor dependency and two new cases**

Replace the full contents of `tests/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommand;
use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommandHandler;
use App\Hotel\Domain\Exception\InvalidGeoPlaceException;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Port\GeoPlaceCheckerInterface;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Domain\Event\HotelRegistered;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use App\Shared\Domain\ValueObject\HotelId;
use App\Tests\Fake\FakeEventDispatcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegisterHotelCommandHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesHotelRegisteredOnSuccess(): void
    {
        $repository = $this->createStub(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();
        $geoPlaceChecker = $this->createStub(GeoPlaceCheckerInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(false);

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher, $geoPlaceChecker);
        ($handler)(new RegisterHotelCommand(
            id: new HotelId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'),
            name: 'Le Grand Hôtel',
            address: new Address('1 rue de la Paix', '75001', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        ));

        $event = $dispatcher->getLastDispatched();
        self::assertInstanceOf(HotelRegistered::class, $event);
        self::assertSame('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', $event->hotelId);
        self::assertSame('Le Grand Hôtel', $event->name);
        self::assertSame('Paris', $event->city);
        self::assertSame('FR', $event->country);
        self::assertNull($event->starRating);
    }

    #[Test]
    public function itDispatchesStarRatingWhenProvided(): void
    {
        $repository = $this->createStub(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();
        $geoPlaceChecker = $this->createStub(GeoPlaceCheckerInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(false);

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher, $geoPlaceChecker);
        ($handler)(new RegisterHotelCommand(
            id: new HotelId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a12'),
            name: 'Luxury Palace',
            address: new Address('5 avenue Foch', '75016', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            starRating: new StarRating(4, false),
        ));

        $event = $dispatcher->getLastDispatched();
        self::assertInstanceOf(HotelRegistered::class, $event);
        self::assertSame(4, $event->starRating);
    }

    #[Test]
    public function itDoesNotDispatchWhenHotelAlreadyExists(): void
    {
        $repository = $this->createStub(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();
        $geoPlaceChecker = $this->createStub(GeoPlaceCheckerInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(true);

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher, $geoPlaceChecker);

        try {
            ($handler)(new RegisterHotelCommand(
                id: new HotelId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a13'),
                name: 'Le Grand Hôtel',
                address: new Address('1 rue de la Paix', '75001', 'Paris', 'FR'),
                createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            ));
            self::fail('Expected HotelAlreadyExistsException was not thrown');
        } catch (\App\Hotel\Domain\Exception\HotelAlreadyExistsException) {
            // expected
        }

        self::assertEmpty($dispatcher->getDispatched());
    }

    #[Test]
    public function itRegistersAHotelWithAValidGeoPlaceId(): void
    {
        $repository = $this->createStub(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();
        $geoPlaceChecker = $this->createStub(GeoPlaceCheckerInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(false);
        $geoPlaceChecker->method('exists')->willReturn(true);

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher, $geoPlaceChecker);
        ($handler)(new RegisterHotelCommand(
            id: new HotelId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a14'),
            name: 'Hotel Ibis Paris',
            address: new Address('15 rue de Rivoli', '75001', 'Paris', 'FR', new GeoPlaceId('2988507')),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        ));

        self::assertInstanceOf(HotelRegistered::class, $dispatcher->getLastDispatched());
    }

    #[Test]
    public function itRejectsAnUnknownGeoPlaceId(): void
    {
        $repository = $this->createStub(HotelRepositoryInterface::class);
        $dispatcher = new FakeEventDispatcher();
        $geoPlaceChecker = $this->createStub(GeoPlaceCheckerInterface::class);

        $repository->method('existsByNameAndAddress')->willReturn(false);
        $geoPlaceChecker->method('exists')->willReturn(false);

        $handler = new RegisterHotelCommandHandler($repository, $dispatcher, $geoPlaceChecker);

        try {
            ($handler)(new RegisterHotelCommand(
                id: new HotelId('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a15'),
                name: 'Hotel Ibis Paris',
                address: new Address('15 rue de Rivoli', '75001', 'Paris', 'FR', new GeoPlaceId('9999999')),
                createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            ));
            self::fail('Expected InvalidGeoPlaceException was not thrown');
        } catch (InvalidGeoPlaceException) {
            // expected
        }

        self::assertEmpty($dispatcher->getDispatched());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `make unit-test`
Expected: FAIL — `Too few arguments to function ...RegisterHotelCommandHandler::__construct()` and `Unknown named parameter $geoPlaceId` on `Address`

- [ ] **Step 3: Add `geoPlaceId` to `Address`**

Replace the full contents of `src/Hotel/Domain/Model/Address.php`:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Model;

use App\Shared\Domain\ValueObject\GeoPlaceId;

final readonly class Address
{
    public function __construct(
        public string $streetAddress,
        public string $postalCode,
        public string $city,
        public string $country,
        public ?GeoPlaceId $geoPlaceId = null,
    ) {
    }
}
```

- [ ] **Step 4: Create the domain exception**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Exception;

final class InvalidGeoPlaceException extends \DomainException
{
    public function __construct(string $geoPlaceId)
    {
        parent::__construct(\sprintf('Geo place "%s" does not exist in the Geo referential.', $geoPlaceId));
    }
}
```

- [ ] **Step 5: Validate `geoPlaceId` in the handler**

Replace the full contents of `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Domain\Exception\HotelAlreadyExistsException;
use App\Hotel\Domain\Exception\InvalidGeoPlaceException;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\GeoPlaceCheckerInterface;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use App\Shared\Domain\Event\HotelRegistered;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class RegisterHotelCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private HotelRepositoryInterface $hotelRepository,
        private EventDispatcherInterface $eventDispatcher,
        private GeoPlaceCheckerInterface $geoPlaceChecker,
    ) {
    }

    public function __invoke(RegisterHotelCommand $command): void
    {
        if ($this->hotelRepository->existsByNameAndAddress($command->name, $command->address)) {
            throw new HotelAlreadyExistsException($command->name, $command->address->city);
        }

        $geoPlaceId = $command->address->geoPlaceId;
        if (null !== $geoPlaceId && !$this->geoPlaceChecker->exists($geoPlaceId)) {
            throw new InvalidGeoPlaceException($geoPlaceId->value);
        }

        $hotel = new Hotel($command->id, $command->name, $command->address, $command->createdAt, $command->starRating);

        $this->hotelRepository->add($hotel);

        $this->eventDispatcher->dispatch(new HotelRegistered(
            hotelId: $hotel->id->value,
            name: $hotel->name,
            city: $hotel->address->city,
            country: $hotel->address->country,
            starRating: $hotel->starRating?->stars,
            createdAt: $hotel->createdAt,
        ));
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `make unit-test`
Expected: PASS

- [ ] **Step 7: Update the domain glossary**

In `CONTEXT.md`, replace:

```markdown
**Address**:
The physical location of a Hotel, composed of street address, postal code, city, and country (ISO 3166-1 alpha-2). Two hotels with the same name are distinct if their addresses differ.
_Avoid_: location, place
```

with:

```markdown
**Address**:
The physical location of a Hotel, composed of street address, postal code, city, and country (ISO 3166-1 alpha-2). May optionally reference a **Geo Place** by its GeoNames id, captured via Geo Place Search, to disambiguate the free-text city. Two hotels with the same name are distinct if their addresses differ.
_Avoid_: location, place
```

And in the `## Relationships` section, after `- A **Hotel** has exactly one **Address**`, add:

```markdown
- An **Address** may reference at most one **Geo Place** (optional, via its GeoNames id)
```

- [ ] **Step 8: Commit**

```bash
git add src/Hotel/Domain/Model/Address.php src/Hotel/Domain/Exception/InvalidGeoPlaceException.php src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandler.php tests/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandlerTest.php CONTEXT.md
git commit -m "feat(hotel): validate geoPlaceId against the Geo referential at registration"
```

---

### Task 4: Persist `geoPlaceId` on the Hotel aggregate

**Files:**
- Create: `migrations/Version20260620090000.php`
- Modify: `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php`
- Test: `tests/Hotel/Infrastructure/Persistence/Doctrine/HotelRepositoryGeoPlaceTest.php`

**Interfaces:**
- Consumes: `Address::$geoPlaceId` (Task 3)
- Produces: `hotel.hotel.geo_place_id` column (nullable `VARCHAR(255)`)

- [ ] **Step 1: Write the failing integration test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Infrastructure\Persistence\Doctrine\HotelRepository;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use App\Shared\Domain\ValueObject\HotelId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class HotelRepositoryGeoPlaceTest extends KernelTestCase
{
    private const ID_WITH_GEO_PLACE = 'a2000000-0000-4000-8000-000000000001';
    private const ID_WITHOUT_GEO_PLACE = 'a2000000-0000-4000-8000-000000000002';
    private HotelRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(HotelRepository::class);
    }

    #[Test]
    public function itSaveAndReloadAGeoPlaceId(): void
    {
        $hotel = new Hotel(
            new HotelId(self::ID_WITH_GEO_PLACE),
            'Hotel With Geo Place',
            new Address('1 rue Test', '75001', 'Paris', 'FR', new GeoPlaceId('2988507')),
            new \DateTimeImmutable('2025-01-01'),
        );
        $this->repository->add($hotel);

        $reloaded = $this->repository->get(new HotelId(self::ID_WITH_GEO_PLACE));
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->address->geoPlaceId);
        self::assertSame('2988507', $reloaded->address->geoPlaceId->value);
    }

    #[Test]
    public function itSaveAndReloadANullGeoPlaceId(): void
    {
        $hotel = new Hotel(
            new HotelId(self::ID_WITHOUT_GEO_PLACE),
            'Hotel Without Geo Place',
            new Address('2 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable('2025-01-01'),
        );
        $this->repository->add($hotel);

        $reloaded = $this->repository->get(new HotelId(self::ID_WITHOUT_GEO_PLACE));
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->address->geoPlaceId);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `make functional-test`
Expected: FAIL — `SQLSTATE[42703]: column "geo_place_id" of relation "hotel" does not exist` (or similar, depending on insert order)

- [ ] **Step 3: Create the migration**

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional geo_place_id to hotel.hotel table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel.hotel ADD COLUMN geo_place_id VARCHAR(255) DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN hotel.hotel.geo_place_id IS 'GeoNames id disambiguating the free-text city — validated against geo.geo_place at registration, not a foreign key (contexts stay decoupled at the DB level)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel.hotel DROP COLUMN geo_place_id');
    }
}
```

- [ ] **Step 4: Run the migration**

Run: `make migrate`
Expected: Migration `Version20260620090000` applied successfully

- [ ] **Step 5: Persist and read `geo_place_id` in `HotelRepository`**

In `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php`:

Add the import:

```php
use App\Shared\Domain\ValueObject\GeoPlaceId;
```

In `add()`, change the insert array to:

```php
$this->hotelConnection->insert('hotel', [
    'id' => $hotel->id->value,
    'name' => $hotel->name,
    'street_address' => $hotel->address->streetAddress,
    'postal_code' => $hotel->address->postalCode,
    'city' => $hotel->address->city,
    'country' => $hotel->address->country,
    'geo_place_id' => $hotel->address->geoPlaceId?->value,
    'search_key' => $this->buildSearchKey($hotel->name, $hotel->address),
    'created_at' => $hotel->createdAt->format('Y-m-d H:i:s'),
    'stars' => $hotel->starRating?->stars,
    'superior' => null !== $hotel->starRating ? $hotel->starRating->superior : false,
    'amenities' => $this->serializeAmenities($hotel->amenities),
], [
    'superior' => Types::BOOLEAN,
]);
```

In `get()`, change the SQL and the `@var` annotation:

```php
/** @var array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, geo_place_id: string|null, created_at: string, stars: int|null, superior: string|bool, amenities: string}|false $row */
$row = $this->hotelConnection->fetchAssociative(
    'SELECT id, name, street_address, postal_code, city, country, geo_place_id, created_at, stars, superior, amenities FROM hotel WHERE id = :id',
    ['id' => $id->value],
);
```

In `list()`, change the SQL and the `@var` annotation:

```php
/** @var list<array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, geo_place_id: string|null, created_at: string, stars: int|null, superior: string|bool, amenities: string}> $rows */
$rows = $this->hotelConnection->fetchAllAssociative(
    "SELECT id, name, street_address, postal_code, city, country, geo_place_id, created_at, stars, superior, amenities FROM hotel {$where} ORDER BY name ASC LIMIT :limit OFFSET :offset",
    $params,
);
```

In `hydrate()`, change the signature's `@param` annotation to match the new row shape and build `Address` with `geoPlaceId`:

```php
/**
 * @param array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, geo_place_id: string|null, created_at: string, stars: int|null, superior: string|bool, amenities: string} $row
 */
private function hydrate(array $row): Hotel
{
    $starRating = null !== $row['stars']
        ? new StarRating((int) $row['stars'], 't' === $row['superior'] || true === $row['superior'])
        : null;

    return new Hotel(
        new HotelId($row['id']),
        $row['name'],
        new Address(
            $row['street_address'],
            $row['postal_code'],
            $row['city'],
            $row['country'],
            null !== $row['geo_place_id'] ? new GeoPlaceId((string) $row['geo_place_id']) : null,
        ),
        new \DateTimeImmutable($row['created_at']),
        $starRating,
        $this->parseAmenities($row['amenities']),
    );
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `make functional-test`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add migrations/Version20260620090000.php src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php tests/Hotel/Infrastructure/Persistence/Doctrine/HotelRepositoryGeoPlaceTest.php
git commit -m "feat(hotel): persist geoPlaceId on the hotel.hotel table"
```

---

### Task 5: Expose `geoPlaceId` through the back-office API

**Files:**
- Modify: `src/Hotel/Application/Service/RegisterHotelCommandFactory.php`
- Modify: `tests/Hotel/Application/Service/RegisterHotelCommandFactoryTest.php`
- Modify: `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelRequest.php`
- Modify: `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelController.php`
- Modify: `src/Hotel/UI/Http/Controller/HotelSerializer.php`
- Modify: `config/services/exceptions.yaml`
- Modify: `tests/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelControllerTest.php`

**Interfaces:**
- Consumes: `Address::$geoPlaceId` (Task 3), `InvalidGeoPlaceException` (Task 3)
- Produces: `RegisterHotelCommandFactory::create(..., ?string $geoPlaceId = null)`; JSON key `geoPlaceId` (nullable string) on all Hotel-serializing endpoints

- [ ] **Step 1: Add a failing factory test**

In `tests/Hotel/Application/Service/RegisterHotelCommandFactoryTest.php`, add (inside the existing class, after `itThrowsWhenAnyFieldIsNull`):

```php
    #[Test]
    public function itBuildsAddressWithGeoPlaceIdWhenProvided(): void
    {
        $command = $this->factory->create(
            name: 'Hotel Ibis Paris',
            streetAddress: '15 rue de Rivoli',
            postalCode: '75001',
            city: 'Paris',
            country: 'FR',
            geoPlaceId: '2988507',
        );

        self::assertNotNull($command->address->geoPlaceId);
        self::assertSame('2988507', $command->address->geoPlaceId->value);
    }

    #[Test]
    public function itBuildsAddressWithoutGeoPlaceIdWhenNull(): void
    {
        $command = $this->factory->create(
            name: 'Hotel Ibis Paris',
            streetAddress: '15 rue de Rivoli',
            postalCode: '75001',
            city: 'Paris',
            country: 'FR',
        );

        self::assertNull($command->address->geoPlaceId);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `make unit-test`
Expected: FAIL — `Unknown named parameter $geoPlaceId`

- [ ] **Step 3: Update `RegisterHotelCommandFactory`**

Replace the full contents of `src/Hotel/Application/Service/RegisterHotelCommandFactory.php`:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\Service;

use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommand;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Port\HotelIdGeneratorInterface;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Domain\ValueObject\GeoPlaceId;
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
        ?string $geoPlaceId = null,
    ): RegisterHotelCommand {
        if (null === $name || null === $streetAddress || null === $postalCode || null === $city || null === $country) {
            throw new \InvalidArgumentException('All hotel fields are required.');
        }

        $starRating = null !== $stars ? new StarRating($stars, $superior) : null;

        return new RegisterHotelCommand(
            $this->hotelIdGenerator->generate(),
            $name,
            new Address(
                $streetAddress,
                $postalCode,
                $city,
                $country,
                null !== $geoPlaceId ? new GeoPlaceId($geoPlaceId) : null,
            ),
            $this->clock->now(),
            $starRating,
        );
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `make unit-test`
Expected: PASS

- [ ] **Step 5: Add `geoPlaceId` to the request DTO**

In `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelRequest.php`, add after the `superior` property:

```php
        #[Assert\Regex(pattern: '/^\d+$/', message: 'geoPlaceId must be a numeric string.')]
        #[OA\Property(type: 'string', example: '2988507', nullable: true, description: 'GeoNames id selected via the Geo Place Search autocomplete (GET /geo/places)')]
        public ?string $geoPlaceId = null,
```

- [ ] **Step 6: Pass `geoPlaceId` through the controller, document the field, and map the new exception**

In `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelController.php`, in the `__invoke` method, change the factory call to:

```php
        $command = $this->commandFactory->create(
            $request->name,
            $request->streetAddress,
            $request->postalCode,
            $request->city,
            $request->country,
            $request->stars,
            $request->superior,
            $request->geoPlaceId,
        );
```

In the same file's `#[OA\Post]` success response `OA\JsonContent` properties list, add after the `country` property:

```php
                        new OA\Property(property: 'geoPlaceId', type: 'string', nullable: true, example: '2988507'),
```

In `config/services/exceptions.yaml`, add after the `App\Hotel\Domain\Exception\HotelNotFoundException` entry:

```yaml
                App\Hotel\Domain\Exception\InvalidGeoPlaceException:
                    type: 'https://book.it/problems/invalid-geo-place'
                    title: 'Invalid Geo Place'
                    status: 422
```

- [ ] **Step 7: Add `geoPlaceId` to `HotelSerializer`**

Replace the full contents of `src/Hotel/UI/Http/Controller/HotelSerializer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller;

use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\ValueObject\HotelAmenity;

final class HotelSerializer
{
    /**
     * @return array{id: string, name: string, streetAddress: string, postalCode: string, city: string, country: string, geoPlaceId: string|null, createdAt: string, starRating: array{stars: int, superior: bool}|null, amenities: string[]}
     */
    public function serialize(Hotel $hotel): array
    {
        return [
            'id' => $hotel->id->value,
            'name' => $hotel->name,
            'streetAddress' => $hotel->address->streetAddress,
            'postalCode' => $hotel->address->postalCode,
            'city' => $hotel->address->city,
            'country' => $hotel->address->country,
            'geoPlaceId' => $hotel->address->geoPlaceId?->value,
            'createdAt' => $hotel->createdAt->format(\DateTimeInterface::ATOM),
            'starRating' => null !== $hotel->starRating
                ? ['stars' => $hotel->starRating->stars, 'superior' => $hotel->starRating->superior]
                : null,
            'amenities' => array_map(static fn(HotelAmenity $a) => $a->value, $hotel->amenities),
        ];
    }
}
```

- [ ] **Step 8: Regenerate the OpenAPI spec**

Run: `make openapi`
Expected: `openapi.yaml` updated with the `geoPlaceId` request/response fields

- [ ] **Step 9: Write the failing functional tests**

In `tests/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelControllerTest.php`, add (inside the existing class, after `itRegistersAHotelAndReturns201`):

```php
    #[Test]
    public function itRegistersAHotelWithAValidGeoPlaceIdAndReturns201(): void
    {
        self::getContainer()->get('doctrine.dbal.geo_connection')->insert('geo_place', [
            'geoname_id' => 2988507,
            'name' => 'Paris',
            'ascii_name' => 'Paris',
            'country_code' => 'FR',
            'admin1_code' => '11',
        ]);

        $client = static::createAuthenticatedClient();

        $payload = array_merge(self::VALID_PAYLOAD, ['geoPlaceId' => '2988507']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{geoPlaceId: string|null} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('2988507', $body['geoPlaceId']);
    }

    #[Test]
    public function itReturns422WhenGeoPlaceIdDoesNotExist(): void
    {
        $client = static::createAuthenticatedClient();

        $payload = array_merge(self::VALID_PAYLOAD, ['geoPlaceId' => '9999999']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/invalid-geo-place', $body['type']);
        self::assertSame('Invalid Geo Place', $body['title']);
    }

    #[Test]
    public function itRegistersAHotelWithoutAGeoPlaceIdAndReturns201(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{geoPlaceId: string|null} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNull($body['geoPlaceId']);
    }
```

- [ ] **Step 10: Run the tests to verify they pass**

Run: `make functional-test`
Expected: PASS — all three new cases plus the existing `RegisterHotelControllerTest` cases pass

- [ ] **Step 11: Run the full lint and test suite**

Run: `make lint && make test`
Expected: PASS — CS Fixer, PHPStan, Deptrac (both configs), unit, integration, and functional tests all green

- [ ] **Step 12: Commit**

```bash
git add src/Hotel/Application/Service/RegisterHotelCommandFactory.php tests/Hotel/Application/Service/RegisterHotelCommandFactoryTest.php src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelRequest.php src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelController.php src/Hotel/UI/Http/Controller/HotelSerializer.php config/services/exceptions.yaml tests/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelControllerTest.php openapi.yaml
git commit -m "feat(hotel): expose geoPlaceId on hotel registration and read endpoints"
```

---

## Self-Review Notes

- **Spec coverage:** Domain model (`Address.geoPlaceId`) — Task 3. Geo published contract — Task 1. Hotel domain port + adapter — Task 2. Registration validation — Task 3. Persistence — Task 4. Request/response/OpenAPI/exception mapping — Task 5. CONTEXT.md glossary update — Task 3. All spec sections covered; Search/availability/front (steps 5-7) explicitly out of scope per the spec and not included here.
- **Naming consistency:** `geoPlaceId` used uniformly across `Address`, ports, factory, request, serializer; `geo_place_id` for the SQL column; verified no leftover `geonamesId` naming.
- **Type consistency:** `GeoPlaceCheckerInterface::exists(GeoPlaceId $id): bool` signature identical in Geo's contract (Task 1), Hotel's port (Task 2), and the adapter (Task 2) that bridges them — checked across all three tasks.

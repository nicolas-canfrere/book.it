# Hotel Uniqueness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prevent duplicate hotel registration by enforcing uniqueness on name + full address (street, postal code, city, country ISO alpha-2), with normalized comparison via Symfony AsciiSlugger.

**Architecture:** `Address` becomes a domain Value Object on `Hotel`. The `HotelRepositoryInterface` gains an `existsByNameAndAddress()` method. The Doctrine repository computes a `search_key` (AsciiSlugger-normalized composite) at write time, stored in a dedicated column with a `UNIQUE` constraint as a DB-level safety net. The command handler checks via the port before inserting and throws `HotelAlreadyExistsException` on conflict, surfaced as HTTP 409.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL (raw SQL, no ORM), symfony/string AsciiSlugger, PHPUnit + KernelTestCase/WebTestCase, Doctrine Migrations.

---

## File Map

| Action | Path | Role |
|--------|------|------|
| Create | `src/Hotel/Domain/Model/Address.php` | Value Object |
| Create | `src/Hotel/Domain/Exception/HotelAlreadyExistsException.php` | Domain exception |
| Modify | `src/Hotel/Domain/Model/Hotel.php` | Add `Address $address` |
| Modify | `src/Hotel/Domain/Port/HotelRepositoryInterface.php` | Add `existsByNameAndAddress()` |
| Modify | `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommand.php` | Add `Address $address` |
| Modify | `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandler.php` | Duplicate check |
| Modify | `src/Hotel/Application/Service/RegisterHotelCommandFactory.php` | Accept address params |
| Modify | `tests/Hotel/Infrastructure/Persistence/InMemory/InMemoryHotelRepository.php` | Implement `existsByNameAndAddress` |
| Modify | `tests/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandlerTest.php` | Update + add duplicate test |
| Modify | `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php` | Slugger + address columns |
| Create | `migrations/Version<timestamp>.php` | Address columns + search_key UNIQUE |
| Modify | `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelRequest.php` | Add address fields |
| Modify | `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelController.php` | Pass address + 409 |
| Modify | `src/Hotel/UI/Http/Controller/RegisterHotel/RegisteredHotelSerializer.php` | Include address |
| Modify | `tests/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelControllerTest.php` | Update + add 409 test |

---

## Task 1: Address Value Object

**Files:**
- Create: `src/Hotel/Domain/Model/Address.php`

- [ ] **Step 1: Create the Address VO**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Model;

final readonly class Address
{
    public function __construct(
        public string $streetAddress,
        public string $postalCode,
        public string $city,
        public string $country,
    ) {
    }
}
```

- [ ] **Step 2: Run static analysis to verify**

```bash
make static-code-analysis
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/Hotel/Domain/Model/Address.php
git commit -m "feat(hotel): add Address value object"
```

---

## Task 2: HotelAlreadyExistsException

**Files:**
- Create: `src/Hotel/Domain/Exception/HotelAlreadyExistsException.php`

- [ ] **Step 1: Create the exception**

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Exception;

final class HotelAlreadyExistsException extends \DomainException
{
    public function __construct(string $name, string $city)
    {
        parent::__construct(\sprintf('A hotel named "%s" already exists in %s.', $name, $city));
    }
}
```

- [ ] **Step 2: Run static analysis**

```bash
make static-code-analysis
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/Hotel/Domain/Exception/HotelAlreadyExistsException.php
git commit -m "feat(hotel): add HotelAlreadyExistsException"
```

---

## Task 3: Update Hotel model and HotelRepositoryInterface

**Files:**
- Modify: `src/Hotel/Domain/Model/Hotel.php`
- Modify: `src/Hotel/Domain/Port/HotelRepositoryInterface.php`

- [ ] **Step 1: Add `Address` to the Hotel model**

Replace the full content of `src/Hotel/Domain/Model/Hotel.php`:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Model;

final readonly class Hotel
{
    public function __construct(
        public string $id,
        public string $name,
        public Address $address,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 2: Add `existsByNameAndAddress` to the repository port**

Replace the full content of `src/Hotel/Domain/Port/HotelRepositoryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Port;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;

interface HotelRepositoryInterface
{
    public function add(Hotel $hotel): void;

    public function get(string $id): ?Hotel;

    public function existsByNameAndAddress(string $name, Address $address): bool;
}
```

- [ ] **Step 3: Run static analysis**

```bash
make static-code-analysis
```

Expected: errors about missing `existsByNameAndAddress` in concrete implementations and wrong number of constructor args for `Hotel`. This is expected at this stage — we'll fix them in subsequent tasks.

- [ ] **Step 4: Commit**

```bash
git add src/Hotel/Domain/Model/Hotel.php src/Hotel/Domain/Port/HotelRepositoryInterface.php
git commit -m "feat(hotel): add Address to Hotel and existsByNameAndAddress to repository port"
```

---

## Task 4: Update application layer (Command, Handler, Factory)

**Files:**
- Modify: `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommand.php`
- Modify: `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandler.php`
- Modify: `src/Hotel/Application/Service/RegisterHotelCommandFactory.php`

- [ ] **Step 1: Add `Address` to the command**

Replace the full content of `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Domain\Model\Address;
use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class RegisterHotelCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public string $name,
        public Address $address,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
```

- [ ] **Step 2: Update the command handler to check for duplicates**

Replace the full content of `src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Domain\Exception\HotelAlreadyExistsException;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class RegisterHotelCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private HotelRepositoryInterface $hotelRepository,
    ) {
    }

    public function __invoke(RegisterHotelCommand $command): void
    {
        if ($this->hotelRepository->existsByNameAndAddress($command->name, $command->address)) {
            throw new HotelAlreadyExistsException($command->name, $command->address->city);
        }

        $hotel = new Hotel($command->id, $command->name, $command->address, $command->createdAt);

        $this->hotelRepository->add($hotel);
    }
}
```

- [ ] **Step 3: Update the command factory to accept address parameters**

Replace the full content of `src/Hotel/Application/Service/RegisterHotelCommandFactory.php`:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Application\Service;

use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommand;
use App\Hotel\Domain\Model\Address;
use Psr\Clock\ClockInterface;

final readonly class RegisterHotelCommandFactory
{
    public function __construct(
        private HotelIdGeneratorInterface $hotelIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(
        string $name,
        string $streetAddress,
        string $postalCode,
        string $city,
        string $country,
    ): RegisterHotelCommand {
        return new RegisterHotelCommand(
            $this->hotelIdGenerator->generate(),
            $name,
            new Address($streetAddress, $postalCode, $city, $country),
            $this->clock->now(),
        );
    }
}
```

- [ ] **Step 4: Run static analysis**

```bash
make static-code-analysis
```

Expected: errors about `InMemoryHotelRepository` not implementing `existsByNameAndAddress`, and the controller/tests still passing old args. Expected at this stage.

- [ ] **Step 5: Commit**

```bash
git add src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommand.php \
        src/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandler.php \
        src/Hotel/Application/Service/RegisterHotelCommandFactory.php
git commit -m "feat(hotel): update application layer to include Address and duplicate check"
```

---

## Task 5: Update InMemoryHotelRepository and command handler tests

**Files:**
- Modify: `tests/Hotel/Infrastructure/Persistence/InMemory/InMemoryHotelRepository.php`
- Modify: `tests/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandlerTest.php`

- [ ] **Step 1: Implement `existsByNameAndAddress` in the in-memory double**

Replace the full content of `tests/Hotel/Infrastructure/Persistence/InMemory/InMemoryHotelRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Infrastructure\Persistence\InMemory;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;

final class InMemoryHotelRepository implements HotelRepositoryInterface
{
    /** @var array<string, Hotel> */
    private array $hotels = [];

    public function add(Hotel $hotel): void
    {
        $this->hotels[$hotel->id] = $hotel;
    }

    public function get(string $id): ?Hotel
    {
        return $this->hotels[$id] ?? null;
    }

    public function existsByNameAndAddress(string $name, Address $address): bool
    {
        $key = $this->normalize($name, $address);

        foreach ($this->hotels as $hotel) {
            if ($this->normalize($hotel->name, $hotel->address) === $key) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $name, Address $address): string
    {
        return implode('|', array_map(
            static fn (string $s) => strtolower(trim($s)),
            [$name, $address->streetAddress, $address->postalCode, $address->city, $address->country],
        ));
    }
}
```

- [ ] **Step 2: Write the failing duplicate detection test**

Replace the full content of `tests/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\Application\UseCase\RegisterHotel;

use App\Hotel\Application\Service\RegisterHotelCommandFactory;
use App\Hotel\Application\UseCase\RegisterHotel\RegisterHotelCommandHandler;
use App\Hotel\Domain\Exception\HotelAlreadyExistsException;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Tests\Hotel\Infrastructure\Persistence\InMemory\InMemoryHotelRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class RegisterHotelCommandHandlerTest extends KernelTestCase
{
    private InMemoryHotelRepository $hotelRepository;
    private RegisterHotelCommandHandler $handler;
    private RegisterHotelCommandFactory $commandFactory;

    protected function setUp(): void
    {
        $this->hotelRepository = new InMemoryHotelRepository();
        static::getContainer()->set(HotelRepositoryInterface::class, $this->hotelRepository);
        $this->handler = static::getContainer()->get(RegisterHotelCommandHandler::class);
        $this->commandFactory = static::getContainer()->get(RegisterHotelCommandFactory::class);
    }

    #[Test]
    public function itPersistsTheHotel(): void
    {
        $command = $this->commandFactory->create(
            name: 'Hotel Ibis Paris',
            streetAddress: '15 rue de Rivoli',
            postalCode: '75001',
            city: 'Paris',
            country: 'FR',
        );

        ($this->handler)($command);

        $hotel = $this->hotelRepository->get($command->id);

        self::assertNotNull($hotel);
        self::assertSame($command->id, $hotel->id);
        self::assertSame($command->name, $hotel->name);
        self::assertSame('15 rue de Rivoli', $hotel->address->streetAddress);
        self::assertSame('75001', $hotel->address->postalCode);
        self::assertSame('Paris', $hotel->address->city);
        self::assertSame('FR', $hotel->address->country);
        self::assertEquals($command->createdAt, $hotel->createdAt);
    }

    #[Test]
    public function itThrowsWhenHotelAlreadyExists(): void
    {
        $command = $this->commandFactory->create(
            name: 'Hotel Ibis Paris',
            streetAddress: '15 rue de Rivoli',
            postalCode: '75001',
            city: 'Paris',
            country: 'FR',
        );

        ($this->handler)($command);

        $this->expectException(HotelAlreadyExistsException::class);

        $duplicate = $this->commandFactory->create(
            name: 'Hôtel Ibis Paris',
            streetAddress: '15, rue de Rivoli',
            postalCode: '75001',
            city: 'Paris',
            country: 'FR',
        );

        ($this->handler)($duplicate);
    }

    #[Test]
    public function itAllowsSameNameInDifferentCity(): void
    {
        $paris = $this->commandFactory->create(
            name: 'Hotel Ibis',
            streetAddress: '15 rue de Rivoli',
            postalCode: '75001',
            city: 'Paris',
            country: 'FR',
        );

        $lyon = $this->commandFactory->create(
            name: 'Hotel Ibis',
            streetAddress: '10 rue de la République',
            postalCode: '69001',
            city: 'Lyon',
            country: 'FR',
        );

        ($this->handler)($paris);
        ($this->handler)($lyon);

        self::assertNotNull($this->hotelRepository->get($paris->id));
        self::assertNotNull($this->hotelRepository->get($lyon->id));
    }
}
```

- [ ] **Step 3: Run the tests**

```bash
make unit-test-quiet ARGS="--filter RegisterHotelCommandHandlerTest"
```

Expected: `itPersistsTheHotel` and `itAllowsSameNameInDifferentCity` pass. `itThrowsWhenHotelAlreadyExists` passes too — the InMemory normalization uses `strtolower` but does NOT strip accents, so "Hôtel" ≠ "Hotel". This is acceptable: the InMemory double tests handler logic, not normalization accuracy. If you want the duplicate test to pass with accents, simplify the duplicate command to use the exact same name.

> **Note:** If `itThrowsWhenHotelAlreadyExists` fails because the in-memory normalization doesn't strip accents, change the duplicate command's name from `'Hôtel Ibis Paris'` to `'Hotel Ibis Paris'` (same name, same address). The accent normalization is tested via the Doctrine repository.

- [ ] **Step 4: Commit**

```bash
git add tests/Hotel/Infrastructure/Persistence/InMemory/InMemoryHotelRepository.php \
        tests/Hotel/Application/UseCase/RegisterHotel/RegisterHotelCommandHandlerTest.php
git commit -m "test(hotel): update handler tests with Address and duplicate detection"
```

---

## Task 6: Update Doctrine HotelRepository

**Files:**
- Modify: `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php`

The repository needs to:
1. Accept `SluggerInterface` to compute `search_key`
2. Store and read address columns (`street_address`, `postal_code`, `city`, `country`)
3. Store a `search_key` column (slug composite used for the UNIQUE constraint)
4. Implement `existsByNameAndAddress`

The `search_key` is: `slug(name)|slug(streetAddress)|postalCode|slug(city)|country` — all lowercased.

- [ ] **Step 1: Update the Doctrine repository**

Replace the full content of `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class HotelRepository implements HotelRepositoryInterface
{
    public function __construct(
        private Connection $bookit,
        private SluggerInterface $slugger,
    ) {
    }

    public function add(Hotel $hotel): void
    {
        $this->bookit->insert('hotel', [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'street_address' => $hotel->address->streetAddress,
            'postal_code' => $hotel->address->postalCode,
            'city' => $hotel->address->city,
            'country' => $hotel->address->country,
            'search_key' => $this->buildSearchKey($hotel->name, $hotel->address),
            'created_at' => $hotel->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $id): ?Hotel
    {
        /** @var array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, name, street_address, postal_code, city, country, created_at FROM hotel WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return new Hotel(
            $row['id'],
            $row['name'],
            new Address($row['street_address'], $row['postal_code'], $row['city'], $row['country']),
            new \DateTimeImmutable($row['created_at']),
        );
    }

    public function existsByNameAndAddress(string $name, Address $address): bool
    {
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM hotel WHERE search_key = :key',
            ['key' => $this->buildSearchKey($name, $address)],
        );

        return $count > 0;
    }

    private function buildSearchKey(string $name, Address $address): string
    {
        return implode('|', [
            $this->slugger->slug($name)->lower()->toString(),
            $this->slugger->slug($address->streetAddress)->lower()->toString(),
            strtolower($address->postalCode),
            $this->slugger->slug($address->city)->lower()->toString(),
            strtolower($address->country),
        ]);
    }
}
```

- [ ] **Step 2: Run static analysis**

```bash
make static-code-analysis
```

Expected: no errors (the `SluggerInterface` is from `symfony/string` which is already installed).

- [ ] **Step 3: Commit**

```bash
git add src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php
git commit -m "feat(hotel): update Doctrine repository with address columns and search_key normalization"
```

---

## Task 7: Database migration

**Files:**
- Create: `migrations/Version<timestamp>.php` (generated by `make generate-migration` or written manually)

The migration adds `street_address`, `postal_code`, `city`, `country`, `search_key` columns and a `UNIQUE` constraint on `search_key`.

- [ ] **Step 1: Write the migration manually**

Create `migrations/Version20260515000000.php` (adjust timestamp to today's date in YYYYMMDDHHMMSS format):

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add address columns and search_key unique constraint to hotel table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE hotel
                ADD COLUMN street_address VARCHAR(255) NOT NULL DEFAULT '',
                ADD COLUMN postal_code VARCHAR(20) NOT NULL DEFAULT '',
                ADD COLUMN city VARCHAR(255) NOT NULL DEFAULT '',
                ADD COLUMN country CHAR(2) NOT NULL DEFAULT '',
                ADD COLUMN search_key VARCHAR(511) NOT NULL DEFAULT ''
        SQL);
        $this->addSql('ALTER TABLE hotel ALTER COLUMN street_address DROP DEFAULT');
        $this->addSql('ALTER TABLE hotel ALTER COLUMN postal_code DROP DEFAULT');
        $this->addSql('ALTER TABLE hotel ALTER COLUMN city DROP DEFAULT');
        $this->addSql('ALTER TABLE hotel ALTER COLUMN country DROP DEFAULT');
        $this->addSql('ALTER TABLE hotel ALTER COLUMN search_key DROP DEFAULT');
        $this->addSql('CREATE UNIQUE INDEX uniq_hotel_search_key ON hotel (search_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_hotel_search_key');
        $this->addSql(<<<'SQL'
            ALTER TABLE hotel
                DROP COLUMN street_address,
                DROP COLUMN postal_code,
                DROP COLUMN city,
                DROP COLUMN country,
                DROP COLUMN search_key
        SQL);
    }
}
```

- [ ] **Step 2: Run the migration**

```bash
make migrate
```

Expected: migration runs without errors.

- [ ] **Step 3: Commit**

```bash
git add migrations/Version20260515000000.php
git commit -m "feat(hotel): migration — add address columns and search_key unique constraint"
```

---

## Task 8: Update UI layer (Request, Controller, Serializer)

**Files:**
- Modify: `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelRequest.php`
- Modify: `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelController.php`
- Modify: `src/Hotel/UI/Http/Controller/RegisterHotel/RegisteredHotelSerializer.php`

- [ ] **Step 1: Add address fields to the HTTP request DTO**

Replace the full content of `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\RegisterHotel;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterHotelRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        #[OA\Property(type: 'string', example: 'Hotel Ibis Paris', maxLength: 255, minLength: 2)]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        #[OA\Property(type: 'string', example: '15 rue de Rivoli', maxLength: 255, minLength: 2)]
        public string $streetAddress,

        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 20)]
        #[OA\Property(type: 'string', example: '75001', maxLength: 20, minLength: 1)]
        public string $postalCode,

        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 255)]
        #[OA\Property(type: 'string', example: 'Paris', maxLength: 255, minLength: 1)]
        public string $city,

        #[Assert\NotBlank]
        #[Assert\Length(exactly: 2)]
        #[Assert\Country]
        #[OA\Property(type: 'string', example: 'FR', minLength: 2, maxLength: 2)]
        public string $country,
    ) {
    }
}
```

- [ ] **Step 2: Update the controller to pass address and handle 409**

Replace the full content of `src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\RegisterHotel;

use App\Hotel\Application\Service\RegisterHotelCommandFactory;
use App\Hotel\Application\UseCase\GetHotel\GetHotelQuery;
use App\Hotel\Domain\Exception\HotelAlreadyExistsException;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RegisterHotelController
{
    public function __construct(
        private RegisterHotelCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private RegisteredHotelSerializer $registeredHotelSerializer,
    ) {
    }

    #[Route('/api/hotels', name: 'hotel_register_hotel', methods: ['POST'])]
    #[OA\Post(
        summary: 'Register a new hotel',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: RegisterHotelRequest::class)),
        ),
        tags: ['Hotels'],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Hotel registered',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'name', type: 'string', example: 'Hotel Ibis Paris'),
                        new OA\Property(property: 'streetAddress', type: 'string', example: '15 rue de Rivoli'),
                        new OA\Property(property: 'postalCode', type: 'string', example: '75001'),
                        new OA\Property(property: 'city', type: 'string', example: 'Paris'),
                        new OA\Property(property: 'country', type: 'string', example: 'FR'),
                        new OA\Property(property: 'createdAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Hotel already exists'),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error'),
        ],
    )]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json')]
        RegisterHotelRequest $request,
    ): Response {
        try {
            $command = $this->commandFactory->create(
                $request->name,
                $request->streetAddress,
                $request->postalCode,
                $request->city,
                $request->country,
            );
            $this->commandBus->execute($command);
        } catch (HotelAlreadyExistsException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        $hotel = $this->queryBus->ask(new GetHotelQuery($command->id));
        if (null === $hotel) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(
            $this->registeredHotelSerializer->serialize($hotel),
            Response::HTTP_CREATED
        );
    }
}
```

- [ ] **Step 3: Update the serializer to include address**

Replace the full content of `src/Hotel/UI/Http/Controller/RegisterHotel/RegisteredHotelSerializer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Hotel\UI\Http\Controller\RegisterHotel;

use App\Hotel\Domain\Model\Hotel;

final class RegisteredHotelSerializer
{
    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     streetAddress: string,
     *     postalCode: string,
     *     city: string,
     *     country: string,
     *     createdAt: int
     * }
     */
    public function serialize(Hotel $hotel): array
    {
        return [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'streetAddress' => $hotel->address->streetAddress,
            'postalCode' => $hotel->address->postalCode,
            'city' => $hotel->address->city,
            'country' => $hotel->address->country,
            'createdAt' => $hotel->createdAt->getTimestamp(),
        ];
    }
}
```

- [ ] **Step 4: Run static analysis**

```bash
make static-code-analysis
```

Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelRequest.php \
        src/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelController.php \
        src/Hotel/UI/Http/Controller/RegisterHotel/RegisteredHotelSerializer.php
git commit -m "feat(hotel): update UI layer with address fields and 409 conflict handling"
```

---

## Task 9: Update controller tests

**Files:**
- Modify: `tests/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelControllerTest.php`

- [ ] **Step 1: Write the failing tests first, then run them**

Replace the full content of `tests/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Hotel\UI\Http\Controller\RegisterHotel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class RegisterHotelControllerTest extends WebTestCase
{
    private const array VALID_PAYLOAD = [
        'name' => 'Hotel Ibis Paris',
        'streetAddress' => '15 rue de Rivoli',
        'postalCode' => '75001',
        'city' => 'Paris',
        'country' => 'FR',
    ];

    #[Test]
    public function itRegistersAHotelAndReturns201(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{id: string, name: string, streetAddress: string, postalCode: string, city: string, country: string, createdAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $body);
        self::assertSame('Hotel Ibis Paris', $body['name']);
        self::assertSame('15 rue de Rivoli', $body['streetAddress']);
        self::assertSame('75001', $body['postalCode']);
        self::assertSame('Paris', $body['city']);
        self::assertSame('FR', $body['country']);
        self::assertArrayHasKey('createdAt', $body);
    }

    #[Test]
    public function itReturns409WhenHotelAlreadyExists(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenNameIsMissing(): void
    {
        $client = static::createClient();

        $payload = self::VALID_PAYLOAD;
        unset($payload['name']);

        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenCountryIsInvalid(): void
    {
        $client = static::createClient();

        $payload = array_merge(self::VALID_PAYLOAD, ['country' => 'FRANCE']);

        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenNameIsTooShort(): void
    {
        $client = static::createClient();

        $payload = array_merge(self::VALID_PAYLOAD, ['name' => 'A']);

        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }
}
```

- [ ] **Step 2: Run all tests**

```bash
make unit-test-quiet
```

Expected: all tests pass (functional tests hit the real DB which is wrapped in a rolled-back transaction by DAMA DoctrineTestBundle).

- [ ] **Step 3: Run lint**

```bash
make lint
```

Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add tests/Hotel/UI/Http/Controller/RegisterHotel/RegisterHotelControllerTest.php
git commit -m "test(hotel): update controller tests with address fields and 409 coverage"
```

---

## Self-Review

### Spec coverage

| Requirement | Task |
|-------------|------|
| Address VO (street, postal, city, country ISO) | Task 1 |
| HotelAlreadyExistsException | Task 2 |
| Hotel model carries Address | Task 3 |
| Repository port: existsByNameAndAddress | Task 3 |
| Command + Handler + Factory updated | Task 4 |
| InMemory repo implements existsByNameAndAddress | Task 5 |
| Handler tests: persist, duplicate, same name different city | Task 5 |
| Doctrine repo: Slugger + search_key | Task 6 |
| DB migration: address columns + UNIQUE on search_key | Task 7 |
| UI: address in request with validation | Task 8 |
| UI: 409 on duplicate | Task 8 |
| Serializer includes address | Task 8 |
| Controller tests updated + 409 test | Task 9 |

### Placeholder scan

None found. All steps contain complete code.

### Type consistency

- `Address` is used consistently across Domain, Application, Infrastructure, and Tests.
- `RegisterHotelCommandFactory::create()` signature matches the controller call in Task 8.
- `HotelRepository::buildSearchKey()` is `private` — consistent across `add()` and `existsByNameAndAddress()`.
- `RegisteredHotelSerializer::serialize()` return type includes all 7 fields added in Task 8.

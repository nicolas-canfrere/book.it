# Booker Registration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the Booker bounded context with a single use case — Booker Registration (self-registration of a natural person who will make reservations).

**Architecture:** Hexagonal architecture mirroring the Hotel and Room contexts (`src/Booker/`). A Booker is uniquely identified by email. Age validation (18+ years) is enforced in the command handler using the registration timestamp and the date of birth. Exception mappings are added to the existing `config/services/exceptions.yaml`.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL (raw SQL, named connection `bookit`), Symfony Messenger (command/query buses), NelmioApiDocBundle, PHPUnit.

---

## File Map

**Create (src):**
- `src/Booker/Domain/Model/Booker.php`
- `src/Booker/Domain/Exception/BookerAlreadyExistsException.php`
- `src/Booker/Domain/Exception/BookerUnderageException.php`
- `src/Booker/Domain/Port/BookerRepositoryInterface.php`
- `src/Booker/Application/Service/BookerIdGeneratorInterface.php`
- `src/Booker/Application/Service/RegisterBookerCommandFactory.php`
- `src/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommand.php`
- `src/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommandHandler.php`
- `src/Booker/Application/UseCase/GetBooker/GetBookerQuery.php`
- `src/Booker/Application/UseCase/GetBooker/GetBookerQueryHandler.php`
- `src/Booker/Infrastructure/Persistence/Doctrine/BookerRepository.php`
- `src/Booker/Infrastructure/Service/BookerIdGenerator.php`
- `src/Booker/UI/Http/Controller/BookerSerializer.php`
- `src/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerRequest.php`
- `src/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerController.php`

**Create (tests):**
- `tests/Booker/Infrastructure/Persistence/InMemory/InMemoryBookerRepository.php`
- `tests/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommandHandlerTest.php`
- `tests/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerControllerTest.php`

**Create (config + migration):**
- `config/services/booker.yaml`
- `migrations/Version<timestamp>.php` — generated via `make generate-migration`

**Modify:**
- `config/services.yaml` — add `booker.yaml` import (before `exceptions.yaml`)
- `config/services/exceptions.yaml` — add `BookerAlreadyExistsException` and `BookerUnderageException` mappings

---

## Task 1: Create feature branch

- [ ] **Step 1: Create and switch to the feature branch**

```bash
git checkout -b feat/booker-registration
```

- [ ] **Step 2: Verify**

```bash
git branch --show-current
```

Expected output: `feat/booker-registration`

---

## Task 2: Domain model and exceptions

**Files:**
- Create: `src/Booker/Domain/Model/Booker.php`
- Create: `src/Booker/Domain/Exception/BookerAlreadyExistsException.php`
- Create: `src/Booker/Domain/Exception/BookerUnderageException.php`

No isolated unit tests for these — they are exercised by the command handler integration test in Task 4.

- [ ] **Step 1: Create `src/Booker/Domain/Model/Booker.php`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Domain\Model;

final readonly class Booker
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public \DateTimeImmutable $dateOfBirth,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
```

- [ ] **Step 2: Create `src/Booker/Domain/Exception/BookerAlreadyExistsException.php`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Domain\Exception;

final class BookerAlreadyExistsException extends \DomainException
{
    public function __construct(string $email)
    {
        parent::__construct(\sprintf('A booker with email "%s" already exists.', $email));
    }
}
```

- [ ] **Step 3: Create `src/Booker/Domain/Exception/BookerUnderageException.php`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Domain\Exception;

final class BookerUnderageException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Booker must be at least 18 years old.');
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add src/Booker/Domain/
git commit -m "feat(booker): add Booker domain model and exceptions"
```

---

## Task 3: Repository port and ID generator

**Files:**
- Create: `src/Booker/Domain/Port/BookerRepositoryInterface.php`
- Create: `src/Booker/Application/Service/BookerIdGeneratorInterface.php`
- Create: `src/Booker/Infrastructure/Service/BookerIdGenerator.php`

- [ ] **Step 1: Create `src/Booker/Domain/Port/BookerRepositoryInterface.php`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Domain\Port;

use App\Booker\Domain\Model\Booker;

interface BookerRepositoryInterface
{
    public function add(Booker $booker): void;

    public function get(string $id): ?Booker;

    public function existsByEmail(string $email): bool;
}
```

- [ ] **Step 2: Create `src/Booker/Application/Service/BookerIdGeneratorInterface.php`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Application\Service;

interface BookerIdGeneratorInterface
{
    public function generate(): string;
}
```

- [ ] **Step 3: Create `src/Booker/Infrastructure/Service/BookerIdGenerator.php`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Infrastructure\Service;

use App\Booker\Application\Service\BookerIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class BookerIdGenerator implements BookerIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add src/Booker/Domain/Port/ src/Booker/Application/Service/ src/Booker/Infrastructure/Service/
git commit -m "feat(booker): add repository port and ID generator"
```

---

## Task 4: Command, query, and command factory

**Files:**
- Create: `src/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommand.php`
- Create: `src/Booker/Application/UseCase/GetBooker/GetBookerQuery.php`
- Create: `src/Booker/Application/Service/RegisterBookerCommandFactory.php`

- [ ] **Step 1: Create `src/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommand.php`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Application\UseCase\RegisterBooker;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class RegisterBookerCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public \DateTimeImmutable $dateOfBirth,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
```

- [ ] **Step 2: Create `src/Booker/Application/UseCase/GetBooker/GetBookerQuery.php`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Application\UseCase\GetBooker;

use App\Shared\Application\Bus\SyncQueryInterface;

final readonly class GetBookerQuery implements SyncQueryInterface
{
    public function __construct(public string $id)
    {
    }
}
```

- [ ] **Step 3: Create `src/Booker/Application/Service/RegisterBookerCommandFactory.php`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Application\Service;

use App\Booker\Application\UseCase\RegisterBooker\RegisterBookerCommand;
use Psr\Clock\ClockInterface;

final readonly class RegisterBookerCommandFactory
{
    public function __construct(
        private BookerIdGeneratorInterface $bookerIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(
        ?string $firstName,
        ?string $lastName,
        ?string $email,
        ?string $phone,
        ?string $dateOfBirth,
    ): RegisterBookerCommand {
        if (null === $firstName || null === $lastName || null === $email || null === $phone || null === $dateOfBirth) {
            throw new \InvalidArgumentException('All booker fields are required.');
        }

        return new RegisterBookerCommand(
            $this->bookerIdGenerator->generate(),
            $firstName,
            $lastName,
            $email,
            $phone,
            new \DateTimeImmutable($dateOfBirth),
            $this->clock->now(),
        );
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add src/Booker/Application/
git commit -m "feat(booker): add RegisterBooker command, GetBooker query, and command factory"
```

---

## Task 5: Command handler, query handler, and in-memory repository

**Files:**
- Create: `src/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommandHandler.php`
- Create: `src/Booker/Application/UseCase/GetBooker/GetBookerQueryHandler.php`
- Create: `tests/Booker/Infrastructure/Persistence/InMemory/InMemoryBookerRepository.php`

- [ ] **Step 1: Write the failing integration test**

Create `tests/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommandHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Booker\Application\UseCase\RegisterBooker;

use App\Booker\Application\Service\RegisterBookerCommandFactory;
use App\Booker\Application\UseCase\RegisterBooker\RegisterBookerCommandHandler;
use App\Booker\Domain\Exception\BookerAlreadyExistsException;
use App\Booker\Domain\Exception\BookerUnderageException;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Tests\Booker\Infrastructure\Persistence\InMemory\InMemoryBookerRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class RegisterBookerCommandHandlerTest extends KernelTestCase
{
    private InMemoryBookerRepository $bookerRepository;
    private RegisterBookerCommandHandler $handler;
    private RegisterBookerCommandFactory $commandFactory;

    protected function setUp(): void
    {
        $this->bookerRepository = new InMemoryBookerRepository();
        static::getContainer()->set(BookerRepositoryInterface::class, $this->bookerRepository);
        $this->handler = static::getContainer()->get(RegisterBookerCommandHandler::class);
        $this->commandFactory = static::getContainer()->get(RegisterBookerCommandFactory::class);
    }

    #[Test]
    public function itPersistsTheBooker(): void
    {
        $command = $this->commandFactory->create(
            firstName: 'Jean',
            lastName: 'Dupont',
            email: 'jean.dupont@example.com',
            phone: '+33612345678',
            dateOfBirth: '1990-05-15',
        );

        ($this->handler)($command);

        $booker = $this->bookerRepository->get($command->id);

        self::assertNotNull($booker);
        self::assertSame($command->id, $booker->id);
        self::assertSame('Jean', $booker->firstName);
        self::assertSame('Dupont', $booker->lastName);
        self::assertSame('jean.dupont@example.com', $booker->email);
        self::assertSame('+33612345678', $booker->phone);
        self::assertSame('1990-05-15', $booker->dateOfBirth->format('Y-m-d'));
        self::assertEquals($command->registeredAt, $booker->registeredAt);
    }

    #[Test]
    public function itThrowsWhenEmailAlreadyExists(): void
    {
        $command = $this->commandFactory->create(
            firstName: 'Jean',
            lastName: 'Dupont',
            email: 'jean@example.com',
            phone: '+33612345678',
            dateOfBirth: '1990-05-15',
        );
        ($this->handler)($command);

        $this->expectException(BookerAlreadyExistsException::class);

        $duplicate = $this->commandFactory->create(
            firstName: 'Marie',
            lastName: 'Martin',
            email: 'jean@example.com',
            phone: '+33698765432',
            dateOfBirth: '1985-03-20',
        );
        ($this->handler)($duplicate);
    }

    #[Test]
    public function itThrowsWhenBookerIsUnderage(): void
    {
        $this->expectException(BookerUnderageException::class);

        $underageDate = (new \DateTimeImmutable())->modify('-17 years +1 day')->format('Y-m-d');

        $command = $this->commandFactory->create(
            firstName: 'Young',
            lastName: 'Person',
            email: 'young@example.com',
            phone: '+33612345678',
            dateOfBirth: $underageDate,
        );
        ($this->handler)($command);
    }

    #[Test]
    public function itAcceptsBookerWhoTurns18Today(): void
    {
        $exactlyEighteenDate = (new \DateTimeImmutable())->modify('-18 years')->format('Y-m-d');

        $command = $this->commandFactory->create(
            firstName: 'Just',
            lastName: 'Adult',
            email: 'adult@example.com',
            phone: '+33612345678',
            dateOfBirth: $exactlyEighteenDate,
        );

        ($this->handler)($command);

        self::assertNotNull($this->bookerRepository->get($command->id));
    }
}
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `make unit-test-quiet ARGS="--filter RegisterBookerCommandHandlerTest"`

Expected: FAIL — classes do not exist yet.

- [ ] **Step 3: Create `tests/Booker/Infrastructure/Persistence/InMemory/InMemoryBookerRepository.php`**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Booker\Infrastructure\Persistence\InMemory;

use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;

final class InMemoryBookerRepository implements BookerRepositoryInterface
{
    /** @var array<string, Booker> */
    private array $bookers = [];

    public function add(Booker $booker): void
    {
        $this->bookers[$booker->id] = $booker;
    }

    public function get(string $id): ?Booker
    {
        return $this->bookers[$id] ?? null;
    }

    public function existsByEmail(string $email): bool
    {
        foreach ($this->bookers as $booker) {
            if (strtolower($booker->email) === strtolower($email)) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Create `src/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommandHandler.php`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Application\UseCase\RegisterBooker;

use App\Booker\Domain\Exception\BookerAlreadyExistsException;
use App\Booker\Domain\Exception\BookerUnderageException;
use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class RegisterBookerCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private BookerRepositoryInterface $bookerRepository,
    ) {
    }

    public function __invoke(RegisterBookerCommand $command): void
    {
        $age = $command->registeredAt->diff($command->dateOfBirth)->y;

        if ($age < 18) {
            throw new BookerUnderageException();
        }

        if ($this->bookerRepository->existsByEmail($command->email)) {
            throw new BookerAlreadyExistsException($command->email);
        }

        $booker = new Booker(
            $command->id,
            $command->firstName,
            $command->lastName,
            $command->email,
            $command->phone,
            $command->dateOfBirth,
            $command->registeredAt,
        );

        $this->bookerRepository->add($booker);
    }
}
```

- [ ] **Step 5: Create `src/Booker/Application/UseCase/GetBooker/GetBookerQueryHandler.php`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Application\UseCase\GetBooker;

use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetBookerQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private BookerRepositoryInterface $bookerRepository,
    ) {
    }

    public function __invoke(GetBookerQuery $query): ?Booker
    {
        return $this->bookerRepository->get($query->id);
    }
}
```

- [ ] **Step 6: Wire the service config so the Symfony container knows about these classes**

Create `config/services/booker.yaml`:

```yaml
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

    App\Booker\Domain\:
        resource: '../../src/Booker/Domain/'
        exclude:
            - '../../src/Booker/Domain/Model/'

    App\Booker\Application\:
        resource: '../../src/Booker/Application/'
        exclude:
            - '../../src/Booker/Application/**/*Exception.php'
            - '../../src/Booker/Application/**/*Command.php'
            - '../../src/Booker/Application/**/*Query.php'

    App\Booker\Infrastructure\:
        resource: '../../src/Booker/Infrastructure/'
        exclude:
            - '../../src/Booker/Infrastructure/**/*Exception.php'

    App\Booker\UI\:
        resource: '../../src/Booker/UI/'
        exclude:
            - '../../src/Booker/UI/**/*Request.php'
```

Add the import to `config/services.yaml` (before `exceptions.yaml`):

```yaml
imports:
    - { resource: './services/shared.yaml' }
    - { resource: './services/hotel.yaml' }
    - { resource: './services/room.yaml' }
    - { resource: './services/booker.yaml' }
    - { resource: './services/exceptions.yaml' }
```

- [ ] **Step 7: Run the test to confirm it passes**

Run: `make unit-test-quiet ARGS="--filter RegisterBookerCommandHandlerTest"`

Expected: 4 tests, 4 assertions, PASS.

- [ ] **Step 8: Run full unit/integration suite to check for regressions**

Run: `make unit-test-quiet`

Expected: all existing tests still pass.

- [ ] **Step 9: Commit**

```bash
git add src/Booker/Application/UseCase/ tests/Booker/ config/services/booker.yaml config/services.yaml
git commit -m "feat(booker): add RegisterBooker command handler, GetBooker query handler, and integration test"
```

---

## Task 6: Doctrine migration and repository

**Files:**
- Create: `migrations/Version<timestamp>.php` — generated via `make generate-migration`
- Create: `src/Booker/Infrastructure/Persistence/Doctrine/BookerRepository.php`

- [ ] **Step 1: Generate the migration file**

Run: `make generate-migration`

This creates a file named `migrations/Version<Year><Month><Day><Hour><Minutes><Seconds>.php` with the correct timestamp. Open the generated file and replace its `up()` and `down()` body with:

```php
    public function getDescription(): string
    {
        return 'Create booker table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE booker (
                id UUID NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(50) NOT NULL,
                date_of_birth DATE NOT NULL,
                registered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql("COMMENT ON COLUMN booker.registered_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX uniq_booker_email ON booker (LOWER(email))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE booker');
    }
```

- [ ] **Step 2: Create `src/Booker/Infrastructure/Persistence/Doctrine/BookerRepository.php`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Infrastructure\Persistence\Doctrine;

use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use Doctrine\DBAL\Connection;

final readonly class BookerRepository implements BookerRepositoryInterface
{
    public function __construct(
        private Connection $bookit,
    ) {
    }

    public function add(Booker $booker): void
    {
        $this->bookit->insert('booker', [
            'id' => $booker->id,
            'first_name' => $booker->firstName,
            'last_name' => $booker->lastName,
            'email' => $booker->email,
            'phone' => $booker->phone,
            'date_of_birth' => $booker->dateOfBirth->format('Y-m-d'),
            'registered_at' => $booker->registeredAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $id): ?Booker
    {
        /** @var array{id: string, first_name: string, last_name: string, email: string, phone: string, date_of_birth: string, registered_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, first_name, last_name, email, phone, date_of_birth, registered_at FROM booker WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return new Booker(
            $row['id'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['phone'],
            new \DateTimeImmutable($row['date_of_birth']),
            new \DateTimeImmutable($row['registered_at']),
        );
    }

    public function existsByEmail(string $email): bool
    {
        /** @var int|string $count */
        $count = $this->bookit->fetchOne(
            'SELECT COUNT(*) FROM booker WHERE LOWER(email) = LOWER(:email)',
            ['email' => $email],
        );

        return (int) $count > 0;
    }
}
```

- [ ] **Step 3: Run the migration**

Run: `make migrate`

Expected: the generated migration applied successfully.

- [ ] **Step 4: Commit**

```bash
git add migrations/ src/Booker/Infrastructure/Persistence/Doctrine/BookerRepository.php
git commit -m "feat(booker): add booker table migration and Doctrine repository"
```

---

## Task 7: Exception mappings

**Files:**
- Modify: `config/services/exceptions.yaml`

- [ ] **Step 1: Add the Booker exception mappings to `config/services/exceptions.yaml`**

Add these two entries to the `$map` block:

```yaml
                App\Booker\Domain\Exception\BookerAlreadyExistsException:
                    type: 'https://book.it/problems/booker-already-exists'
                    title: 'Booker Already Exists'
                    status: 409
                App\Booker\Domain\Exception\BookerUnderageException:
                    type: 'https://book.it/problems/booker-underage'
                    title: 'Booker Underage'
                    status: 422
```

The full file should look like:

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
                App\Room\Domain\Exception\RoomBatchInvalidException:
                    type: 'https://book.it/problems/room-batch-invalid'
                    title: 'Room Batch Import Failed'
                    status: 422
                App\Room\Application\Exception\InvalidCsvFormatException:
                    type: 'about:blank'
                    title: 'Unprocessable Content'
                    status: 422
                App\Booker\Domain\Exception\BookerAlreadyExistsException:
                    type: 'https://book.it/problems/booker-already-exists'
                    title: 'Booker Already Exists'
                    status: 409
                App\Booker\Domain\Exception\BookerUnderageException:
                    type: 'https://book.it/problems/booker-underage'
                    title: 'Booker Underage'
                    status: 422
```

- [ ] **Step 2: Commit**

```bash
git add config/services/exceptions.yaml
git commit -m "feat(booker): map BookerAlreadyExistsException and BookerUnderageException to HTTP responses"
```

---

## Task 8: HTTP layer — request DTO, serializer, and controller

**Files:**
- Create: `src/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerRequest.php`
- Create: `src/Booker/UI/Http/Controller/BookerSerializer.php`
- Create: `src/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerController.php`

- [ ] **Step 1: Write the failing functional test**

Create `tests/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Booker\UI\Http\Controller\RegisterBooker;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class RegisterBookerControllerTest extends WebTestCase
{
    private const array VALID_PAYLOAD = [
        'firstName' => 'Jean',
        'lastName' => 'Dupont',
        'email' => 'jean.dupont@example.com',
        'phone' => '+33612345678',
        'dateOfBirth' => '1990-05-15',
    ];

    #[Test]
    public function itRegistersABookerAndReturns201(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{id: string, firstName: string, lastName: string, email: string, phone: string, dateOfBirth: string, registeredAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['id']);
        self::assertSame('Jean', $body['firstName']);
        self::assertSame('Dupont', $body['lastName']);
        self::assertSame('jean.dupont@example.com', $body['email']);
        self::assertSame('+33612345678', $body['phone']);
        self::assertSame('1990-05-15', $body['dateOfBirth']);
        self::assertGreaterThan(0, $body['registeredAt']);
    }

    #[Test]
    public function itReturns409WhenEmailAlreadyExists(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'POST',
            uri: '/api/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int, detail: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/booker-already-exists', $body['type']);
        self::assertSame('Booker Already Exists', $body['title']);
        self::assertSame(Response::HTTP_CONFLICT, $body['status']);
    }

    #[Test]
    public function itReturns422WhenBookerIsUnderage(): void
    {
        $client = static::createClient();

        $underageDate = (new \DateTimeImmutable())->modify('-17 years +1 day')->format('Y-m-d');
        $payload = array_merge(self::VALID_PAYLOAD, ['dateOfBirth' => $underageDate, 'email' => 'underage@example.com']);

        $client->request(
            method: 'POST',
            uri: '/api/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/booker-underage', $body['type']);
        self::assertSame('Booker Underage', $body['title']);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $body['status']);
    }

    #[Test]
    public function itReturns422AsAProblemDetailWithViolationsWhenFieldIsMissing(): void
    {
        $client = static::createClient();

        $payload = self::VALID_PAYLOAD;
        unset($payload['email']);

        $client->request(
            method: 'POST',
            uri: '/api/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{violations: list<array{field: string, message: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('email', $fields);
    }

    #[Test]
    public function itReturns422WhenEmailIsInvalid(): void
    {
        $client = static::createClient();

        $payload = array_merge(self::VALID_PAYLOAD, ['email' => 'not-an-email']);

        $client->request(
            method: 'POST',
            uri: '/api/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenDateOfBirthIsInvalidFormat(): void
    {
        $client = static::createClient();

        $payload = array_merge(self::VALID_PAYLOAD, ['dateOfBirth' => 'not-a-date', 'email' => 'other@example.com']);

        $client->request(
            method: 'POST',
            uri: '/api/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }
}
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `make functional-test ARGS="--filter RegisterBookerControllerTest"`

Expected: FAIL — route does not exist yet (404 or error).

- [ ] **Step 3: Create `src/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerRequest.php`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\UI\Http\Controller\RegisterBooker;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterBookerRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        #[OA\Property(type: 'string', example: 'Jean', maxLength: 100, minLength: 1)]
        public ?string $firstName = null,

        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        #[OA\Property(type: 'string', example: 'Dupont', maxLength: 100, minLength: 1)]
        public ?string $lastName = null,

        #[Assert\NotBlank]
        #[Assert\Email]
        #[OA\Property(type: 'string', format: 'email', example: 'jean.dupont@example.com')]
        public ?string $email = null,

        #[Assert\NotBlank]
        #[Assert\Length(min: 5, max: 50)]
        #[OA\Property(type: 'string', example: '+33612345678', maxLength: 50, minLength: 5)]
        public ?string $phone = null,

        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(type: 'string', format: 'date', example: '1990-05-15')]
        public ?string $dateOfBirth = null,
    ) {
    }
}
```

- [ ] **Step 4: Create `src/Booker/UI/Http/Controller/BookerSerializer.php`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\UI\Http\Controller;

use App\Booker\Domain\Model\Booker;

final class BookerSerializer
{
    /**
     * @return array{id: string, firstName: string, lastName: string, email: string, phone: string, dateOfBirth: string, registeredAt: int}
     */
    public function serialize(Booker $booker): array
    {
        return [
            'id' => $booker->id,
            'firstName' => $booker->firstName,
            'lastName' => $booker->lastName,
            'email' => $booker->email,
            'phone' => $booker->phone,
            'dateOfBirth' => $booker->dateOfBirth->format('Y-m-d'),
            'registeredAt' => $booker->registeredAt->getTimestamp(),
        ];
    }
}
```

- [ ] **Step 5: Create `src/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\UI\Http\Controller\RegisterBooker;

use App\Booker\Application\Service\RegisterBookerCommandFactory;
use App\Booker\Application\UseCase\GetBooker\GetBookerQuery;
use App\Booker\UI\Http\Controller\BookerSerializer;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RegisterBookerController
{
    public function __construct(
        private RegisterBookerCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private BookerSerializer $bookerSerializer,
    ) {
    }

    #[Route('/api/bookers', name: 'booker_register_booker', methods: ['POST'])]
    #[OA\Post(
        summary: 'Register a new booker',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: RegisterBookerRequest::class)),
        ),
        tags: ['Bookers'],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Booker registered',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'firstName', type: 'string', example: 'Jean'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Dupont'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jean.dupont@example.com'),
                        new OA\Property(property: 'phone', type: 'string', example: '+33612345678'),
                        new OA\Property(property: 'dateOfBirth', type: 'string', format: 'date', example: '1990-05-15'),
                        new OA\Property(property: 'registeredAt', description: 'Unix timestamp', type: 'integer'),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_CONFLICT,
                description: 'Email already taken',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
                ),
            ),
            new OA\Response(
                response: Response::HTTP_UNPROCESSABLE_ENTITY,
                description: 'Validation error or underage applicant',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'),
                ),
            ),
        ],
    )]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json')]
        RegisterBookerRequest $request,
    ): Response {
        $command = $this->commandFactory->create(
            $request->firstName,
            $request->lastName,
            $request->email,
            $request->phone,
            $request->dateOfBirth,
        );
        $this->commandBus->execute($command);

        $booker = $this->queryBus->ask(new GetBookerQuery($command->id));
        if (null === $booker) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse(
            $this->bookerSerializer->serialize($booker),
            Response::HTTP_CREATED,
        );
    }
}
```

- [ ] **Step 6: Run the functional tests**

Run: `make functional-test ARGS="--filter RegisterBookerControllerTest"`

Expected: 6 tests, all PASS.

- [ ] **Step 7: Run the full test suite to check for regressions**

Run: `make test`

Expected: all tests pass.

- [ ] **Step 8: Regenerate the OpenAPI spec**

Run: `make openapi`

Expected: `openapi.yaml` updated, no warnings about missing schemas.

- [ ] **Step 9: Commit**

```bash
git add src/Booker/UI/ tests/Booker/UI/
git commit -m "feat(booker): add RegisterBooker HTTP controller, request DTO, and serializer"
git add openapi.yaml
git commit -m "docs(openapi): regenerate spec with Booker Registration endpoint"
```

---

## Task 9: Static analysis and lint

- [ ] **Step 1: Run PHPStan and CS Fixer**

Run: `make lint`

Expected: no errors, no CS violations.

Fix any issues found before proceeding.

- [ ] **Step 2: Commit any lint fixes**

```bash
git add -p
git commit -m "style(booker): apply CS Fixer rules"
```

(Skip this step if `make lint` reported no issues.)

# Operator Registration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a new `Operator` bounded context with a `POST /operators` endpoint that allows an admin to register an operator account (stored in PostgreSQL + Keycloak).

**Architecture:** Mirror of the `Booker` context — same four-layer structure (Domain / Application / Infrastructure / UI), same Keycloak integration via `ExternalAccountRegistrarInterface` (domain port) → `SecurityAccountRegistrarAdapter` (infra adapter) → `AccountRegistrarInterface` (Security contract). An `operator` DBAL connection with its own `SearchPathMiddleware` isolates the schema. The endpoint is unprotected in this iteration.

**Tech Stack:** PHP 8.4, Symfony 8.0, PostgreSQL 16 (schema `operator`), Keycloak via Security context, Doctrine DBAL (no ORM), PHPUnit.

---

## File Map

**Create:**
- `src/Operator/Domain/Model/Operator.php`
- `src/Operator/Domain/Port/OperatorRepositoryInterface.php`
- `src/Operator/Domain/Port/OperatorIdGeneratorInterface.php`
- `src/Operator/Domain/Port/ExternalAccountRegistrarInterface.php`
- `src/Operator/Domain/Exception/OperatorAlreadyExistsException.php`
- `src/Operator/Domain/Exception/ExternalAccountCreationException.php`
- `src/Operator/Application/UseCase/RegisterOperator/RegisterOperatorCommand.php`
- `src/Operator/Application/UseCase/RegisterOperator/RegisterOperatorCommandHandler.php`
- `src/Operator/Application/Service/RegisterOperatorCommandFactory.php`
- `src/Operator/Infrastructure/Persistence/Doctrine/OperatorRepository.php`
- `src/Operator/Infrastructure/Service/OperatorIdGenerator.php`
- `src/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapter.php`
- `src/Operator/UI/Http/Controller/RegisterOperator/RegisterOperatorRequest.php`
- `src/Operator/UI/Http/Controller/RegisterOperator/RegisterOperatorController.php`
- `tests/Operator/Application/UseCase/RegisterOperator/RegisterOperatorCommandHandlerTest.php`
- `tests/Operator/Infrastructure/Persistence/InMemory/InMemoryOperatorRepository.php`
- `tests/Operator/Infrastructure/ExternalAccount/NullExternalAccountRegistrar.php`
- `tests/Operator/Infrastructure/ExternalAccount/ThrowingExternalAccountRegistrar.php`
- `tests/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapterTest.php`
- `tests/Operator/UI/Http/Controller/RegisterOperator/RegisterOperatorControllerTest.php`

**Modify:**
- `config/packages/doctrine.yaml` — add `operator` DBAL connection
- `config/services/exceptions.yaml` — map `OperatorAlreadyExistsException` and `ExternalAccountCreationException`
- `config/services.yaml` — import `operator.yaml`

**Create config:**
- `config/services/operator.yaml`

**Generate:**
- One new Doctrine migration (via `make generate-migration`)

---

## Task 1: Domain Layer

**Files:**
- Create: `src/Operator/Domain/Model/Operator.php`
- Create: `src/Operator/Domain/Port/OperatorRepositoryInterface.php`
- Create: `src/Operator/Domain/Port/OperatorIdGeneratorInterface.php`
- Create: `src/Operator/Domain/Port/ExternalAccountRegistrarInterface.php`
- Create: `src/Operator/Domain/Exception/OperatorAlreadyExistsException.php`
- Create: `src/Operator/Domain/Exception/ExternalAccountCreationException.php`

- [ ] **Step 1: Create the Operator aggregate**

```php
// src/Operator/Domain/Model/Operator.php
<?php

declare(strict_types=1);

namespace App\Operator\Domain\Model;

final readonly class Operator
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
```

- [ ] **Step 2: Create the repository port**

```php
// src/Operator/Domain/Port/OperatorRepositoryInterface.php
<?php

declare(strict_types=1);

namespace App\Operator\Domain\Port;

use App\Operator\Domain\Model\Operator;

interface OperatorRepositoryInterface
{
    public function add(Operator $operator): void;

    public function existsByEmail(string $email): bool;
}
```

- [ ] **Step 3: Create the ID generator port**

```php
// src/Operator/Domain/Port/OperatorIdGeneratorInterface.php
<?php

declare(strict_types=1);

namespace App\Operator\Domain\Port;

interface OperatorIdGeneratorInterface
{
    public function generate(): string;
}
```

- [ ] **Step 4: Create the external account registrar port**

```php
// src/Operator/Domain/Port/ExternalAccountRegistrarInterface.php
<?php

declare(strict_types=1);

namespace App\Operator\Domain\Port;

interface ExternalAccountRegistrarInterface
{
    public function register(string $operatorId, string $email, string $password): void;

    public function unregister(string $operatorId): void;
}
```

- [ ] **Step 5: Create domain exceptions**

```php
// src/Operator/Domain/Exception/OperatorAlreadyExistsException.php
<?php

declare(strict_types=1);

namespace App\Operator\Domain\Exception;

final class OperatorAlreadyExistsException extends \DomainException
{
    public function __construct(string $email)
    {
        parent::__construct(sprintf('An operator with email "%s" already exists.', $email));
    }
}
```

```php
// src/Operator/Domain/Exception/ExternalAccountCreationException.php
<?php

declare(strict_types=1);

namespace App\Operator\Domain\Exception;

final class ExternalAccountCreationException extends \RuntimeException
{
    public function __construct(string $email, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Failed to create external account for "%s"', $email), 0, $previous);
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add src/Operator/Domain/
git commit -m "feat(operator): add domain model, ports and exceptions"
```

---

## Task 2: Application Layer — Command and Factory

**Files:**
- Create: `src/Operator/Application/UseCase/RegisterOperator/RegisterOperatorCommand.php`
- Create: `src/Operator/Application/Service/RegisterOperatorCommandFactory.php`

- [ ] **Step 1: Create the command**

```php
// src/Operator/Application/UseCase/RegisterOperator/RegisterOperatorCommand.php
<?php

declare(strict_types=1);

namespace App\Operator\Application\UseCase\RegisterOperator;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class RegisterOperatorCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public string $password,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
```

- [ ] **Step 2: Create the command factory**

```php
// src/Operator/Application/Service/RegisterOperatorCommandFactory.php
<?php

declare(strict_types=1);

namespace App\Operator\Application\Service;

use App\Operator\Application\UseCase\RegisterOperator\RegisterOperatorCommand;
use App\Operator\Domain\Port\OperatorIdGeneratorInterface;
use Psr\Clock\ClockInterface;

final readonly class RegisterOperatorCommandFactory
{
    public function __construct(
        private OperatorIdGeneratorInterface $operatorIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $password,
    ): RegisterOperatorCommand {
        return new RegisterOperatorCommand(
            $this->operatorIdGenerator->generate(),
            $firstName,
            $lastName,
            $email,
            $phone,
            $password,
            $this->clock->now(),
        );
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Operator/Application/
git commit -m "feat(operator): add RegisterOperator command and factory"
```

---

## Task 3: Unit Test + Command Handler

**Files:**
- Create: `tests/Operator/Infrastructure/Persistence/InMemory/InMemoryOperatorRepository.php`
- Create: `tests/Operator/Infrastructure/ExternalAccount/NullExternalAccountRegistrar.php`
- Create: `tests/Operator/Infrastructure/ExternalAccount/ThrowingExternalAccountRegistrar.php`
- Create: `tests/Operator/Application/UseCase/RegisterOperator/RegisterOperatorCommandHandlerTest.php`
- Create: `src/Operator/Application/UseCase/RegisterOperator/RegisterOperatorCommandHandler.php`

- [ ] **Step 1: Create test doubles**

```php
// tests/Operator/Infrastructure/Persistence/InMemory/InMemoryOperatorRepository.php
<?php

declare(strict_types=1);

namespace App\Tests\Operator\Infrastructure\Persistence\InMemory;

use App\Operator\Domain\Model\Operator;
use App\Operator\Domain\Port\OperatorRepositoryInterface;

final class InMemoryOperatorRepository implements OperatorRepositoryInterface
{
    /** @var array<string, Operator> */
    private array $operators = [];

    public function add(Operator $operator): void
    {
        $this->operators[$operator->id] = $operator;
    }

    public function existsByEmail(string $email): bool
    {
        foreach ($this->operators as $operator) {
            if (strtolower($operator->email) === strtolower($email)) {
                return true;
            }
        }

        return false;
    }
}
```

```php
// tests/Operator/Infrastructure/ExternalAccount/NullExternalAccountRegistrar.php
<?php

declare(strict_types=1);

namespace App\Tests\Operator\Infrastructure\ExternalAccount;

use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;

final class NullExternalAccountRegistrar implements ExternalAccountRegistrarInterface
{
    public function register(string $operatorId, string $email, string $password): void
    {
    }

    public function unregister(string $operatorId): void
    {
    }
}
```

```php
// tests/Operator/Infrastructure/ExternalAccount/ThrowingExternalAccountRegistrar.php
<?php

declare(strict_types=1);

namespace App\Tests\Operator\Infrastructure\ExternalAccount;

use App\Operator\Domain\Exception\ExternalAccountCreationException;
use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;

final class ThrowingExternalAccountRegistrar implements ExternalAccountRegistrarInterface
{
    public function register(string $operatorId, string $email, string $password): void
    {
        throw new ExternalAccountCreationException($email, new \RuntimeException('Keycloak unavailable'));
    }

    public function unregister(string $operatorId): void
    {
    }
}
```

- [ ] **Step 2: Write the failing unit test**

```php
// tests/Operator/Application/UseCase/RegisterOperator/RegisterOperatorCommandHandlerTest.php
<?php

declare(strict_types=1);

namespace App\Tests\Operator\Application\UseCase\RegisterOperator;

use App\Operator\Application\UseCase\RegisterOperator\RegisterOperatorCommand;
use App\Operator\Application\UseCase\RegisterOperator\RegisterOperatorCommandHandler;
use App\Operator\Domain\Exception\OperatorAlreadyExistsException;
use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;
use App\Operator\Domain\Port\OperatorRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[Group('unit')]
final class RegisterOperatorCommandHandlerTest extends TestCase
{
    private OperatorRepositoryInterface&MockObject $repository;
    private ExternalAccountRegistrarInterface&MockObject $accountRegistrar;
    private RegisterOperatorCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(OperatorRepositoryInterface::class);
        $this->accountRegistrar = $this->createMock(ExternalAccountRegistrarInterface::class);
        $this->handler = new RegisterOperatorCommandHandler(
            $this->repository,
            $this->accountRegistrar,
            new NullLogger(),
        );
    }

    #[Test]
    public function it_throws_already_exists_before_calling_keycloak(): void
    {
        $this->repository->method('existsByEmail')->willReturn(true);
        $command = $this->makeCommand();
        $this->accountRegistrar->expects(self::never())->method('register');

        $this->expectException(OperatorAlreadyExistsException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function it_compensates_by_unregistering_when_db_save_fails(): void
    {
        $this->repository->method('existsByEmail')->willReturn(false);
        $this->repository->method('add')->willThrowException(new \RuntimeException('DB error'));
        $command = $this->makeCommand();

        $this->accountRegistrar->expects(self::once())->method('register');
        $this->accountRegistrar->expects(self::once())->method('unregister')->with('uuid-1');

        $this->expectException(\RuntimeException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function it_registers_external_account_then_saves_operator(): void
    {
        $this->repository->method('existsByEmail')->willReturn(false);
        $command = $this->makeCommand();

        $this->accountRegistrar->expects(self::once())
            ->method('register')
            ->with('uuid-1', 'alice@hotel.com', 'password123');
        $this->accountRegistrar->expects(self::never())->method('unregister');
        $this->repository->expects(self::once())->method('add');

        ($this->handler)($command);
    }

    private function makeCommand(
        string $email = 'alice@hotel.com',
        string $id = 'uuid-1',
    ): RegisterOperatorCommand {
        return new RegisterOperatorCommand(
            $id,
            'Alice',
            'Martin',
            $email,
            '+33612345678',
            'password123',
            new \DateTimeImmutable('2026-01-01'),
        );
    }
}
```

- [ ] **Step 3: Run the test — expect failure (class not found)**

```bash
docker compose run --rm unit-test vendor/bin/phpunit tests/Operator/Application/UseCase/RegisterOperator/RegisterOperatorCommandHandlerTest.php
```

Expected: error like `Class "App\Operator\Application\UseCase\RegisterOperator\RegisterOperatorCommandHandler" not found`

- [ ] **Step 4: Implement the command handler**

```php
// src/Operator/Application/UseCase/RegisterOperator/RegisterOperatorCommandHandler.php
<?php

declare(strict_types=1);

namespace App\Operator\Application\UseCase\RegisterOperator;

use App\Operator\Domain\Exception\OperatorAlreadyExistsException;
use App\Operator\Domain\Model\Operator;
use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;
use App\Operator\Domain\Port\OperatorRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class RegisterOperatorCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private OperatorRepositoryInterface $operatorRepository,
        private ExternalAccountRegistrarInterface $accountRegistrar,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RegisterOperatorCommand $command): void
    {
        if ($this->operatorRepository->existsByEmail($command->email)) {
            throw new OperatorAlreadyExistsException($command->email);
        }

        $this->accountRegistrar->register($command->id, $command->email, $command->password);

        try {
            $this->operatorRepository->add(new Operator(
                $command->id,
                $command->firstName,
                $command->lastName,
                $command->email,
                $command->phone,
                $command->registeredAt,
            ));
        } catch (\Throwable $e) {
            $this->logger->error('Operator persistence failed after account creation — compensating', [
                'operator_id' => $command->id,
                'email' => $command->email,
                'error' => $e->getMessage(),
            ]);
            $this->accountRegistrar->unregister($command->id);
            throw $e;
        }

        $this->logger->info('Operator registered', [
            'operator_id' => $command->id,
            'email' => $command->email,
        ]);
    }
}
```

- [ ] **Step 5: Run the test — expect green**

```bash
docker compose run --rm unit-test vendor/bin/phpunit tests/Operator/Application/UseCase/RegisterOperator/RegisterOperatorCommandHandlerTest.php
```

Expected: 3 tests, 3 assertions, OK

- [ ] **Step 6: Commit**

```bash
git add src/Operator/Application/UseCase/ tests/Operator/
git commit -m "feat(operator): add RegisterOperatorCommandHandler with unit tests"
```

---

## Task 4: Infrastructure — Persistence + ID Generator

**Files:**
- Create: `src/Operator/Infrastructure/Persistence/Doctrine/OperatorRepository.php`
- Create: `src/Operator/Infrastructure/Service/OperatorIdGenerator.php`
- Generate: new Doctrine migration

- [ ] **Step 1: Generate the migration**

```bash
make generate-migration
```

Open the newly created file in `migrations/` and replace its `up()` body with:

```php
public function up(Schema $schema): void
{
    $this->addSql('CREATE SCHEMA IF NOT EXISTS operator');
    $this->addSql('CREATE TABLE operator.operator (
        id           UUID         NOT NULL PRIMARY KEY,
        first_name   VARCHAR(100) NOT NULL,
        last_name    VARCHAR(100) NOT NULL,
        email        VARCHAR(255) NOT NULL UNIQUE,
        phone        VARCHAR(50)  NOT NULL,
        registered_at TIMESTAMP   NOT NULL
    )');
}

public function down(Schema $schema): void
{
    $this->addSql('DROP TABLE operator.operator');
    $this->addSql('DROP SCHEMA IF EXISTS operator');
}
```

- [ ] **Step 2: Run the migration**

```bash
make migrate
```

Expected: migration applied with no errors.

- [ ] **Step 3: Implement the repository**

```php
// src/Operator/Infrastructure/Persistence/Doctrine/OperatorRepository.php
<?php

declare(strict_types=1);

namespace App\Operator\Infrastructure\Persistence\Doctrine;

use App\Operator\Domain\Model\Operator;
use App\Operator\Domain\Port\OperatorRepositoryInterface;
use Doctrine\DBAL\Connection;

final readonly class OperatorRepository implements OperatorRepositoryInterface
{
    public function __construct(
        private Connection $operator,
    ) {
    }

    public function add(Operator $operator): void
    {
        $this->operator->insert('operator', [
            'id' => $operator->id,
            'first_name' => $operator->firstName,
            'last_name' => $operator->lastName,
            'email' => $operator->email,
            'phone' => $operator->phone,
            'registered_at' => $operator->registeredAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function existsByEmail(string $email): bool
    {
        /** @var int|string $count */
        $count = $this->operator->fetchOne(
            'SELECT COUNT(*) FROM operator WHERE LOWER(email) = LOWER(:email)',
            ['email' => $email],
        );

        return (int) $count > 0;
    }
}
```

- [ ] **Step 4: Implement the ID generator**

```php
// src/Operator/Infrastructure/Service/OperatorIdGenerator.php
<?php

declare(strict_types=1);

namespace App\Operator\Infrastructure\Service;

use App\Operator\Domain\Port\OperatorIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class OperatorIdGenerator implements OperatorIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add src/Operator/Infrastructure/Persistence/ src/Operator/Infrastructure/Service/ migrations/
git commit -m "feat(operator): add OperatorRepository, OperatorIdGenerator and migration"
```

---

## Task 5: Infrastructure — Security Account Registrar Adapter

**Files:**
- Create: `src/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapter.php`
- Create: `tests/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapterTest.php`

- [ ] **Step 1: Write the failing unit test**

```php
// tests/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapterTest.php
<?php

declare(strict_types=1);

namespace App\Tests\Operator\Infrastructure\Contract;

use App\Operator\Domain\Exception\ExternalAccountCreationException;
use App\Operator\Infrastructure\Contract\SecurityAccountRegistrarAdapter;
use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrationFailedException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SecurityAccountRegistrarAdapterTest extends TestCase
{
    private AccountRegistrarInterface&MockObject $accountRegistrar;
    private SecurityAccountRegistrarAdapter $adapter;

    protected function setUp(): void
    {
        $this->accountRegistrar = $this->createMock(AccountRegistrarInterface::class);
        $this->adapter = new SecurityAccountRegistrarAdapter($this->accountRegistrar);
    }

    #[Test]
    public function it_delegates_register_with_operator_context(): void
    {
        $this->accountRegistrar->expects(self::once())
            ->method('register')
            ->with('operator-id', 'operator', 'email@example.com', 'password');

        $this->adapter->register('operator-id', 'email@example.com', 'password');
    }

    #[Test]
    public function it_delegates_unregister_with_operator_context(): void
    {
        $this->accountRegistrar->expects(self::once())
            ->method('unregister')
            ->with('operator-id', 'operator');

        $this->adapter->unregister('operator-id');
    }

    #[Test]
    public function it_wraps_account_registration_failed_as_external_account_creation_exception(): void
    {
        $this->accountRegistrar->method('register')
            ->willThrowException(new AccountRegistrationFailedException('email@example.com'));

        $this->expectException(ExternalAccountCreationException::class);
        $this->adapter->register('operator-id', 'email@example.com', 'password');
    }
}
```

- [ ] **Step 2: Run the test — expect failure (class not found)**

```bash
docker compose run --rm unit-test vendor/bin/phpunit tests/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapterTest.php
```

Expected: error — `SecurityAccountRegistrarAdapter` not found.

- [ ] **Step 3: Implement the adapter**

```php
// src/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapter.php
<?php

declare(strict_types=1);

namespace App\Operator\Infrastructure\Contract;

use App\Operator\Domain\Exception\ExternalAccountCreationException;
use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrationFailedException;

final readonly class SecurityAccountRegistrarAdapter implements ExternalAccountRegistrarInterface
{
    public function __construct(
        private AccountRegistrarInterface $accountRegistrar,
    ) {
    }

    public function register(string $operatorId, string $email, string $password): void
    {
        try {
            $this->accountRegistrar->register($operatorId, 'operator', $email, $password);
        } catch (AccountRegistrationFailedException $e) {
            throw new ExternalAccountCreationException($email, $e);
        }
    }

    public function unregister(string $operatorId): void
    {
        $this->accountRegistrar->unregister($operatorId, 'operator');
    }
}
```

- [ ] **Step 4: Run the test — expect green**

```bash
docker compose run --rm unit-test vendor/bin/phpunit tests/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapterTest.php
```

Expected: 3 tests, 3 assertions, OK

- [ ] **Step 5: Commit**

```bash
git add src/Operator/Infrastructure/Contract/ tests/Operator/Infrastructure/Contract/
git commit -m "feat(operator): add SecurityAccountRegistrarAdapter with unit tests"
```

---

## Task 6: Service Configuration

**Files:**
- Create: `config/services/operator.yaml`
- Modify: `config/packages/doctrine.yaml`
- Modify: `config/services/exceptions.yaml`
- Modify: `config/services.yaml`

- [ ] **Step 1: Create operator.yaml**

```yaml
# config/services/operator.yaml
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

    App\Operator\Domain\:
        resource: '../../src/Operator/Domain/'
        exclude:
            - '../../src/Operator/Domain/Model/'

    App\Operator\Application\:
        resource: '../../src/Operator/Application/'
        exclude:
            - '../../src/Operator/Application/**/*Exception.php'
            - '../../src/Operator/Application/**/*Command.php'
            - '../../src/Operator/Application/**/*Query.php'

    App\Operator\Infrastructure\:
        resource: '../../src/Operator/Infrastructure/'
        exclude:
            - '../../src/Operator/Infrastructure/**/*Exception.php'

    App\Operator\UI\:
        resource: '../../src/Operator/UI/'
        exclude:
            - '../../src/Operator/UI/**/*Request.php'

    App\Operator\Domain\Port\ExternalAccountRegistrarInterface: '@App\Operator\Infrastructure\Contract\SecurityAccountRegistrarAdapter'

    App\Operator\Infrastructure\Persistence\Doctrine\OperatorRepository:
        arguments:
            $operator: '@doctrine.dbal.operator_connection'

    bookit.doctrine.middleware.search_path.operator:
        class: App\Shared\Infrastructure\Doctrine\SearchPathMiddleware
        arguments:
            $schema: 'operator'
        tags:
            - {name: doctrine.middleware, connection: operator}

when@test:
    services:
        App\Operator\Domain\Port\ExternalAccountRegistrarInterface:
            class: App\Tests\Operator\Infrastructure\ExternalAccount\NullExternalAccountRegistrar
```

- [ ] **Step 2: Add the operator DBAL connection to doctrine.yaml**

Open `config/packages/doctrine.yaml` and add the `operator` connection under `doctrine.dbal.connections`:

```yaml
            operator:
                server_version: '16'
                url: '%env(resolve:BOOKIT_DATABASE_URL)%' # same DB, isolated via search_path=operator (set by SearchPathMiddleware)
                profiling_collect_backtrace: '%kernel.debug%'
```

- [ ] **Step 3: Map exceptions in exceptions.yaml**

Open `config/services/exceptions.yaml` and add these two entries inside the `$map:` block (alongside the existing Booker entries):

```yaml
                App\Operator\Domain\Exception\OperatorAlreadyExistsException:
                    type: 'https://book.it/problems/operator-already-exists'
                    title: 'Operator Already Exists'
                    status: 409
                App\Operator\Domain\Exception\ExternalAccountCreationException:
                    type: 'https://book.it/problems/external-account-creation-failed'
                    title: 'External Account Creation Failed'
                    status: 500
```

- [ ] **Step 4: Import operator.yaml in services.yaml**

Open `config/services.yaml`. Find the block that imports other context YAMLs (look for `- { resource: services/booker.yaml }` or similar) and add:

```yaml
    - { resource: services/operator.yaml }
```

- [ ] **Step 5: Verify the container compiles**

```bash
docker compose run --rm php bin/console cache:clear
```

Expected: cache cleared with no errors.

- [ ] **Step 6: Commit**

```bash
git add config/services/operator.yaml config/packages/doctrine.yaml config/services/exceptions.yaml config/services.yaml
git commit -m "feat(operator): add service config, DBAL connection and exception mappings"
```

---

## Task 7: UI Layer — Request + Controller

**Files:**
- Create: `src/Operator/UI/Http/Controller/RegisterOperator/RegisterOperatorRequest.php`
- Create: `src/Operator/UI/Http/Controller/RegisterOperator/RegisterOperatorController.php`

- [ ] **Step 1: Create the request DTO**

```php
// src/Operator/UI/Http/Controller/RegisterOperator/RegisterOperatorRequest.php
<?php

declare(strict_types=1);

namespace App\Operator\UI\Http\Controller\RegisterOperator;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterOperatorRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        #[OA\Property(type: 'string', example: 'Alice', maxLength: 100, minLength: 1)]
        public ?string $firstName = null,
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        #[OA\Property(type: 'string', example: 'Martin', maxLength: 100, minLength: 1)]
        public ?string $lastName = null,
        #[Assert\NotBlank]
        #[Assert\Email]
        #[OA\Property(type: 'string', format: 'email', example: 'alice.martin@hotel.com')]
        public ?string $email = null,
        #[Assert\NotBlank]
        #[Assert\Length(min: 5, max: 50)]
        #[OA\Property(type: 'string', example: '+33612345678', maxLength: 50, minLength: 5)]
        public ?string $phone = null,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 100)]
        #[OA\Property(type: 'string', example: 'MySecurePassword123!', minLength: 8, maxLength: 100)]
        public ?string $password = null,
    ) {
    }
}
```

- [ ] **Step 2: Create the controller**

```php
// src/Operator/UI/Http/Controller/RegisterOperator/RegisterOperatorController.php
<?php

declare(strict_types=1);

namespace App\Operator\UI\Http\Controller\RegisterOperator;

use App\Operator\Application\Service\RegisterOperatorCommandFactory;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RegisterOperatorController
{
    public function __construct(
        private RegisterOperatorCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
    ) {
    }

    #[Route('/operators', name: 'operator_register_operator', methods: ['POST'])]
    #[OA\Post(
        summary: 'Register a new operator',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: RegisterOperatorRequest::class)),
        ),
        tags: ['Operators'],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Operator registered',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'firstName', type: 'string', example: 'Alice'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Martin'),
                        new OA\Property(property: 'email', type: 'string', format: 'email'),
                        new OA\Property(property: 'phone', type: 'string', example: '+33612345678'),
                        new OA\Property(property: 'registeredAt', type: 'string', format: 'date-time'),
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
                description: 'Validation error',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'),
                ),
            ),
            new OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported media type', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
        ],
    )]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json')]
        RegisterOperatorRequest $request,
    ): Response {
        $command = $this->commandFactory->create(
            $request->firstName ?? '',
            $request->lastName ?? '',
            $request->email ?? '',
            $request->phone ?? '',
            $request->password ?? '',
        );
        $this->commandBus->execute($command);

        return new JsonResponse([
            'id' => $command->id,
            'firstName' => $command->firstName,
            'lastName' => $command->lastName,
            'email' => $command->email,
            'phone' => $command->phone,
            'registeredAt' => $command->registeredAt->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Operator/UI/
git commit -m "feat(operator): add RegisterOperatorController and request DTO"
```

---

## Task 8: Functional Test

**Files:**
- Create: `tests/Operator/UI/Http/Controller/RegisterOperator/RegisterOperatorControllerTest.php`

- [ ] **Step 1: Write the functional test**

```php
// tests/Operator/UI/Http/Controller/RegisterOperator/RegisterOperatorControllerTest.php
<?php

declare(strict_types=1);

namespace App\Tests\Operator\UI\Http\Controller\RegisterOperator;

use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;
use App\Tests\Operator\Infrastructure\ExternalAccount\ThrowingExternalAccountRegistrar;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class RegisterOperatorControllerTest extends WebTestCase
{
    private const array VALID_PAYLOAD = [
        'firstName' => 'Alice',
        'lastName' => 'Martin',
        'email' => 'alice.martin@hotel.com',
        'phone' => '+33612345678',
        'password' => 'SecurePass123!',
    ];

    #[Test]
    public function itRegistersAnOperatorAndReturns201(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/operators',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{id: string, firstName: string, lastName: string, email: string, phone: string, registeredAt: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['id']);
        self::assertSame('Alice', $body['firstName']);
        self::assertSame('Martin', $body['lastName']);
        self::assertSame('alice.martin@hotel.com', $body['email']);
        self::assertSame('+33612345678', $body['phone']);
        self::assertNotEmpty($body['registeredAt']);
    }

    #[Test]
    public function itReturns409WhenEmailAlreadyExists(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/operators',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'POST',
            uri: '/api/v1/operators',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/operator-already-exists', $body['type']);
        self::assertSame('Operator Already Exists', $body['title']);
        self::assertSame(Response::HTTP_CONFLICT, $body['status']);
    }

    #[Test]
    public function itReturns422AsAProblemDetailWithViolationsWhenFieldIsMissing(): void
    {
        $client = static::createClient();

        $payload = self::VALID_PAYLOAD;
        unset($payload['email']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/operators',
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
            uri: '/api/v1/operators',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenPasswordIsTooShort(): void
    {
        $client = static::createClient();

        $payload = array_merge(self::VALID_PAYLOAD, ['password' => 'short', 'email' => 'short-pw@example.com']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/operators',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        /** @var array{violations: list<array{field: string, message: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('password', $fields);
    }

    #[Test]
    public function itReturns500WhenExternalAccountCreationFails(): void
    {
        $client = static::createClient();
        static::getContainer()->set(
            ExternalAccountRegistrarInterface::class,
            new ThrowingExternalAccountRegistrar(),
        );

        $payload = array_merge(self::VALID_PAYLOAD, ['email' => 'keycloak-fail@example.com']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/operators',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/external-account-creation-failed', $body['type']);
        self::assertSame('External Account Creation Failed', $body['title']);
        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $body['status']);
    }
}
```

- [ ] **Step 2: Run unit tests to confirm nothing broke**

```bash
make unit-test
```

Expected: all green.

- [ ] **Step 3: Run functional tests**

```bash
make functional-test
```

Expected: new operator tests pass, no regressions.

- [ ] **Step 4: Commit**

```bash
git add tests/Operator/UI/
git commit -m "test(operator): add functional tests for RegisterOperatorController"
```

---

## Task 9: Lint + OpenAPI

- [ ] **Step 1: Run full lint**

```bash
make lint
```

Expected: CS Fixer (no changes), PHPStan (no errors), Deptrac (no violations). Fix any issues before continuing.

- [ ] **Step 2: Regenerate OpenAPI spec**

```bash
make openapi
```

Expected: `openapi.yaml` updated with the new `POST /operators` endpoint under the `Operators` tag.

- [ ] **Step 3: Commit**

```bash
git add openapi.yaml
git commit -m "chore(operator): update OpenAPI spec"
```

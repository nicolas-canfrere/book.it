# Booker Keycloak Registration (Flow A) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Register a new Booker through a single `POST /bookers` request that creates a Keycloak account (with `emailVerified: true`) and persists the Booker domain profile, with synchronous compensation on DB failure.

**Architecture:** A new `Security` bounded context owns Keycloak integration and an `identity_mapping` table (keyed by `internal_id + context`). The Booker context communicates with it via a published contract (`AccountRegistrarInterface`) through a bridge adapter in `Booker\Infrastructure\Contract\`. Domain validation (age, email uniqueness) runs before any Keycloak call.

**Tech Stack:** PHP 8.4, Symfony 8.0, Symfony HttpClient, Doctrine DBAL, Keycloak 26, PostgreSQL 16 (`security` schema), PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-06-05-booker-keycloak-registration-design.md`

---

## File Map

### Create
| File | Purpose |
|---|---|
| `src/Security/Application/Contract/AccountRegistrarInterface.php` | Published contract — `register` / `unregister` |
| `src/Security/Application/Contract/AccountRegistrationFailedException.php` | Contract-level exception thrown by Keycloak registrar |
| `src/Security/Infrastructure/Persistence/IdentityMappingRepository.php` | DBAL repo — `save`, `delete`, `findExternalId` on `security.identity_mapping` |
| `src/Security/Infrastructure/Keycloak/KeycloakAccountRegistrar.php` | Implements `AccountRegistrarInterface` via Keycloak Admin REST API |
| `config/services/security.yaml` | DI wiring for Security context |
| `migrations/Version20260605000001.php` | Creates `security` schema + `identity_mapping` table |
| `src/Booker/Domain/Port/ExternalAccountRegistrarInterface.php` | Booker domain port — `register(bookerId, email, password)` / `unregister(bookerId)` |
| `src/Booker/Domain/Exception/ExternalAccountCreationException.php` | Domain exception for external account failure |
| `src/Booker/Infrastructure/Contract/SecurityAccountRegistrarAdapter.php` | Bridges `ExternalAccountRegistrarInterface` → `AccountRegistrarInterface`, injects `'booker'` context, wraps exception |
| `src/Booker/Application/UseCase/RegisterBookerWithCredentials/RegisterBookerWithCredentialsCommand.php` | Command DTO with `id`, `firstName`, `lastName`, `email`, `phone`, `dateOfBirth`, `password`, `registeredAt` |
| `src/Booker/Application/Service/RegisterBookerWithCredentialsCommandFactory.php` | Generates `id` via `BookerIdGeneratorInterface`, `registeredAt` via `ClockInterface` |
| `src/Booker/Application/UseCase/RegisterBookerWithCredentials/RegisterBookerWithCredentialsCommandHandler.php` | Validates age + email, calls `ExternalAccountRegistrarInterface`, saves Booker, compensates on DB failure |
| `tests/Security/Infrastructure/Persistence/IdentityMappingRepositoryTest.php` | Unit — DBAL mock |
| `tests/Security/Infrastructure/Keycloak/KeycloakAccountRegistrarTest.php` | Unit — HttpClient mock |
| `tests/Booker/Infrastructure/Contract/SecurityAccountRegistrarAdapterTest.php` | Unit — delegation + exception wrapping |
| `tests/Booker/Application/UseCase/RegisterBookerWithCredentials/RegisterBookerWithCredentialsCommandHandlerTest.php` | Unit — age, email duplicate, DB failure compensation, happy path |
| `tests/Booker/Infrastructure/ExternalAccount/NullExternalAccountRegistrar.php` | Test double (no-op) for functional tests |

### Modify
| File | What changes |
|---|---|
| `docker-compose.yml` | Add `keycloak` service |
| `config/packages/doctrine.yaml` | Add `security` DBAL connection |
| `config/services.yaml` | Import `security.yaml` |
| `config/services/booker.yaml` | Add `when@test:` block wiring `NullExternalAccountRegistrar` |
| `config/services/exceptions.yaml` | Map `ExternalAccountCreationException` → 500 |
| `deptrac-contexts.yaml` | Add `Security` + `SecurityContract` layers; allow `Booker` → `SecurityContract` |
| `src/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerRequest.php` | Add `password` field |
| `src/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerController.php` | Inject `RegisterBookerWithCredentialsCommandFactory`, call new command |
| `tests/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerControllerTest.php` | Add `password` to payload; add 500 test; add 422 missing-password test |
| `.env` | Add `KEYCLOAK_BASE_URL`, `KEYCLOAK_REALM`, `KEYCLOAK_ADMIN_CLIENT_ID`, `KEYCLOAK_ADMIN_CLIENT_SECRET` |

### Delete
| File |
|---|
| `src/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommand.php` |
| `src/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommandHandler.php` |
| `src/Booker/Application/Service/RegisterBookerCommandFactory.php` |
| `tests/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommandHandlerTest.php` |

---

## Task 1: Add Keycloak to Docker Compose and env vars

**Files:**
- Modify: `docker-compose.yml`
- Modify: `.env`

- [ ] **Step 1: Add Keycloak service to docker-compose.yml**

Locate the `services:` block and add:
```yaml
  keycloak:
    image: quay.io/keycloak/keycloak:26.0
    command: start-dev
    ports:
      - "8080:8080"
    environment:
      KEYCLOAK_ADMIN: admin
      KEYCLOAK_ADMIN_PASSWORD: admin
```

- [ ] **Step 2: Add env vars to .env**

```dotenv
KEYCLOAK_BASE_URL=http://localhost:8080
KEYCLOAK_REALM=bookit
KEYCLOAK_ADMIN_CLIENT_ID=bookit-admin
KEYCLOAK_ADMIN_CLIENT_SECRET=changeme
```

- [ ] **Step 3: Start Keycloak and verify it boots**

```bash
make up
# Wait ~10s then:
curl -s http://localhost:8080/realms/master | jq .realm
# Expected: "master"
```

- [ ] **Step 4: Commit**

```bash
git add docker-compose.yml .env
git commit -m "feat(security): add Keycloak service to docker-compose"
```

---

## Task 2: Security\Application\Contract — published interface + exception

**Files:**
- Create: `src/Security/Application/Contract/AccountRegistrarInterface.php`
- Create: `src/Security/Application/Contract/AccountRegistrationFailedException.php`

No tests needed — pure interface and exception.

- [ ] **Step 1: Create AccountRegistrarInterface**

```php
<?php

declare(strict_types=1);

namespace App\Security\Application\Contract;

interface AccountRegistrarInterface
{
    public function register(string $internalId, string $context, string $email, string $password): void;

    public function unregister(string $internalId, string $context): void;
}
```

- [ ] **Step 2: Create AccountRegistrationFailedException**

```php
<?php

declare(strict_types=1);

namespace App\Security\Application\Contract;

final class AccountRegistrationFailedException extends \RuntimeException
{
    public function __construct(string $email, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Failed to register account for "%s"', $email), 0, $previous);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Security/Application/Contract/
git commit -m "feat(security): add AccountRegistrarInterface published contract"
```

---

## Task 3: Doctrine — security connection + identity_mapping migration

**Files:**
- Modify: `config/packages/doctrine.yaml`
- Create: `migrations/Version20260605000001.php`

- [ ] **Step 1: Add security DBAL connection to doctrine.yaml**

In the `doctrine.dbal.connections:` block, add after the last existing connection:
```yaml
            security:
                server_version: '16'
                url: '%env(resolve:BOOKIT_DATABASE_URL)%' # same DB, isolated via search_path=security (set by SearchPathMiddleware)
                profiling_collect_backtrace: '%kernel.debug%'
```

- [ ] **Step 2: Create migration file**

Create `migrations/Version20260605000001.php`:
```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create security schema and identity_mapping table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS security');
        $this->addSql('CREATE TABLE security.identity_mapping (
            internal_id UUID NOT NULL,
            context     VARCHAR(50) NOT NULL,
            external_id UUID NOT NULL,
            PRIMARY KEY (internal_id, context)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE security.identity_mapping');
        $this->addSql('DROP SCHEMA IF EXISTS security');
    }
}
```

- [ ] **Step 3: Run migration and verify**

```bash
make migrate
# Expected: [OK] 1 migrations executed.
```

- [ ] **Step 4: Commit**

```bash
git add config/packages/doctrine.yaml migrations/Version20260605000001.php
git commit -m "feat(security): add security schema and identity_mapping table"
```

---

## Task 4: TDD — IdentityMappingRepository

**Files:**
- Create: `tests/Security/Infrastructure/Persistence/IdentityMappingRepositoryTest.php`
- Create: `src/Security/Infrastructure/Persistence/IdentityMappingRepository.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Security/Infrastructure/Persistence/IdentityMappingRepositoryTest.php`:
```php
<?php

declare(strict_types=1);

namespace App\Tests\Security\Infrastructure\Persistence;

use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class IdentityMappingRepositoryTest extends TestCase
{
    private Connection&MockObject $connection;
    private IdentityMappingRepository $repository;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->repository = new IdentityMappingRepository($this->connection);
    }

    #[Test]
    public function it_saves_mapping(): void
    {
        $this->connection->expects(self::once())
            ->method('insert')
            ->with('security.identity_mapping', [
                'internal_id' => 'booker-uuid',
                'context' => 'booker',
                'external_id' => 'keycloak-uuid',
            ]);

        $this->repository->save('booker-uuid', 'booker', 'keycloak-uuid');
    }

    #[Test]
    public function it_deletes_mapping(): void
    {
        $this->connection->expects(self::once())
            ->method('delete')
            ->with('security.identity_mapping', [
                'internal_id' => 'booker-uuid',
                'context' => 'booker',
            ]);

        $this->repository->delete('booker-uuid', 'booker');
    }

    #[Test]
    public function it_finds_external_id(): void
    {
        $this->connection->method('fetchOne')
            ->with(
                'SELECT external_id FROM security.identity_mapping WHERE internal_id = ? AND context = ?',
                ['booker-uuid', 'booker'],
            )
            ->willReturn('keycloak-uuid');

        self::assertSame('keycloak-uuid', $this->repository->findExternalId('booker-uuid', 'booker'));
    }

    #[Test]
    public function it_returns_null_when_mapping_not_found(): void
    {
        $this->connection->method('fetchOne')->willReturn(false);

        self::assertNull($this->repository->findExternalId('booker-uuid', 'booker'));
    }
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
make unit-test -- --filter IdentityMappingRepositoryTest
# Expected: FAIL — class not found
```

- [ ] **Step 3: Implement IdentityMappingRepository**

Create `src/Security/Infrastructure/Persistence/IdentityMappingRepository.php`:
```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;

final readonly class IdentityMappingRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function save(string $internalId, string $context, string $externalId): void
    {
        $this->connection->insert('security.identity_mapping', [
            'internal_id' => $internalId,
            'context' => $context,
            'external_id' => $externalId,
        ]);
    }

    public function delete(string $internalId, string $context): void
    {
        $this->connection->delete('security.identity_mapping', [
            'internal_id' => $internalId,
            'context' => $context,
        ]);
    }

    public function findExternalId(string $internalId, string $context): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT external_id FROM security.identity_mapping WHERE internal_id = ? AND context = ?',
            [$internalId, $context],
        );

        return false !== $result ? (string) $result : null;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

```bash
make unit-test -- --filter IdentityMappingRepositoryTest
# Expected: OK, 4 tests, 4 assertions
```

- [ ] **Step 5: Commit**

```bash
git add src/Security/Infrastructure/Persistence/ tests/Security/Infrastructure/Persistence/
git commit -m "feat(security): add IdentityMappingRepository"
```

---

## Task 5: TDD — KeycloakAccountRegistrar

**Files:**
- Create: `tests/Security/Infrastructure/Keycloak/KeycloakAccountRegistrarTest.php`
- Create: `src/Security/Infrastructure/Keycloak/KeycloakAccountRegistrar.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Security/Infrastructure/Keycloak/KeycloakAccountRegistrarTest.php`:
```php
<?php

declare(strict_types=1);

namespace App\Tests\Security\Infrastructure\Keycloak;

use App\Security\Application\Contract\AccountRegistrationFailedException;
use App\Security\Infrastructure\Keycloak\KeycloakAccountRegistrar;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[Group('unit')]
final class KeycloakAccountRegistrarTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private IdentityMappingRepository&MockObject $mappingRepository;
    private KeycloakAccountRegistrar $registrar;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->mappingRepository = $this->createMock(IdentityMappingRepository::class);
        $this->registrar = new KeycloakAccountRegistrar(
            $this->httpClient,
            $this->mappingRepository,
            'http://keycloak:8080',
            'bookit',
            'bookit-admin',
            'secret',
        );
    }

    private function mockTokenResponse(): ResponseInterface&MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['access_token' => 'test-token']);

        return $response;
    }

    #[Test]
    public function it_creates_account_and_saves_mapping(): void
    {
        $createResponse = $this->createMock(ResponseInterface::class);
        $createResponse->method('getStatusCode')->willReturn(201);
        $createResponse->method('getHeaders')->willReturn([
            'location' => ['http://keycloak:8080/admin/realms/bookit/users/keycloak-uuid'],
        ]);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($this->mockTokenResponse(), $createResponse);

        $this->mappingRepository->expects(self::once())
            ->method('save')
            ->with('booker-uuid', 'booker', 'keycloak-uuid');

        $this->registrar->register('booker-uuid', 'booker', 'test@example.com', 'password123');
    }

    #[Test]
    public function it_throws_on_non_201_response(): void
    {
        $createResponse = $this->createMock(ResponseInterface::class);
        $createResponse->method('getStatusCode')->willReturn(409);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($this->mockTokenResponse(), $createResponse);

        $this->expectException(AccountRegistrationFailedException::class);
        $this->registrar->register('booker-uuid', 'booker', 'test@example.com', 'password123');
    }

    #[Test]
    public function it_unregisters_account_and_removes_mapping(): void
    {
        $this->mappingRepository->method('findExternalId')
            ->with('booker-uuid', 'booker')
            ->willReturn('keycloak-uuid');

        $deleteResponse = $this->createMock(ResponseInterface::class);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($this->mockTokenResponse(), $deleteResponse);

        $this->mappingRepository->expects(self::once())
            ->method('delete')
            ->with('booker-uuid', 'booker');

        $this->registrar->unregister('booker-uuid', 'booker');
    }

    #[Test]
    public function it_skips_unregister_when_mapping_not_found(): void
    {
        $this->mappingRepository->method('findExternalId')->willReturn(null);
        $this->httpClient->expects(self::never())->method('request');
        $this->mappingRepository->expects(self::never())->method('delete');

        $this->registrar->unregister('booker-uuid', 'booker');
    }
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
make unit-test -- --filter KeycloakAccountRegistrarTest
# Expected: FAIL — class not found
```

- [ ] **Step 3: Implement KeycloakAccountRegistrar**

Create `src/Security/Infrastructure/Keycloak/KeycloakAccountRegistrar.php`:
```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrationFailedException;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class KeycloakAccountRegistrar implements AccountRegistrarInterface
{
    private ?string $adminToken = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly IdentityMappingRepository $mappingRepository,
        private readonly string $keycloakBaseUrl,
        private readonly string $keycloakRealm,
        private readonly string $keycloakClientId,
        private readonly string $keycloakClientSecret,
    ) {
    }

    public function register(string $internalId, string $context, string $email, string $password): void
    {
        $response = $this->httpClient->request(
            'POST',
            "{$this->keycloakBaseUrl}/admin/realms/{$this->keycloakRealm}/users",
            [
                'auth_bearer' => $this->fetchAdminToken(),
                'json' => [
                    'email' => $email,
                    'username' => $email,
                    'emailVerified' => true,
                    'enabled' => true,
                    'credentials' => [[
                        'type' => 'password',
                        'value' => $password,
                        'temporary' => false,
                    ]],
                ],
            ],
        );

        if (201 !== $response->getStatusCode()) {
            throw new AccountRegistrationFailedException($email);
        }

        $location = $response->getHeaders(false)['location'][0] ?? '';
        $keycloakId = basename($location);

        $this->mappingRepository->save($internalId, $context, $keycloakId);
    }

    public function unregister(string $internalId, string $context): void
    {
        $keycloakId = $this->mappingRepository->findExternalId($internalId, $context);
        if (null === $keycloakId) {
            return;
        }

        try {
            $this->httpClient->request(
                'DELETE',
                "{$this->keycloakBaseUrl}/admin/realms/{$this->keycloakRealm}/users/{$keycloakId}",
                ['auth_bearer' => $this->fetchAdminToken()],
            );
        } catch (\Throwable) {
            // best-effort: log and continue
        }

        $this->mappingRepository->delete($internalId, $context);
    }

    private function fetchAdminToken(): string
    {
        if (null !== $this->adminToken) {
            return $this->adminToken;
        }

        $response = $this->httpClient->request(
            'POST',
            "{$this->keycloakBaseUrl}/realms/{$this->keycloakRealm}/protocol/openid-connect/token",
            [
                'body' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->keycloakClientId,
                    'client_secret' => $this->keycloakClientSecret,
                ],
            ],
        );

        $this->adminToken = $response->toArray()['access_token'];

        return $this->adminToken;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

```bash
make unit-test -- --filter KeycloakAccountRegistrarTest
# Expected: OK, 4 tests, 4 assertions
```

- [ ] **Step 5: Commit**

```bash
git add src/Security/Infrastructure/Keycloak/ tests/Security/Infrastructure/Keycloak/
git commit -m "feat(security): add KeycloakAccountRegistrar"
```

---

## Task 6: config/services/security.yaml + services.yaml import

**Files:**
- Create: `config/services/security.yaml`
- Modify: `config/services.yaml`

- [ ] **Step 1: Create config/services/security.yaml**

```yaml
parameters: {}

services:
    _defaults:
        autowire: true
        autoconfigure: true

    App\Security\Application\Contract\AccountRegistrarInterface: '@App\Security\Infrastructure\Keycloak\KeycloakAccountRegistrar'

    App\Security\Infrastructure\:
        resource: '../../src/Security/Infrastructure/'
        exclude:
            - '../../src/Security/Infrastructure/**/*Exception.php'

    App\Security\Infrastructure\Keycloak\KeycloakAccountRegistrar:
        arguments:
            $keycloakBaseUrl: '%env(KEYCLOAK_BASE_URL)%'
            $keycloakRealm: '%env(KEYCLOAK_REALM)%'
            $keycloakClientId: '%env(KEYCLOAK_ADMIN_CLIENT_ID)%'
            $keycloakClientSecret: '%env(KEYCLOAK_ADMIN_CLIENT_SECRET)%'

    App\Security\Infrastructure\Persistence\IdentityMappingRepository:
        arguments:
            $connection: '@doctrine.dbal.security_connection'

    bookit.doctrine.middleware.search_path.security:
        class: App\Shared\Infrastructure\Doctrine\SearchPathMiddleware
        arguments:
            $schema: 'security'
        tags:
            - {name: doctrine.middleware, connection: security}
```

- [ ] **Step 2: Import security.yaml in config/services.yaml**

Add after the last existing import:
```yaml
    - { resource: './services/security.yaml' }
```

- [ ] **Step 3: Verify container compiles**

```bash
docker compose exec php bin/console cache:clear
# Expected: no errors
```

- [ ] **Step 4: Commit**

```bash
git add config/services/security.yaml config/services.yaml
git commit -m "feat(security): add Security context DI config"
```

---

## Task 7: Booker domain — ExternalAccountRegistrarInterface + ExternalAccountCreationException

**Files:**
- Create: `src/Booker/Domain/Port/ExternalAccountRegistrarInterface.php`
- Create: `src/Booker/Domain/Exception/ExternalAccountCreationException.php`

No tests needed — pure interface and exception.

- [ ] **Step 1: Create ExternalAccountRegistrarInterface**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Domain\Port;

interface ExternalAccountRegistrarInterface
{
    public function register(string $bookerId, string $email, string $password): void;

    public function unregister(string $bookerId): void;
}
```

- [ ] **Step 2: Create ExternalAccountCreationException**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Domain\Exception;

final class ExternalAccountCreationException extends \RuntimeException
{
    public function __construct(string $email, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Failed to create external account for "%s"', $email), 0, $previous);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Booker/Domain/Port/ExternalAccountRegistrarInterface.php \
        src/Booker/Domain/Exception/ExternalAccountCreationException.php
git commit -m "feat(booker): add ExternalAccountRegistrarInterface domain port"
```

---

## Task 8: TDD — SecurityAccountRegistrarAdapter

**Files:**
- Create: `tests/Booker/Infrastructure/Contract/SecurityAccountRegistrarAdapterTest.php`
- Create: `src/Booker/Infrastructure/Contract/SecurityAccountRegistrarAdapter.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Booker/Infrastructure/Contract/SecurityAccountRegistrarAdapterTest.php`:
```php
<?php

declare(strict_types=1);

namespace App\Tests\Booker\Infrastructure\Contract;

use App\Booker\Domain\Exception\ExternalAccountCreationException;
use App\Booker\Infrastructure\Contract\SecurityAccountRegistrarAdapter;
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
    public function it_delegates_register_with_booker_context(): void
    {
        $this->accountRegistrar->expects(self::once())
            ->method('register')
            ->with('booker-id', 'booker', 'email@example.com', 'password');

        $this->adapter->register('booker-id', 'email@example.com', 'password');
    }

    #[Test]
    public function it_delegates_unregister_with_booker_context(): void
    {
        $this->accountRegistrar->expects(self::once())
            ->method('unregister')
            ->with('booker-id', 'booker');

        $this->adapter->unregister('booker-id');
    }

    #[Test]
    public function it_wraps_account_registration_failed_exception(): void
    {
        $this->accountRegistrar->method('register')
            ->willThrowException(new AccountRegistrationFailedException('email@example.com'));

        $this->expectException(ExternalAccountCreationException::class);
        $this->adapter->register('booker-id', 'email@example.com', 'password');
    }
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
make unit-test -- --filter SecurityAccountRegistrarAdapterTest
# Expected: FAIL — class not found
```

- [ ] **Step 3: Implement SecurityAccountRegistrarAdapter**

Create `src/Booker/Infrastructure/Contract/SecurityAccountRegistrarAdapter.php`:
```php
<?php

declare(strict_types=1);

namespace App\Booker\Infrastructure\Contract;

use App\Booker\Domain\Exception\ExternalAccountCreationException;
use App\Booker\Domain\Port\ExternalAccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Security\Application\Contract\AccountRegistrationFailedException;

final readonly class SecurityAccountRegistrarAdapter implements ExternalAccountRegistrarInterface
{
    public function __construct(
        private AccountRegistrarInterface $accountRegistrar,
    ) {
    }

    public function register(string $bookerId, string $email, string $password): void
    {
        try {
            $this->accountRegistrar->register($bookerId, 'booker', $email, $password);
        } catch (AccountRegistrationFailedException $e) {
            throw new ExternalAccountCreationException($email, $e);
        }
    }

    public function unregister(string $bookerId): void
    {
        $this->accountRegistrar->unregister($bookerId, 'booker');
    }
}
```

- [ ] **Step 4: Run to verify it passes**

```bash
make unit-test -- --filter SecurityAccountRegistrarAdapterTest
# Expected: OK, 3 tests, 3 assertions
```

- [ ] **Step 5: Commit**

```bash
git add src/Booker/Infrastructure/Contract/SecurityAccountRegistrarAdapter.php \
        tests/Booker/Infrastructure/Contract/SecurityAccountRegistrarAdapterTest.php
git commit -m "feat(booker): add SecurityAccountRegistrarAdapter cross-context bridge"
```

---

## Task 9: Update deptrac-contexts.yaml

**Files:**
- Modify: `deptrac-contexts.yaml`

- [ ] **Step 1: Add Security and SecurityContract layers**

In the `layers:` block, add after the last existing layer (before the `ruleset:` block):
```yaml
        -
            name: Security
            collectors:
                -
                    type: bool
                    must:
                        -
                            type: classLike
                            value: 'App\\Security\\.*'
                    must_not:
                        -
                            type: classLike
                            value: 'App\\Security\\Application\\Contract\\.*'
        -
            name: SecurityContract
            collectors:
                -
                    type: classLike
                    value: 'App\\Security\\Application\\Contract\\.*'
```

- [ ] **Step 2: Update the ruleset**

In the `ruleset:` section:

Update `Booker:` to add `SecurityContract`:
```yaml
        Booker:
            - BookerContract
            - SecurityContract
            - Shared
            - Vendor
```

Add the Security context rules (after `Search:`):
```yaml
        Security:
            - SecurityContract
            - Shared
            - Vendor
        SecurityContract: ~
```

- [ ] **Step 3: Run deptrac and verify no violations**

```bash
make deptrac
# Expected: no violations
```

- [ ] **Step 4: Commit**

```bash
git add deptrac-contexts.yaml
git commit -m "feat(security): register Security context in deptrac-contexts.yaml"
```

---

## Task 10: RegisterBookerWithCredentials command + factory

**Files:**
- Create: `src/Booker/Application/UseCase/RegisterBookerWithCredentials/RegisterBookerWithCredentialsCommand.php`
- Create: `src/Booker/Application/Service/RegisterBookerWithCredentialsCommandFactory.php`

No tests needed — DTOs.

- [ ] **Step 1: Create the command**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Application\UseCase\RegisterBookerWithCredentials;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class RegisterBookerWithCredentialsCommand implements SyncCommandInterface
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public \DateTimeImmutable $dateOfBirth,
        public string $password,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
```

- [ ] **Step 2: Create the factory**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Application\Service;

use App\Booker\Application\UseCase\RegisterBookerWithCredentials\RegisterBookerWithCredentialsCommand;
use App\Booker\Domain\Port\BookerIdGeneratorInterface;
use Psr\Clock\ClockInterface;

final readonly class RegisterBookerWithCredentialsCommandFactory
{
    public function __construct(
        private BookerIdGeneratorInterface $bookerIdGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function create(
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $dateOfBirth,
        string $password,
    ): RegisterBookerWithCredentialsCommand {
        return new RegisterBookerWithCredentialsCommand(
            $this->bookerIdGenerator->generate(),
            $firstName,
            $lastName,
            $email,
            $phone,
            new \DateTimeImmutable($dateOfBirth),
            $password,
            $this->clock->now(),
        );
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Booker/Application/UseCase/RegisterBookerWithCredentials/ \
        src/Booker/Application/Service/RegisterBookerWithCredentialsCommandFactory.php
git commit -m "feat(booker): add RegisterBookerWithCredentialsCommand and factory"
```

---

## Task 11: TDD — RegisterBookerWithCredentialsCommandHandler

**Files:**
- Create: `tests/Booker/Application/UseCase/RegisterBookerWithCredentials/RegisterBookerWithCredentialsCommandHandlerTest.php`
- Create: `src/Booker/Application/UseCase/RegisterBookerWithCredentials/RegisterBookerWithCredentialsCommandHandler.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Booker/Application/UseCase/RegisterBookerWithCredentials/RegisterBookerWithCredentialsCommandHandlerTest.php`:
```php
<?php

declare(strict_types=1);

namespace App\Tests\Booker\Application\UseCase\RegisterBookerWithCredentials;

use App\Booker\Application\UseCase\RegisterBookerWithCredentials\RegisterBookerWithCredentialsCommand;
use App\Booker\Application\UseCase\RegisterBookerWithCredentials\RegisterBookerWithCredentialsCommandHandler;
use App\Booker\Domain\Exception\BookerAlreadyExistsException;
use App\Booker\Domain\Exception\BookerUnderageException;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Booker\Domain\Port\ExternalAccountRegistrarInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegisterBookerWithCredentialsCommandHandlerTest extends TestCase
{
    private BookerRepositoryInterface&MockObject $repository;
    private ExternalAccountRegistrarInterface&MockObject $accountRegistrar;
    private RegisterBookerWithCredentialsCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(BookerRepositoryInterface::class);
        $this->accountRegistrar = $this->createMock(ExternalAccountRegistrarInterface::class);
        $this->handler = new RegisterBookerWithCredentialsCommandHandler(
            $this->repository,
            $this->accountRegistrar,
        );
    }

    private function makeCommand(
        string $dateOfBirth = '1990-01-01',
        string $registeredAt = '2025-01-01',
        string $email = 'jean@example.com',
        string $id = 'uuid-1',
    ): RegisterBookerWithCredentialsCommand {
        return new RegisterBookerWithCredentialsCommand(
            $id,
            'Jean',
            'Dupont',
            $email,
            '+33612345678',
            new \DateTimeImmutable($dateOfBirth),
            'password123',
            new \DateTimeImmutable($registeredAt),
        );
    }

    #[Test]
    public function it_throws_underage_exception_before_calling_keycloak(): void
    {
        $command = $this->makeCommand(dateOfBirth: '2010-01-01', registeredAt: '2025-01-01');
        $this->accountRegistrar->expects(self::never())->method('register');

        $this->expectException(BookerUnderageException::class);
        ($this->handler)($command);
    }

    #[Test]
    public function it_throws_already_exists_before_calling_keycloak(): void
    {
        $this->repository->method('existsByEmail')->willReturn(true);
        $command = $this->makeCommand();
        $this->accountRegistrar->expects(self::never())->method('register');

        $this->expectException(BookerAlreadyExistsException::class);
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
    public function it_registers_external_account_then_saves_booker(): void
    {
        $this->repository->method('existsByEmail')->willReturn(false);
        $command = $this->makeCommand();

        $this->accountRegistrar->expects(self::once())
            ->method('register')
            ->with('uuid-1', 'jean@example.com', 'password123');
        $this->accountRegistrar->expects(self::never())->method('unregister');
        $this->repository->expects(self::once())->method('add');

        ($this->handler)($command);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
make unit-test -- --filter RegisterBookerWithCredentialsCommandHandlerTest
# Expected: FAIL — class not found
```

- [ ] **Step 3: Implement the handler**

Create `src/Booker/Application/UseCase/RegisterBookerWithCredentials/RegisterBookerWithCredentialsCommandHandler.php`:
```php
<?php

declare(strict_types=1);

namespace App\Booker\Application\UseCase\RegisterBookerWithCredentials;

use App\Booker\Domain\Exception\BookerAlreadyExistsException;
use App\Booker\Domain\Exception\BookerUnderageException;
use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Booker\Domain\Port\ExternalAccountRegistrarInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class RegisterBookerWithCredentialsCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private BookerRepositoryInterface $bookerRepository,
        private ExternalAccountRegistrarInterface $accountRegistrar,
    ) {
    }

    public function __invoke(RegisterBookerWithCredentialsCommand $command): void
    {
        $age = $command->registeredAt->diff($command->dateOfBirth)->y;

        if ($age < 18) {
            throw new BookerUnderageException();
        }

        if ($this->bookerRepository->existsByEmail($command->email)) {
            throw new BookerAlreadyExistsException($command->email);
        }

        $this->accountRegistrar->register($command->id, $command->email, $command->password);

        try {
            $this->bookerRepository->add(new Booker(
                $command->id,
                $command->firstName,
                $command->lastName,
                $command->email,
                $command->phone,
                $command->dateOfBirth,
                $command->registeredAt,
            ));
        } catch (\Throwable $e) {
            $this->accountRegistrar->unregister($command->id);
            throw $e;
        }
    }
}
```

- [ ] **Step 4: Run to verify it passes**

```bash
make unit-test -- --filter RegisterBookerWithCredentialsCommandHandlerTest
# Expected: OK, 4 tests, 5 assertions
```

- [ ] **Step 5: Commit**

```bash
git add src/Booker/Application/UseCase/RegisterBookerWithCredentials/RegisterBookerWithCredentialsCommandHandler.php \
        tests/Booker/Application/UseCase/RegisterBookerWithCredentials/
git commit -m "feat(booker): add RegisterBookerWithCredentialsCommandHandler"
```

---

## Task 12: Update RegisterBookerRequest + RegisterBookerController

**Files:**
- Modify: `src/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerRequest.php`
- Modify: `src/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerController.php`

- [ ] **Step 1: Add password to RegisterBookerRequest**

In `RegisterBookerRequest.php`, add after the `$dateOfBirth` property:
```php
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 100)]
        #[OA\Property(type: 'string', example: 'MySecurePassword123!', minLength: 8, maxLength: 100)]
        public ?string $password = null,
```

- [ ] **Step 2: Update RegisterBookerController**

Replace the `RegisterBookerCommandFactory` import and constructor injection with `RegisterBookerWithCredentialsCommandFactory`. Replace the `create()` call to pass `$request->password`:

Full updated controller:
```php
<?php

declare(strict_types=1);

namespace App\Booker\UI\Http\Controller\RegisterBooker;

use App\Booker\Application\Service\RegisterBookerWithCredentialsCommandFactory;
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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class RegisterBookerController
{
    public function __construct(
        private RegisterBookerWithCredentialsCommandFactory $commandFactory,
        private SyncCommandBusInterface $commandBus,
        private SyncQueryBusInterface $queryBus,
        private BookerSerializer $bookerSerializer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/bookers', name: 'booker_register_booker', methods: ['POST'])]
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
                headers: [new OA\Header(header: 'Location', description: 'URL of the created booker', schema: new OA\Schema(type: 'string', format: 'uri'))],
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'firstName', type: 'string', example: 'Jean'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Dupont'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jean.dupont@example.com'),
                        new OA\Property(property: 'phone', type: 'string', example: '+33612345678'),
                        new OA\Property(property: 'dateOfBirth', type: 'string', format: 'date', example: '1990-05-15'),
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
                description: 'Validation error or underage applicant',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ValidationProblemDetail'),
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Not found',
                content: new OA\MediaType(
                    mediaType: 'application/problem+json',
                    schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'),
                ),
            ),
            new OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported media type', content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: '#/components/schemas/ProblemDetail'))),
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
            $request->password,
        );
        $this->commandBus->execute($command);

        $booker = $this->queryBus->ask(new GetBookerQuery($command->id));
        if (null === $booker) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse(
            $this->bookerSerializer->serialize($booker),
            Response::HTTP_CREATED,
            ['Location' => $this->urlGenerator->generate('booker_get_booker', ['id' => $command->id])],
        );
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Booker/UI/Http/Controller/RegisterBooker/
git commit -m "feat(booker): update RegisterBookerController to use new credentials command"
```

---

## Task 13: Config updates — exceptions + test wiring

**Files:**
- Modify: `config/services/exceptions.yaml`
- Modify: `config/services/booker.yaml`
- Create: `tests/Booker/Infrastructure/ExternalAccount/NullExternalAccountRegistrar.php`

- [ ] **Step 1: Add interface alias to config/services/booker.yaml (non-test)**

In `config/services/booker.yaml`, add after the `_instanceof` block and before the `resource:` declarations:
```yaml
    App\Booker\Domain\Port\ExternalAccountRegistrarInterface: '@App\Booker\Infrastructure\Contract\SecurityAccountRegistrarAdapter'
```

This lets Symfony autowire `ExternalAccountRegistrarInterface` → `SecurityAccountRegistrarAdapter` in all non-test environments.

- [ ] **Step 2: Map ExternalAccountCreationException in exceptions.yaml**

Add to the `$map` block:
```yaml
            App\Booker\Domain\Exception\ExternalAccountCreationException:
                type: 'https://book.it/problems/external-account-creation-failed'
                title: 'External Account Creation Failed'
                status: 500
```

- [ ] **Step 3: Create NullExternalAccountRegistrar test double**

Create `tests/Booker/Infrastructure/ExternalAccount/NullExternalAccountRegistrar.php`:
```php
<?php

declare(strict_types=1);

namespace App\Tests\Booker\Infrastructure\ExternalAccount;

use App\Booker\Domain\Port\ExternalAccountRegistrarInterface;

final class NullExternalAccountRegistrar implements ExternalAccountRegistrarInterface
{
    public function register(string $bookerId, string $email, string $password): void {}

    public function unregister(string $bookerId): void {}
}
```

- [ ] **Step 4: Wire NullExternalAccountRegistrar for test environment in booker.yaml**

Add at the bottom of `config/services/booker.yaml`:
```yaml
when@test:
    services:
        App\Booker\Domain\Port\ExternalAccountRegistrarInterface:
            class: App\Tests\Booker\Infrastructure\ExternalAccount\NullExternalAccountRegistrar
            public: true
```

- [ ] **Step 5: Verify container compiles in test env**

```bash
docker compose exec php bin/console cache:clear --env=test
# Expected: no errors
```

- [ ] **Step 6: Commit**

```bash
git add config/services/exceptions.yaml config/services/booker.yaml \
        tests/Booker/Infrastructure/ExternalAccount/
git commit -m "feat(booker): wire test double and map ExternalAccountCreationException"
```

---

## Task 14: Delete obsolete files

**Files to delete:**
- `src/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommand.php`
- `src/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommandHandler.php`
- `src/Booker/Application/Service/RegisterBookerCommandFactory.php`
- `tests/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommandHandlerTest.php`

- [ ] **Step 1: Delete old source files**

```bash
rm src/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommand.php
rm src/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommandHandler.php
rm src/Booker/Application/Service/RegisterBookerCommandFactory.php
```

- [ ] **Step 2: Delete old test**

```bash
rm tests/Booker/Application/UseCase/RegisterBooker/RegisterBookerCommandHandlerTest.php
rmdir tests/Booker/Application/UseCase/RegisterBooker 2>/dev/null || true
rmdir src/Booker/Application/UseCase/RegisterBooker 2>/dev/null || true
```

- [ ] **Step 3: Run unit tests to verify no breakage**

```bash
make unit-test
# Expected: all tests pass (no reference to deleted classes)
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "refactor(booker): remove RegisterBookerCommand and factory replaced by credentials variant"
```

---

## Task 15: Update RegisterBookerControllerTest

**Files:**
- Modify: `tests/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerControllerTest.php`

- [ ] **Step 1: Replace full test file**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Booker\UI\Http\Controller\RegisterBooker;

use App\Booker\Domain\Exception\ExternalAccountCreationException;
use App\Booker\Domain\Port\ExternalAccountRegistrarInterface;
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
        'password' => 'MySecurePassword123!',
    ];

    #[Test]
    public function itRegistersABookerAndReturns201(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/bookers',
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
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'POST',
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
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
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

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
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        /** @var array{violations: list<array{field: string, message: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('email', $fields);
    }

    #[Test]
    public function itReturns422WhenPasswordIsTooShort(): void
    {
        $client = static::createClient();

        $payload = array_merge(self::VALID_PAYLOAD, ['password' => 'short', 'email' => 'short@example.com']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        /** @var array{violations: list<array{field: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('password', $fields);
    }

    #[Test]
    public function itReturns500WhenExternalAccountCreationFails(): void
    {
        $client = static::createClient();

        self::getContainer()->set(
            ExternalAccountRegistrarInterface::class,
            new class implements ExternalAccountRegistrarInterface {
                public function register(string $bookerId, string $email, string $password): void
                {
                    throw new ExternalAccountCreationException($email);
                }

                public function unregister(string $bookerId): void {}
            },
        );

        $client->request(
            method: 'POST',
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenEmailIsInvalid(): void
    {
        $client = static::createClient();

        $payload = array_merge(self::VALID_PAYLOAD, ['email' => 'not-an-email']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/bookers',
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
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }
}
```

- [ ] **Step 2: Run functional tests**

```bash
make functional-test -- --filter RegisterBookerControllerTest
# Expected: OK, 8 tests pass
```

- [ ] **Step 3: Commit**

```bash
git add tests/Booker/UI/Http/Controller/RegisterBooker/RegisterBookerControllerTest.php
git commit -m "test(booker): update RegisterBookerControllerTest for credentials flow"
```

---

## Task 16: Full test suite + lint

- [ ] **Step 1: Run full test suite**

```bash
make test
# Expected: all tests pass (unit + functional)
```

- [ ] **Step 2: Run static analysis and architecture check**

```bash
make lint
# Expected: no CS violations, no PHPStan errors, no deptrac violations
```

- [ ] **Step 3: Fix any issues found, then commit**

```bash
make apply-cs
git add -A
git commit -m "fix(booker): apply CS fixer after credentials flow implementation"
```

---

## Task 17: Regenerate OpenAPI spec

- [ ] **Step 1: Run openapi generation**

```bash
make openapi
```

- [ ] **Step 2: Verify password field appears in POST /bookers schema**

```bash
grep -A 5 '"password"' openapi.yaml
# Expected: password property with minLength: 8
```

- [ ] **Step 3: Commit**

```bash
git add openapi.yaml
git commit -m "docs(api): regenerate openapi spec — add password to Booker registration"
```

# Operator Admin Role & CLI Bootstrap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Protect `POST /operators` behind `ROLE_ADMIN`, read Keycloak realm roles from the JWT so Symfony sees them, and provide an `operator:register-admin` CLI command to bootstrap the first admin operator.

**Architecture:** Three independent threads merged into one plan. (1) `BearerTokenAuthenticator` currently hardcodes `['ROLE_OPERATOR']` — it must read `realm_access.roles` from the JWT payload and pass them to `OperatorUser`. (2) A new `assignRealmRole` capability threads from `KeycloakHttpClient` → `KeycloakAccountRegistrar` → `SecurityAccountRegistrarAdapter` → a new `AssignAdminRoleToOperator` use case, following the same layering pattern as `register`. (3) A `operator:register-admin` Symfony console command dispatches `RegisterOperatorCommand` then `AssignAdminRoleToOperatorCommand`, bypassing the HTTP firewall entirely. The HTTP route is locked to `ROLE_ADMIN` via `access_control`.

**Tech Stack:** PHP 8.4, Symfony 8.0, Keycloak 26, Firebase JWT, PHPUnit 11, Symfony Console.

---

## File Map

### Create
| File | Purpose |
|---|---|
| `src/Operator/Application/UseCase/AssignAdminRoleToOperator/AssignAdminRoleToOperatorCommand.php` | Command DTO — carries `operatorId` |
| `src/Operator/Application/UseCase/AssignAdminRoleToOperator/AssignAdminRoleToOperatorCommandHandler.php` | Handler — calls `ExternalAccountRegistrarInterface::assignAdminRole` |
| `src/Operator/UI/Console/RegisterAdminOperatorCommand.php` | Symfony console command — registers operator then promotes to admin |
| `tests/Operator/Application/UseCase/AssignAdminRoleToOperator/AssignAdminRoleToOperatorCommandHandlerTest.php` | Unit test for the handler |
| `tests/Operator/UI/Console/RegisterAdminOperatorCommandTest.php` | Unit test for the console command |

### Modify
| File | What changes |
|---|---|
| `src/Security/Infrastructure/Keycloak/KeycloakHttpClientInterface.php` | Add `assignRealmRole(string $keycloakId, string $roleName): void` |
| `src/Security/Infrastructure/Keycloak/KeycloakHttpClient.php` | Implement `assignRealmRole` — 2 Keycloak API calls |
| `src/Security/Application/Contract/AccountRegistrarInterface.php` | Add `assignRole(string $internalId, string $context, string $roleName): void` |
| `src/Security/Infrastructure/Keycloak/KeycloakAccountRegistrar.php` | Implement `assignRole` — look up Keycloak ID then call client |
| `tests/Security/Infrastructure/Keycloak/KeycloakAccountRegistrarTest.php` | Add tests for `assignRole` |
| `src/Operator/Domain/Port/ExternalAccountRegistrarInterface.php` | Add `assignAdminRole(string $operatorId): void` |
| `src/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapter.php` | Implement `assignAdminRole` — delegates to `AccountRegistrarInterface::assignRole` with `'ROLE_ADMIN'` |
| `tests/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapterTest.php` | Add test for `assignAdminRole` delegation |
| `tests/Operator/Infrastructure/ExternalAccount/NullExternalAccountRegistrar.php` | Add no-op `assignAdminRole` |
| `tests/Operator/Infrastructure/ExternalAccount/ThrowingExternalAccountRegistrar.php` | Add throwing `assignAdminRole` |
| `src/Security/Infrastructure/Keycloak/OperatorUser.php` | Accept `array $roles` constructor parameter |
| `src/Security/Infrastructure/Keycloak/BearerTokenAuthenticator.php` | Extract `realm_access.roles` from JWT, pass to `OperatorUser` |
| `tests/Shared/AuthenticatedWebTestCase.php` | Accept `array $roles` param in `createAuthenticatedClient()`, embed `realm_access` in JWT |
| `tests/Operator/UI/Http/Controller/RegisterOperator/RegisterOperatorControllerTest.php` | Pass `['ROLE_OPERATOR', 'ROLE_ADMIN']` to `createAuthenticatedClient()` |
| `config/packages/security.yaml` | Add `ROLE_ADMIN` access_control rule for `POST /operators` |

---

## Task 1: JWT roles — `OperatorUser` + `BearerTokenAuthenticator`

**Files:**
- Modify: `src/Security/Infrastructure/Keycloak/OperatorUser.php`
- Modify: `src/Security/Infrastructure/Keycloak/BearerTokenAuthenticator.php`

No new test file — the existing `AuthenticatedWebTestCase`-based functional tests cover authentication. The role extraction will be validated in Task 6 when `RegisterOperatorControllerTest` requires `ROLE_ADMIN`.

- [ ] **Step 1: Update `OperatorUser` to accept roles**

Replace the full file content:

```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class OperatorUser implements UserInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $id,
        public string $email,
        private array $roles = ['ROLE_OPERATOR'],
    ) {
    }

    public function getUserIdentifier(): string
    {
        if ('' === $this->email) {
            throw new \LogicException('OperatorUser email cannot be empty');
        }

        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }
}
```

- [ ] **Step 2: Update `BearerTokenAuthenticator::authenticate()` to extract realm roles**

In `BearerTokenAuthenticator.php`, replace the line that creates `$user` (near the end of `authenticate()`):

Old:
```php
        $user = new OperatorUser($operator->id, $operator->email);
```

New:
```php
        $realmRoles = (array) ($payload->realm_access?->roles ?? []);
        $roles = ['ROLE_OPERATOR'];
        if (\in_array('ROLE_ADMIN', $realmRoles, true)) {
            $roles[] = 'ROLE_ADMIN';
        }

        $user = new OperatorUser($operator->id, $operator->email, $roles);
```

- [ ] **Step 3: Verify container compiles and existing tests still pass**

```bash
docker compose exec php bin/console cache:clear
make unit-test
# Expected: all existing tests pass — OperatorUser default is still ['ROLE_OPERATOR']
```

- [ ] **Step 4: Commit**

```bash
git add src/Security/Infrastructure/Keycloak/OperatorUser.php \
        src/Security/Infrastructure/Keycloak/BearerTokenAuthenticator.php
git commit -m "feat(security): read realm_access.roles from JWT, pass to OperatorUser"
```

---

## Task 2: `KeycloakHttpClientInterface` + `KeycloakHttpClient` — `assignRealmRole`

**Files:**
- Modify: `src/Security/Infrastructure/Keycloak/KeycloakHttpClientInterface.php`
- Modify: `src/Security/Infrastructure/Keycloak/KeycloakHttpClient.php`

`assignRealmRole` makes two Keycloak Admin API calls:
1. `GET /admin/realms/{realm}/roles/{roleName}` — fetches `{id, name}` of the role
2. `POST /admin/realms/{realm}/users/{keycloakId}/role-mappings/realm` — assigns it

Both go through the existing `request()` private method which handles token refresh, retries, and error handling.

- [ ] **Step 1: Add method to the interface**

Replace full file:

```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Symfony\Contracts\HttpClient\ResponseInterface;

interface KeycloakHttpClientInterface
{
    public function createUser(string $email, string $password): ResponseInterface;

    public function deleteUser(string $keycloakId): void;

    public function assignRealmRole(string $keycloakId, string $roleName): void;
}
```

- [ ] **Step 2: Implement `assignRealmRole` in `KeycloakHttpClient`**

Add the following method to `KeycloakHttpClient` (after `deleteUser`):

```php
    public function assignRealmRole(string $keycloakId, string $roleName): void
    {
        $roleResponse = $this->request('GET', "/admin/realms/{$this->keycloakRealm}/roles/{$roleName}");
        $role = $roleResponse->toArray();

        $this->request(
            'POST',
            "/admin/realms/{$this->keycloakRealm}/users/{$keycloakId}/role-mappings/realm",
            ['json' => [['id' => $role['id'], 'name' => $role['name']]]],
        );
    }
```

- [ ] **Step 3: Verify container compiles**

```bash
docker compose exec php bin/console cache:clear
# Expected: no errors
```

- [ ] **Step 4: Commit**

```bash
git add src/Security/Infrastructure/Keycloak/KeycloakHttpClientInterface.php \
        src/Security/Infrastructure/Keycloak/KeycloakHttpClient.php
git commit -m "feat(security): add assignRealmRole to KeycloakHttpClient"
```

---

## Task 3: `AccountRegistrarInterface` + `KeycloakAccountRegistrar` — `assignRole`

**Files:**
- Modify: `src/Security/Application/Contract/AccountRegistrarInterface.php`
- Modify: `src/Security/Infrastructure/Keycloak/KeycloakAccountRegistrar.php`
- Modify: `tests/Security/Infrastructure/Keycloak/KeycloakAccountRegistrarTest.php`

- [ ] **Step 1: Add `assignRole` to the Security published contract**

Replace full file:

```php
<?php

declare(strict_types=1);

namespace App\Security\Application\Contract;

interface AccountRegistrarInterface
{
    public function register(string $internalId, string $context, string $email, string $password): void;

    public function unregister(string $internalId, string $context): void;

    public function assignRole(string $internalId, string $context, string $roleName): void;
}
```

- [ ] **Step 2: Implement `assignRole` in `KeycloakAccountRegistrar`**

Add the following method to `KeycloakAccountRegistrar` (after `unregister`):

```php
    public function assignRole(string $internalId, string $context, string $roleName): void
    {
        $keycloakId = $this->mappingRepository->findExternalId($internalId, $context);
        if (null === $keycloakId) {
            $this->logger->error('Cannot assign role: no Keycloak mapping found', [
                'internal_id' => $internalId,
                'context' => $context,
                'role' => $roleName,
            ]);
            throw new \RuntimeException("No Keycloak mapping found for {$internalId} (context: {$context})");
        }

        $this->keycloakClient->assignRealmRole($keycloakId, $roleName);

        $this->logger->info('Realm role assigned', [
            'internal_id' => $internalId,
            'context' => $context,
            'keycloak_id' => $keycloakId,
            'role' => $roleName,
        ]);
    }
```

- [ ] **Step 3: Write failing tests for `assignRole`**

In `tests/Security/Infrastructure/Keycloak/KeycloakAccountRegistrarTest.php`, add two test methods after the existing ones:

```php
    #[Test]
    public function it_assigns_realm_role(): void
    {
        $this->mappingRepository->method('findExternalId')
            ->with('operator-uuid', 'operator')
            ->willReturn('keycloak-uuid');

        $this->keycloakClient->expects(self::once())
            ->method('assignRealmRole')
            ->with('keycloak-uuid', 'ROLE_ADMIN');

        $this->registrar->assignRole('operator-uuid', 'operator', 'ROLE_ADMIN');
    }

    #[Test]
    public function it_throws_when_no_mapping_found_for_role_assignment(): void
    {
        $this->mappingRepository->method('findExternalId')->willReturn(null);

        $this->keycloakClient->expects(self::never())->method('assignRealmRole');

        $this->expectException(\RuntimeException::class);
        $this->registrar->assignRole('operator-uuid', 'operator', 'ROLE_ADMIN');
    }
```

Note: in the existing test `setUp`, `$this->registrar` is constructed with `$this->keycloakClient` (of type `KeycloakHttpClientInterface&MockObject`). Since `KeycloakHttpClientInterface` now declares `assignRealmRole`, PHPUnit will auto-mock it — no setup change needed.

- [ ] **Step 4: Run tests**

```bash
make unit-test -- --filter KeycloakAccountRegistrarTest
# Expected: OK, 6 tests pass
```

- [ ] **Step 5: Commit**

```bash
git add src/Security/Application/Contract/AccountRegistrarInterface.php \
        src/Security/Infrastructure/Keycloak/KeycloakAccountRegistrar.php \
        tests/Security/Infrastructure/Keycloak/KeycloakAccountRegistrarTest.php
git commit -m "feat(security): add assignRole to AccountRegistrarInterface and KeycloakAccountRegistrar"
```

---

## Task 4: Operator domain port + adapter + test doubles — `assignAdminRole`

**Files:**
- Modify: `src/Operator/Domain/Port/ExternalAccountRegistrarInterface.php`
- Modify: `src/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapter.php`
- Modify: `tests/Operator/Infrastructure/ExternalAccount/NullExternalAccountRegistrar.php`
- Modify: `tests/Operator/Infrastructure/ExternalAccount/ThrowingExternalAccountRegistrar.php`
- Modify: `tests/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapterTest.php` (create if absent)

- [ ] **Step 1: Add `assignAdminRole` to the Operator domain port**

Replace full file:

```php
<?php

declare(strict_types=1);

namespace App\Operator\Domain\Port;

interface ExternalAccountRegistrarInterface
{
    public function register(string $operatorId, string $email, string $password): void;

    public function unregister(string $operatorId): void;

    public function assignAdminRole(string $operatorId): void;
}
```

- [ ] **Step 2: Implement in `SecurityAccountRegistrarAdapter`**

Replace full file:

```php
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

    public function assignAdminRole(string $operatorId): void
    {
        $this->accountRegistrar->assignRole($operatorId, 'operator', 'ROLE_ADMIN');
    }
}
```

- [ ] **Step 3: Add `assignAdminRole` to `NullExternalAccountRegistrar`**

Replace full file:

```php
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

    public function assignAdminRole(string $operatorId): void
    {
    }
}
```

- [ ] **Step 4: Add `assignAdminRole` to `ThrowingExternalAccountRegistrar`**

Replace full file:

```php
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

    public function assignAdminRole(string $operatorId): void
    {
        throw new \RuntimeException('Keycloak unavailable');
    }
}
```

- [ ] **Step 5: Write failing test for the adapter**

Check if `tests/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapterTest.php` exists:

```bash
ls tests/Operator/Infrastructure/Contract/ 2>/dev/null || echo "directory missing"
```

If the file does not exist, create `tests/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapterTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Operator\Infrastructure\Contract;

use App\Operator\Infrastructure\Contract\SecurityAccountRegistrarAdapter;
use App\Security\Application\Contract\AccountRegistrarInterface;
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
    public function it_delegates_assign_admin_role(): void
    {
        $this->accountRegistrar->expects(self::once())
            ->method('assignRole')
            ->with('operator-id', 'operator', 'ROLE_ADMIN');

        $this->adapter->assignAdminRole('operator-id');
    }
}
```

If the file already exists, add the `it_delegates_assign_admin_role` test method to the existing class.

- [ ] **Step 6: Run tests**

```bash
make unit-test -- --filter SecurityAccountRegistrarAdapterTest
# Expected: all tests pass
```

- [ ] **Step 7: Commit**

```bash
git add src/Operator/Domain/Port/ExternalAccountRegistrarInterface.php \
        src/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapter.php \
        tests/Operator/Infrastructure/ExternalAccount/NullExternalAccountRegistrar.php \
        tests/Operator/Infrastructure/ExternalAccount/ThrowingExternalAccountRegistrar.php \
        tests/Operator/Infrastructure/Contract/SecurityAccountRegistrarAdapterTest.php
git commit -m "feat(operator): add assignAdminRole to ExternalAccountRegistrarInterface and adapter"
```

---

## Task 5: `AssignAdminRoleToOperator` use case (TDD)

**Files:**
- Create: `src/Operator/Application/UseCase/AssignAdminRoleToOperator/AssignAdminRoleToOperatorCommand.php`
- Create: `src/Operator/Application/UseCase/AssignAdminRoleToOperator/AssignAdminRoleToOperatorCommandHandler.php`
- Create: `tests/Operator/Application/UseCase/AssignAdminRoleToOperator/AssignAdminRoleToOperatorCommandHandlerTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Operator/Application/UseCase/AssignAdminRoleToOperator/AssignAdminRoleToOperatorCommandHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Operator\Application\UseCase\AssignAdminRoleToOperator;

use App\Operator\Application\UseCase\AssignAdminRoleToOperator\AssignAdminRoleToOperatorCommand;
use App\Operator\Application\UseCase\AssignAdminRoleToOperator\AssignAdminRoleToOperatorCommandHandler;
use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AssignAdminRoleToOperatorCommandHandlerTest extends TestCase
{
    private ExternalAccountRegistrarInterface&MockObject $accountRegistrar;
    private AssignAdminRoleToOperatorCommandHandler $handler;

    protected function setUp(): void
    {
        $this->accountRegistrar = $this->createMock(ExternalAccountRegistrarInterface::class);
        $this->handler = new AssignAdminRoleToOperatorCommandHandler($this->accountRegistrar);
    }

    #[Test]
    public function it_assigns_admin_role_to_operator(): void
    {
        $this->accountRegistrar->expects(self::once())
            ->method('assignAdminRole')
            ->with('operator-uuid');

        ($this->handler)(new AssignAdminRoleToOperatorCommand('operator-uuid'));
    }
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
make unit-test -- --filter AssignAdminRoleToOperatorCommandHandlerTest
# Expected: FAIL — class not found
```

- [ ] **Step 3: Create the command DTO**

Create `src/Operator/Application/UseCase/AssignAdminRoleToOperator/AssignAdminRoleToOperatorCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Operator\Application\UseCase\AssignAdminRoleToOperator;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class AssignAdminRoleToOperatorCommand implements SyncCommandInterface
{
    public function __construct(
        public string $operatorId,
    ) {
    }
}
```

- [ ] **Step 4: Create the handler**

Create `src/Operator/Application/UseCase/AssignAdminRoleToOperator/AssignAdminRoleToOperatorCommandHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Operator\Application\UseCase\AssignAdminRoleToOperator;

use App\Operator\Domain\Port\ExternalAccountRegistrarInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class AssignAdminRoleToOperatorCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private ExternalAccountRegistrarInterface $accountRegistrar,
    ) {
    }

    public function __invoke(AssignAdminRoleToOperatorCommand $command): void
    {
        $this->accountRegistrar->assignAdminRole($command->operatorId);
    }
}
```

- [ ] **Step 5: Run to verify it passes**

```bash
make unit-test -- --filter AssignAdminRoleToOperatorCommandHandlerTest
# Expected: OK, 1 test, 1 assertion
```

- [ ] **Step 6: Commit**

```bash
git add src/Operator/Application/UseCase/AssignAdminRoleToOperator/ \
        tests/Operator/Application/UseCase/AssignAdminRoleToOperator/
git commit -m "feat(operator): add AssignAdminRoleToOperator use case"
```

---

## Task 6: `security.yaml` + `AuthenticatedWebTestCase` + `RegisterOperatorControllerTest`

**Files:**
- Modify: `config/packages/security.yaml`
- Modify: `tests/Shared/AuthenticatedWebTestCase.php`
- Modify: `tests/Operator/UI/Http/Controller/RegisterOperator/RegisterOperatorControllerTest.php`

This task locks the route, then fixes the test infrastructure so functional tests can produce admin tokens.

- [ ] **Step 1: Add `ROLE_ADMIN` rule to `access_control`**

In `config/packages/security.yaml`, replace the `access_control` block:

```yaml
    access_control:
        - { path: ^/operators$, roles: ROLE_ADMIN, methods: [POST] }
        - { path: ^/, roles: ROLE_OPERATOR }
```

- [ ] **Step 2: Update `AuthenticatedWebTestCase` to support admin tokens**

In `createAuthenticatedClient()`, add a `array $roles = ['ROLE_OPERATOR']` parameter and embed it in the JWT under `realm_access`:

Replace the full file:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Operator\Application\Contract\OperatorFinderInterface;
use App\Operator\Application\Contract\OperatorView;
use App\Security\Infrastructure\Keycloak\KeycloakJwksProviderInterface;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AuthenticatedWebTestCase extends WebTestCase
{
    private static string $privateKey = '';
    private static string $publicKey = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        \assert($resource instanceof \OpenSSLAsymmetricKey);
        $privateKey = '';
        openssl_pkey_export($resource, $privateKey);
        \assert(\is_string($privateKey));
        self::$privateKey = $privateKey;
        $details = openssl_pkey_get_details($resource);
        \assert(\is_array($details));
        \assert(\is_string($details['key']));
        self::$publicKey = $details['key'];
    }

    /**
     * @param list<string> $roles Keycloak realm roles to embed in the JWT (e.g. ['ROLE_OPERATOR', 'ROLE_ADMIN'])
     */
    protected static function createAuthenticatedClient(array $roles = ['ROLE_OPERATOR']): KernelBrowser
    {
        $client = static::createClient();
        $client->disableReboot();

        $publicKey = self::$publicKey;
        static::getContainer()->set(
            KeycloakJwksProviderInterface::class,
            new class($publicKey) implements KeycloakJwksProviderInterface {
                public function __construct(private string $key)
                {
                }

                public function getPublicKeys(): array
                {
                    return ['default' => new Key($this->key, 'RS256')];
                }
            }
        );

        static::getContainer()->set(
            IdentityMappingRepository::class,
            new class extends IdentityMappingRepository {
                public function __construct()
                {
                }

                public function findInternalId(string $externalId, string $context): string
                {
                    return 'test-internal-operator-id';
                }
            }
        );

        static::getContainer()->set(
            OperatorFinderInterface::class,
            new class implements OperatorFinderInterface {
                public function find(string $operatorId): OperatorView
                {
                    return new OperatorView($operatorId, 'test-operator@example.com');
                }
            }
        );

        $token = JWT::encode([
            'sub' => 'test-operator',
            'iss' => 'http://keycloack:8080/realms/bookit',
            'iat' => time(),
            'exp' => time() + 3600,
            'realm_access' => ['roles' => $roles],
        ], self::$privateKey, 'RS256');

        $client->setServerParameter('HTTP_AUTHORIZATION', "Bearer {$token}");

        return $client;
    }
}
```

- [ ] **Step 3: Update `RegisterOperatorControllerTest` to use an admin token**

Find every call to `static::createAuthenticatedClient()` in `tests/Operator/UI/Http/Controller/RegisterOperator/RegisterOperatorControllerTest.php` and replace each with:

```php
static::createAuthenticatedClient(['ROLE_OPERATOR', 'ROLE_ADMIN'])
```

- [ ] **Step 4: Run functional tests**

```bash
make functional-test -- --filter RegisterOperatorControllerTest
# Expected: all tests pass
```

- [ ] **Step 5: Verify that a plain ROLE_OPERATOR token now gets 403**

Add the following test to `RegisterOperatorControllerTest`:

```php
    #[Test]
    public function itReturns403WhenCallerIsNotAdmin(): void
    {
        $client = static::createAuthenticatedClient(['ROLE_OPERATOR']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/operators',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }
```

- [ ] **Step 6: Run functional tests again**

```bash
make functional-test -- --filter RegisterOperatorControllerTest
# Expected: all tests pass including the new 403 test
```

- [ ] **Step 7: Commit**

```bash
git add config/packages/security.yaml \
        tests/Shared/AuthenticatedWebTestCase.php \
        tests/Operator/UI/Http/Controller/RegisterOperator/RegisterOperatorControllerTest.php
git commit -m "feat(security): restrict POST /operators to ROLE_ADMIN, update test infra and controller test"
```

---

## Task 7: `operator:register-admin` console command (TDD)

**Files:**
- Create: `src/Operator/UI/Console/RegisterAdminOperatorCommand.php`
- Create: `tests/Operator/UI/Console/RegisterAdminOperatorCommandTest.php`

The command is registered automatically via the `App\Operator\UI\:` resource block in `config/services/operator.yaml` — no DI changes needed.

- [ ] **Step 1: Write the failing test**

Create `tests/Operator/UI/Console/RegisterAdminOperatorCommandTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Operator\UI\Console;

use App\Operator\Application\Service\RegisterOperatorCommandFactory;
use App\Operator\Application\UseCase\AssignAdminRoleToOperator\AssignAdminRoleToOperatorCommand;
use App\Operator\Application\UseCase\RegisterOperator\RegisterOperatorCommand;
use App\Operator\UI\Console\RegisterAdminOperatorCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('unit')]
final class RegisterAdminOperatorCommandTest extends TestCase
{
    private RegisterOperatorCommandFactory&MockObject $commandFactory;
    private SyncCommandBusInterface&MockObject $commandBus;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->commandFactory = $this->createMock(RegisterOperatorCommandFactory::class);
        $this->commandBus = $this->createMock(SyncCommandBusInterface::class);
        $this->tester = new CommandTester(
            new RegisterAdminOperatorCommand($this->commandFactory, $this->commandBus),
        );
    }

    #[Test]
    public function it_dispatches_register_then_assign_admin_role(): void
    {
        $registerCommand = new RegisterOperatorCommand(
            'uuid-1',
            'Alice',
            'Martin',
            'alice@hotel.com',
            '+33612345678',
            'SecurePass123!',
            new \DateTimeImmutable(),
        );

        $this->commandFactory->expects(self::once())
            ->method('create')
            ->with('Alice', 'Martin', 'alice@hotel.com', '+33612345678', 'SecurePass123!')
            ->willReturn($registerCommand);

        $dispatchedCommands = [];
        $this->commandBus->expects(self::exactly(2))
            ->method('execute')
            ->willReturnCallback(static function (object $cmd) use (&$dispatchedCommands): void {
                $dispatchedCommands[] = $cmd;
            });

        $this->tester->execute([
            'firstName' => 'Alice',
            'lastName' => 'Martin',
            'email' => 'alice@hotel.com',
            'phone' => '+33612345678',
            'password' => 'SecurePass123!',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertInstanceOf(RegisterOperatorCommand::class, $dispatchedCommands[0]);
        self::assertInstanceOf(AssignAdminRoleToOperatorCommand::class, $dispatchedCommands[1]);
        self::assertSame('uuid-1', $dispatchedCommands[1]->operatorId);
        self::assertStringContainsString('alice@hotel.com', $this->tester->getDisplay());
    }
}
```

- [ ] **Step 2: Run to verify it fails**

```bash
make unit-test -- --filter RegisterAdminOperatorCommandTest
# Expected: FAIL — class not found
```

- [ ] **Step 3: Create the console command**

Create `src/Operator/UI/Console/RegisterAdminOperatorCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Operator\UI\Console;

use App\Operator\Application\Service\RegisterOperatorCommandFactory;
use App\Operator\Application\UseCase\AssignAdminRoleToOperator\AssignAdminRoleToOperatorCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'operator:register-admin',
    description: 'Register a new operator and grant them ROLE_ADMIN in Keycloak',
)]
final class RegisterAdminOperatorCommand extends Command
{
    public function __construct(
        private readonly RegisterOperatorCommandFactory $commandFactory,
        private readonly SyncCommandBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('firstName', InputArgument::REQUIRED, 'First name')
            ->addArgument('lastName', InputArgument::REQUIRED, 'Last name')
            ->addArgument('email', InputArgument::REQUIRED, 'Email address')
            ->addArgument('phone', InputArgument::REQUIRED, 'Phone number (e.g. +33612345678)')
            ->addArgument('password', InputArgument::REQUIRED, 'Password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $registerCommand = $this->commandFactory->create(
            (string) $input->getArgument('firstName'),
            (string) $input->getArgument('lastName'),
            (string) $input->getArgument('email'),
            (string) $input->getArgument('phone'),
            (string) $input->getArgument('password'),
        );

        $this->commandBus->execute($registerCommand);
        $this->commandBus->execute(new AssignAdminRoleToOperatorCommand($registerCommand->id));

        $output->writeln(sprintf(
            '<info>Admin operator "%s" registered with id %s</info>',
            $registerCommand->email,
            $registerCommand->id,
        ));

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

```bash
make unit-test -- --filter RegisterAdminOperatorCommandTest
# Expected: OK, 1 test, 5 assertions
```

- [ ] **Step 5: Verify the command appears in `bin/console`**

```bash
docker compose exec php bin/console operator:register-admin --help
# Expected: shows firstName, lastName, email, phone, password arguments
```

- [ ] **Step 6: Commit**

```bash
git add src/Operator/UI/Console/RegisterAdminOperatorCommand.php \
        tests/Operator/UI/Console/RegisterAdminOperatorCommandTest.php
git commit -m "feat(operator): add operator:register-admin console command"
```

---

## Task 8: Full test suite + lint + OpenAPI

- [ ] **Step 1: Run unit tests**

```bash
make unit-test
# Expected: all pass
```

- [ ] **Step 2: Run functional tests**

```bash
make functional-test
# Expected: all pass
```

- [ ] **Step 3: Run static analysis and architecture check**

```bash
make lint
# Expected: no CS violations, no PHPStan errors, no deptrac violations
```

- [ ] **Step 4: Fix any issues**

If `make apply-cs` produces changes:
```bash
make apply-cs
git add -A
git commit -m "fix(operator): apply CS fixer after admin role implementation"
```

If PHPStan reports errors, fix them before regenerating OpenAPI.

- [ ] **Step 5: Regenerate OpenAPI spec**

No new HTTP routes were added, but run anyway to ensure the spec is current:

```bash
make openapi
git add openapi.yaml
git diff --cached --quiet openapi.yaml || git commit -m "docs(api): regenerate openapi spec"
```

---

## Self-Review

**Spec coverage:**

| Requirement | Task |
|---|---|
| `POST /operators` requires `ROLE_ADMIN` | Task 6 — `security.yaml` `access_control` |
| ROLE_OPERATOR token gets 403 on that route | Task 6 — new 403 test in `RegisterOperatorControllerTest` |
| Keycloak realm roles read from JWT | Task 1 — `BearerTokenAuthenticator` + `OperatorUser` |
| CLI command to bootstrap first admin operator | Task 7 — `operator:register-admin` |
| Keycloak `ROLE_ADMIN` realm role assignment | Task 2 — `KeycloakHttpClient::assignRealmRole` |
| Full layered chain from Keycloak up to use case | Tasks 2–5 |
| Test doubles updated for new interface method | Task 4 — `Null` + `Throwing` registrars |

**No gaps found.**

**Placeholder scan:** No TBD, no "similar to Task N", no steps without code.

**Type consistency check:**
- `AssignAdminRoleToOperatorCommand::$operatorId` (string) — used in Task 5 test (`$dispatchedCommands[1]->operatorId`) ✓
- `ExternalAccountRegistrarInterface::assignAdminRole(string $operatorId)` defined Task 4 Step 1, called in handler Task 5 Step 4, delegated in adapter Task 4 Step 2, no-op in Null Task 4 Step 3 ✓
- `AccountRegistrarInterface::assignRole(string $internalId, string $context, string $roleName)` defined Task 3 Step 1, called with `('ROLE_ADMIN')` in adapter Task 4 Step 2 ✓
- `KeycloakHttpClientInterface::assignRealmRole(string $keycloakId, string $roleName)` defined Task 2 Step 1, called in `KeycloakAccountRegistrar::assignRole` Task 3 Step 2 ✓
- `RegisterOperatorCommandFactory::create` signature: `(firstName, lastName, email, phone, password)` — used in console command Task 7 Step 3 and test Task 7 Step 1 ✓

# SaaS Onboarding Self-Service Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a public `POST /api/v1/onboarding` endpoint that creates an Organization (status: pending) and its owner Operator (Keycloak account + DB record) in a single request.

**Architecture:** New `Onboarding` bounded context that orchestrates two published contracts — `Organization\Application\Contract\OrganizationRegistrarInterface` and `Operator\Application\Contract\OwnerOperatorRegistrarInterface` — via thin infrastructure adapters. A `setOrganizationId` method is added to `Security\Application\Contract\AccountRegistrarInterface` so Keycloak's `organization_id` user attribute is set during owner account creation.

**Tech Stack:** PHP 8.4, Symfony 8, Doctrine DBAL, PHPUnit, Keycloak Admin REST API.

## Global Constraints

- `declare(strict_types=1)` on every file.
- Bounded context boundaries enforced by deptrac — run `make deptrac` after every structural change.
- Published contracts (`Application\Contract\`) use primitives only — no context-specific domain VOs.
- PHPStan at max level — run `make static-code-analysis` before each commit.
- HTTP errors follow RFC 7807 (`application/problem+json`) via the existing `ProblemDetailExceptionListener`.
- Tests: `#[Group('unit')]` on `TestCase`, `#[Group('functional')]` on `WebTestCase`.
- Commands run via `make` inside Docker — there is no local PHP runtime.
- New service YAML files must be imported in `config/services.yaml`.

---

## File Map

| File | Action | Purpose |
|------|--------|---------|
| `src/Security/Application/Contract/AccountRegistrarInterface.php` | Modify | Add `setOrganizationId()` |
| `src/Security/Infrastructure/Keycloak/KeycloakAccountRegistrar.php` | Modify | Implement `setOrganizationId()` |
| `tests/Security/Infrastructure/Keycloak/KeycloakAccountRegistrarTest.php` | Modify | Test `setOrganizationId()` |
| `src/Organization/Application/Contract/OrganizationRegistrarInterface.php` | Create | Published contract for creating an Organization |
| `src/Organization/Infrastructure/Contract/DoctrineOrganizationRegistrar.php` | Create | DBAL implementation of the contract |
| `config/services/organization.yaml` | Modify | Wire `OrganizationRegistrarInterface` |
| `src/Operator/Application/Contract/OwnerOperatorRegistrarInterface.php` | Create | Published contract for registering an owner Operator |
| `src/Operator/Infrastructure/Contract/DoctrineOwnerOperatorRegistrar.php` | Create | Orchestrates Keycloak + DBAL |
| `config/services/operator.yaml` | Modify | Wire `OwnerOperatorRegistrarInterface` |
| `tests/Operator/Infrastructure/Contract/DoctrineOwnerOperatorRegistrarTest.php` | Create | Unit test for the owner registrar |
| `tests/Security/Infrastructure/NullAccountRegistrar.php` | Create | Test double for `AccountRegistrarInterface` |
| `src/Onboarding/Application/Port/OrganizationRegistrarInterface.php` | Create | Onboarding's inbound port for org creation |
| `src/Onboarding/Application/Port/OwnerOperatorRegistrarInterface.php` | Create | Onboarding's inbound port for owner creation |
| `src/Onboarding/Application/UseCase/OnboardOrganization/OnboardOrganizationCommand.php` | Create | Command DTO |
| `src/Onboarding/Application/UseCase/OnboardOrganization/OnboardOrganizationHandler.php` | Create | Orchestrates org + owner creation |
| `tests/Onboarding/Application/UseCase/OnboardOrganization/OnboardOrganizationHandlerTest.php` | Create | Unit test for handler |
| `src/Onboarding/Infrastructure/Adapter/OrganizationRegistrarAdapter.php` | Create | Delegates to `Organization\Application\Contract` |
| `src/Onboarding/Infrastructure/Adapter/OwnerOperatorRegistrarAdapter.php` | Create | Delegates to `Operator\Application\Contract` |
| `src/Onboarding/UI/Http/OnboardOrganizationRequest.php` | Create | Request DTO with validation |
| `src/Onboarding/UI/Http/OnboardOrganizationController.php` | Create | `POST /onboarding` handler |
| `config/services/onboarding.yaml` | Create | DI config for Onboarding context |
| `config/services.yaml` | Modify | Import `onboarding.yaml` |
| `config/packages/security.yaml` | Modify | Add PUBLIC_ACCESS rule for `/api/v1/onboarding` |
| `config/services/exceptions.yaml` | No change | Existing 409 mappings cover both domain exceptions |
| `deptrac-contexts.yaml` | Modify | Add `Onboarding` layer + ruleset |
| `tests/Onboarding/UI/Http/OnboardOrganizationControllerTest.php` | Create | Functional tests |

---

## Task 1: Add `setOrganizationId` to `AccountRegistrarInterface` and `KeycloakAccountRegistrar`

**Files:**
- Modify: `src/Security/Application/Contract/AccountRegistrarInterface.php`
- Modify: `src/Security/Infrastructure/Keycloak/KeycloakAccountRegistrar.php`
- Modify: `tests/Security/Infrastructure/Keycloak/KeycloakAccountRegistrarTest.php`

**Interfaces:**
- Produces: `AccountRegistrarInterface::setOrganizationId(string $internalId, string $context, string $organizationId): void`

- [ ] **Step 1: Write the failing test**

Add this test method to the existing `KeycloakAccountRegistrarTest` class (after the last existing test):

```php
#[Test]
public function itSetsOrganizationIdAttributeOnKeycloakUser(): void
{
    $this->mappingRepository->expects(self::once())
        ->method('findExternalId')
        ->with('operator-uuid', 'operator')
        ->willReturn('keycloak-uuid');

    $this->keycloakClient->expects(self::once())
        ->method('setUserAttribute')
        ->with('keycloak-uuid', 'organization_id', 'org-uuid');

    $this->registrar->setOrganizationId('operator-uuid', 'operator', 'org-uuid');
}

#[Test]
public function itThrowsWhenNoMappingFoundForSetOrganizationId(): void
{
    $this->mappingRepository->method('findExternalId')->willReturn(null);

    $this->keycloakClient->expects(self::never())->method('setUserAttribute');

    $this->expectException(\RuntimeException::class);
    $this->registrar->setOrganizationId('operator-uuid', 'operator', 'org-uuid');
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
make unit-test 2>&1 | grep -A5 "setOrganizationId\|FAIL\|Error"
```

Expected: FAIL — method `setOrganizationId` not found on `KeycloakAccountRegistrar`.

- [ ] **Step 3: Add `setOrganizationId` to the interface**

Full content of `src/Security/Application/Contract/AccountRegistrarInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security\Application\Contract;

interface AccountRegistrarInterface
{
    public function register(string $internalId, string $context, string $email, string $password): void;

    public function unregister(string $internalId, string $context): void;

    public function assignRole(string $internalId, string $context, string $roleName): void;

    public function setOrganizationId(string $internalId, string $context, string $organizationId): void;
}
```

- [ ] **Step 4: Implement `setOrganizationId` in `KeycloakAccountRegistrar`**

Add this method to `src/Security/Infrastructure/Keycloak/KeycloakAccountRegistrar.php` (after `assignRole()`):

```php
public function setOrganizationId(string $internalId, string $context, string $organizationId): void
{
    $keycloakId = $this->mappingRepository->findExternalId($internalId, $context);
    if (null === $keycloakId) {
        throw new \RuntimeException("No Keycloak mapping found for {$internalId} (context: {$context})");
    }

    $this->keycloakClient->setUserAttribute($keycloakId, 'organization_id', $organizationId);
}
```

- [ ] **Step 5: Run tests to confirm they pass**

```bash
make unit-test 2>&1 | grep -E "OK|FAIL|ERROR"
```

Expected: `OK` — all tests green.

- [ ] **Step 6: Run PHPStan**

```bash
make static-code-analysis 2>&1 | tail -5
```

Expected: `[OK] No errors`.

- [ ] **Step 7: Commit**

```bash
git add src/Security/Application/Contract/AccountRegistrarInterface.php \
        src/Security/Infrastructure/Keycloak/KeycloakAccountRegistrar.php \
        tests/Security/Infrastructure/Keycloak/KeycloakAccountRegistrarTest.php
git commit -m "feat(security): add setOrganizationId to AccountRegistrarInterface and KeycloakAccountRegistrar"
```

---

## Task 2: Organization published contract

**Files:**
- Create: `src/Organization/Application/Contract/OrganizationRegistrarInterface.php`
- Create: `src/Organization/Infrastructure/Contract/DoctrineOrganizationRegistrar.php`
- Modify: `config/services/organization.yaml`

**Interfaces:**
- Consumes: `OrganizationRepositoryInterface::add()`, `::existsByContactEmail()` (already exists); `Organization::register()` (already exists); `EventDispatcherInterface`
- Produces: `Organization\Application\Contract\OrganizationRegistrarInterface::register(string, string, string, DateTimeImmutable): void`

- [ ] **Step 1: Create `OrganizationRegistrarInterface`**

```php
<?php

declare(strict_types=1);

namespace App\Organization\Application\Contract;

interface OrganizationRegistrarInterface
{
    public function register(
        string $organizationId,
        string $name,
        string $contactEmail,
        \DateTimeImmutable $registeredAt,
    ): void;
}
```

- [ ] **Step 2: Create `DoctrineOrganizationRegistrar`**

```php
<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Contract;

use App\Organization\Application\Contract\OrganizationRegistrarInterface;
use App\Organization\Domain\Exception\OrganizationAlreadyExistsException;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Port\OrganizationRepositoryInterface;
use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Domain\ValueObject\OrganizationId;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class DoctrineOrganizationRegistrar implements OrganizationRegistrarInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function register(
        string $organizationId,
        string $name,
        string $contactEmail,
        \DateTimeImmutable $registeredAt,
    ): void {
        if ($this->repository->existsByContactEmail($contactEmail)) {
            throw new OrganizationAlreadyExistsException($contactEmail);
        }

        $organization = Organization::register(
            new OrganizationId($organizationId),
            new OrganizationName($name),
            new OrganizationEmail($contactEmail),
            $registeredAt,
        );

        $this->repository->add($organization);

        foreach ($organization->pullEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
```

- [ ] **Step 3: Wire in `config/services/organization.yaml`**

Add after the existing `OrganizationCheckerInterface` binding:

```yaml
    App\Organization\Application\Contract\OrganizationRegistrarInterface:
        class: App\Organization\Infrastructure\Contract\DoctrineOrganizationRegistrar
```

- [ ] **Step 4: Run PHPStan**

```bash
make static-code-analysis 2>&1 | tail -5
```

Expected: `[OK] No errors`.

- [ ] **Step 5: Run deptrac**

```bash
make deptrac 2>&1 | tail -10
```

Expected: no violations.

- [ ] **Step 6: Commit**

```bash
git add src/Organization/Application/Contract/OrganizationRegistrarInterface.php \
        src/Organization/Infrastructure/Contract/DoctrineOrganizationRegistrar.php \
        config/services/organization.yaml
git commit -m "feat(organization): add OrganizationRegistrarInterface published contract"
```

---

## Task 3: Operator published contract + unit test

**Files:**
- Create: `src/Operator/Application/Contract/OwnerOperatorRegistrarInterface.php`
- Create: `src/Operator/Infrastructure/Contract/DoctrineOwnerOperatorRegistrar.php`
- Create: `tests/Security/Infrastructure/NullAccountRegistrar.php`
- Create: `tests/Operator/Infrastructure/Contract/DoctrineOwnerOperatorRegistrarTest.php`
- Modify: `config/services/operator.yaml`

**Interfaces:**
- Consumes: `AccountRegistrarInterface::register()`, `::setOrganizationId()`, `::unregister()` (Task 1); `OperatorRepositoryInterface::add()`, `::existsByEmail()` (already exists); `Operator` model (already exists); `OperatorRole::Owner` (already exists)
- Produces: `Operator\Application\Contract\OwnerOperatorRegistrarInterface::registerOwner(string, string, string, string, string, string, string, DateTimeImmutable): void`

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Operator\Infrastructure\Contract;

use App\Operator\Application\Contract\OwnerOperatorRegistrarInterface;
use App\Operator\Domain\Exception\OperatorAlreadyExistsException;
use App\Operator\Domain\Port\OperatorRepositoryInterface;
use App\Operator\Infrastructure\Contract\DoctrineOwnerOperatorRegistrar;
use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Tests\Operator\Infrastructure\Persistence\InMemory\InMemoryOperatorRepository;
use App\Tests\Security\Infrastructure\NullAccountRegistrar;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[Group('unit')]
final class DoctrineOwnerOperatorRegistrarTest extends TestCase
{
    private AccountRegistrarInterface&MockObject $accountRegistrar;
    private InMemoryOperatorRepository $operatorRepository;
    private DoctrineOwnerOperatorRegistrar $registrar;

    protected function setUp(): void
    {
        $this->accountRegistrar = $this->createMock(AccountRegistrarInterface::class);
        $this->operatorRepository = new InMemoryOperatorRepository();
        $this->registrar = new DoctrineOwnerOperatorRegistrar(
            $this->operatorRepository,
            $this->accountRegistrar,
            new NullLogger(),
        );
    }

    #[Test]
    public function itThrowsWhenEmailAlreadyExists(): void
    {
        $this->operatorRepository->add(new \App\Operator\Domain\Model\Operator(
            new \App\Shared\Domain\ValueObject\OperatorId('existing-op-id'),
            'Bob', 'Dupont', 'owner@hotel.com', '+33600000001',
            new \DateTimeImmutable(),
            new \App\Shared\Domain\ValueObject\OrganizationId('some-org-id'),
            \App\Operator\Domain\ValueObject\OperatorRole::Owner,
        ));

        $this->accountRegistrar->expects(self::never())->method('register');

        $this->expectException(OperatorAlreadyExistsException::class);
        $this->callRegisterOwner('owner@hotel.com');
    }

    #[Test]
    public function itCreatesKeycloakAccountSetsOrgIdAndSavesOperator(): void
    {
        $this->accountRegistrar->expects(self::once())
            ->method('register')
            ->with('op-uuid', 'operator', 'alice@hotel.com', 'Passw0rd!');

        $this->accountRegistrar->expects(self::once())
            ->method('setOrganizationId')
            ->with('op-uuid', 'operator', 'org-uuid');

        $this->accountRegistrar->expects(self::never())->method('unregister');

        $this->callRegisterOwner('alice@hotel.com');

        $operator = $this->operatorRepository->findByEmail('alice@hotel.com');
        self::assertNotNull($operator);
        self::assertSame('Alice', $operator->firstName);
        self::assertSame(\App\Operator\Domain\ValueObject\OperatorRole::Owner, $operator->role);
        self::assertSame('org-uuid', $operator->organizationId->value);
    }

    #[Test]
    public function itCompensatesKeycloakAccountWhenDbSaveFails(): void
    {
        $throwingRepository = $this->createMock(OperatorRepositoryInterface::class);
        $throwingRepository->method('existsByEmail')->willReturn(false);
        $throwingRepository->method('add')->willThrowException(new \RuntimeException('DB down'));

        $registrar = new DoctrineOwnerOperatorRegistrar(
            $throwingRepository,
            $this->accountRegistrar,
            new NullLogger(),
        );

        $this->accountRegistrar->expects(self::once())->method('register');
        $this->accountRegistrar->expects(self::once())->method('setOrganizationId');
        $this->accountRegistrar->expects(self::once())
            ->method('unregister')
            ->with('op-uuid', 'operator');

        $this->expectException(\RuntimeException::class);
        $registrar->registerOwner(
            'op-uuid', 'Alice', 'Martin', 'alice@hotel.com', '+33612345678',
            'Passw0rd!', 'org-uuid', new \DateTimeImmutable(),
        );
    }

    private function callRegisterOwner(string $email): void
    {
        $this->registrar->registerOwner(
            'op-uuid', 'Alice', 'Martin', $email, '+33612345678',
            'Passw0rd!', 'org-uuid', new \DateTimeImmutable('2026-06-28T10:00:00Z'),
        );
    }
}
```

- [ ] **Step 2: Create `NullAccountRegistrar` test double**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Security\Infrastructure;

use App\Security\Application\Contract\AccountRegistrarInterface;

final class NullAccountRegistrar implements AccountRegistrarInterface
{
    public function register(string $internalId, string $context, string $email, string $password): void
    {
    }

    public function unregister(string $internalId, string $context): void
    {
    }

    public function assignRole(string $internalId, string $context, string $roleName): void
    {
    }

    public function setOrganizationId(string $internalId, string $context, string $organizationId): void
    {
    }
}
```

- [ ] **Step 3: Add `findByEmail` helper to `InMemoryOperatorRepository`**

The test uses `findByEmail()`. Add it to `tests/Operator/Infrastructure/Persistence/InMemory/InMemoryOperatorRepository.php`:

```php
public function findByEmail(string $email): ?\App\Operator\Domain\Model\Operator
{
    foreach ($this->operators as $operator) {
        if (strtolower($operator->email) === strtolower($email)) {
            return $operator;
        }
    }

    return null;
}
```

- [ ] **Step 4: Run tests to confirm they fail**

```bash
make unit-test 2>&1 | grep -E "FAIL|Error|DoctrineOwnerOperatorRegistrar"
```

Expected: FAIL — `DoctrineOwnerOperatorRegistrar` not found.

- [ ] **Step 5: Create `OwnerOperatorRegistrarInterface`**

```php
<?php

declare(strict_types=1);

namespace App\Operator\Application\Contract;

interface OwnerOperatorRegistrarInterface
{
    public function registerOwner(
        string $operatorId,
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $password,
        string $organizationId,
        \DateTimeImmutable $registeredAt,
    ): void;
}
```

- [ ] **Step 6: Create `DoctrineOwnerOperatorRegistrar`**

```php
<?php

declare(strict_types=1);

namespace App\Operator\Infrastructure\Contract;

use App\Operator\Application\Contract\OwnerOperatorRegistrarInterface;
use App\Operator\Domain\Exception\OperatorAlreadyExistsException;
use App\Operator\Domain\Model\Operator;
use App\Operator\Domain\Port\OperatorRepositoryInterface;
use App\Operator\Domain\ValueObject\OperatorRole;
use App\Security\Application\Contract\AccountRegistrarInterface;
use App\Shared\Domain\ValueObject\OperatorId;
use App\Shared\Domain\ValueObject\OrganizationId;
use Psr\Log\LoggerInterface;

final readonly class DoctrineOwnerOperatorRegistrar implements OwnerOperatorRegistrarInterface
{
    public function __construct(
        private OperatorRepositoryInterface $operatorRepository,
        private AccountRegistrarInterface $accountRegistrar,
        private LoggerInterface $logger,
    ) {
    }

    public function registerOwner(
        string $operatorId,
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $password,
        string $organizationId,
        \DateTimeImmutable $registeredAt,
    ): void {
        if ($this->operatorRepository->existsByEmail($email)) {
            throw new OperatorAlreadyExistsException($email);
        }

        $this->accountRegistrar->register($operatorId, 'operator', $email, $password);
        $this->accountRegistrar->setOrganizationId($operatorId, 'operator', $organizationId);

        try {
            $this->operatorRepository->add(new Operator(
                new OperatorId($operatorId),
                $firstName,
                $lastName,
                $email,
                $phone,
                $registeredAt,
                new OrganizationId($organizationId),
                OperatorRole::Owner,
            ));
        } catch (\Throwable $e) {
            $this->logger->error('Owner operator DB save failed — compensating Keycloak', [
                'operator_id' => $operatorId,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            $this->accountRegistrar->unregister($operatorId, 'operator');
            throw $e;
        }
    }
}
```

- [ ] **Step 7: Wire in `config/services/operator.yaml`**

Add after the existing `ExternalAccountRegistrarInterface` binding:

```yaml
    App\Operator\Application\Contract\OwnerOperatorRegistrarInterface:
        class: App\Operator\Infrastructure\Contract\DoctrineOwnerOperatorRegistrar
```

- [ ] **Step 8: Run unit tests**

```bash
make unit-test 2>&1 | grep -E "OK|FAIL|ERROR"
```

Expected: `OK` — all tests green.

- [ ] **Step 9: Run PHPStan + deptrac**

```bash
make static-code-analysis 2>&1 | tail -5
make deptrac 2>&1 | tail -10
```

Expected: no errors, no violations.

- [ ] **Step 10: Commit**

```bash
git add src/Operator/Application/Contract/OwnerOperatorRegistrarInterface.php \
        src/Operator/Infrastructure/Contract/DoctrineOwnerOperatorRegistrar.php \
        tests/Security/Infrastructure/NullAccountRegistrar.php \
        tests/Operator/Infrastructure/Contract/DoctrineOwnerOperatorRegistrarTest.php \
        tests/Operator/Infrastructure/Persistence/InMemory/InMemoryOperatorRepository.php \
        config/services/operator.yaml
git commit -m "feat(operator): add OwnerOperatorRegistrarInterface published contract with Keycloak orchestration"
```

---

## Task 4: Onboarding handler + ports + unit test

**Files:**
- Create: `src/Onboarding/Application/Port/OrganizationRegistrarInterface.php`
- Create: `src/Onboarding/Application/Port/OwnerOperatorRegistrarInterface.php`
- Create: `src/Onboarding/Application/UseCase/OnboardOrganization/OnboardOrganizationCommand.php`
- Create: `src/Onboarding/Application/UseCase/OnboardOrganization/OnboardOrganizationHandler.php`
- Create: `tests/Onboarding/Application/UseCase/OnboardOrganization/OnboardOrganizationHandlerTest.php`

**Interfaces:**
- Consumes: `Organization\Application\Contract\OrganizationRegistrarInterface` (Task 2 — same signature); `Operator\Application\Contract\OwnerOperatorRegistrarInterface` (Task 3 — same signature)
- Produces: `OnboardOrganizationHandler::__invoke(OnboardOrganizationCommand): void`
- Produces: `OnboardOrganizationCommand` with fields: `organizationId: string`, `operatorId: string`, `organizationName: string`, `contactEmail: string`, `ownerFirstName: string`, `ownerLastName: string`, `ownerPhone: string`, `password: string`, `registeredAt: DateTimeImmutable`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Onboarding\Application\UseCase\OnboardOrganization;

use App\Onboarding\Application\Port\OrganizationRegistrarInterface;
use App\Onboarding\Application\Port\OwnerOperatorRegistrarInterface;
use App\Onboarding\Application\UseCase\OnboardOrganization\OnboardOrganizationCommand;
use App\Onboarding\Application\UseCase\OnboardOrganization\OnboardOrganizationHandler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class OnboardOrganizationHandlerTest extends TestCase
{
    private OrganizationRegistrarInterface&MockObject $organizationRegistrar;
    private OwnerOperatorRegistrarInterface&MockObject $ownerRegistrar;
    private OnboardOrganizationHandler $handler;

    protected function setUp(): void
    {
        $this->organizationRegistrar = $this->createMock(OrganizationRegistrarInterface::class);
        $this->ownerRegistrar = $this->createMock(OwnerOperatorRegistrarInterface::class);
        $this->handler = new OnboardOrganizationHandler(
            $this->organizationRegistrar,
            $this->ownerRegistrar,
        );
    }

    #[Test]
    public function itRegistersOrganizationThenOwner(): void
    {
        $at = new \DateTimeImmutable('2026-06-28T10:00:00Z');

        $this->organizationRegistrar->expects(self::once())
            ->method('register')
            ->with('org-uuid', 'Hôtel ABC', 'owner@hotel.com', $at);

        $this->ownerRegistrar->expects(self::once())
            ->method('registerOwner')
            ->with(
                'op-uuid', 'Alice', 'Martin', 'owner@hotel.com',
                '+33612345678', 'Passw0rd!', 'org-uuid', $at,
            );

        ($this->handler)(new OnboardOrganizationCommand(
            organizationId: 'org-uuid',
            operatorId: 'op-uuid',
            organizationName: 'Hôtel ABC',
            contactEmail: 'owner@hotel.com',
            ownerFirstName: 'Alice',
            ownerLastName: 'Martin',
            ownerPhone: '+33612345678',
            password: 'Passw0rd!',
            registeredAt: $at,
        ));
    }

    #[Test]
    public function itPropagatesExceptionFromOrganizationRegistrar(): void
    {
        $this->organizationRegistrar->method('register')
            ->willThrowException(new \RuntimeException('org conflict'));

        $this->ownerRegistrar->expects(self::never())->method('registerOwner');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('org conflict');

        ($this->handler)(new OnboardOrganizationCommand(
            organizationId: 'org-uuid',
            operatorId: 'op-uuid',
            organizationName: 'Hôtel ABC',
            contactEmail: 'owner@hotel.com',
            ownerFirstName: 'Alice',
            ownerLastName: 'Martin',
            ownerPhone: '+33612345678',
            password: 'Passw0rd!',
            registeredAt: new \DateTimeImmutable(),
        ));
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
make unit-test 2>&1 | grep -E "FAIL|Error|OnboardOrganization"
```

Expected: FAIL — classes not found.

- [ ] **Step 3: Create Onboarding ports**

`src/Onboarding/Application/Port/OrganizationRegistrarInterface.php`:
```php
<?php

declare(strict_types=1);

namespace App\Onboarding\Application\Port;

interface OrganizationRegistrarInterface
{
    public function register(
        string $organizationId,
        string $name,
        string $contactEmail,
        \DateTimeImmutable $registeredAt,
    ): void;
}
```

`src/Onboarding/Application/Port/OwnerOperatorRegistrarInterface.php`:
```php
<?php

declare(strict_types=1);

namespace App\Onboarding\Application\Port;

interface OwnerOperatorRegistrarInterface
{
    public function registerOwner(
        string $operatorId,
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $password,
        string $organizationId,
        \DateTimeImmutable $registeredAt,
    ): void;
}
```

- [ ] **Step 4: Create `OnboardOrganizationCommand`**

```php
<?php

declare(strict_types=1);

namespace App\Onboarding\Application\UseCase\OnboardOrganization;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class OnboardOrganizationCommand implements SyncCommandInterface
{
    public function __construct(
        public string $organizationId,
        public string $operatorId,
        public string $organizationName,
        public string $contactEmail,
        public string $ownerFirstName,
        public string $ownerLastName,
        public string $ownerPhone,
        public string $password,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
```

- [ ] **Step 5: Create `OnboardOrganizationHandler`**

```php
<?php

declare(strict_types=1);

namespace App\Onboarding\Application\UseCase\OnboardOrganization;

use App\Onboarding\Application\Port\OrganizationRegistrarInterface;
use App\Onboarding\Application\Port\OwnerOperatorRegistrarInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class OnboardOrganizationHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private OrganizationRegistrarInterface $organizationRegistrar,
        private OwnerOperatorRegistrarInterface $ownerRegistrar,
    ) {
    }

    public function __invoke(OnboardOrganizationCommand $command): void
    {
        $this->organizationRegistrar->register(
            $command->organizationId,
            $command->organizationName,
            $command->contactEmail,
            $command->registeredAt,
        );

        $this->ownerRegistrar->registerOwner(
            $command->operatorId,
            $command->ownerFirstName,
            $command->ownerLastName,
            $command->contactEmail,
            $command->ownerPhone,
            $command->password,
            $command->organizationId,
            $command->registeredAt,
        );
    }
}
```

- [ ] **Step 6: Run unit tests**

```bash
make unit-test 2>&1 | grep -E "OK|FAIL|ERROR"
```

Expected: `OK`.

- [ ] **Step 7: Commit**

```bash
git add src/Onboarding/ tests/Onboarding/
git commit -m "feat(onboarding): add OnboardOrganizationHandler with ports"
```

---

## Task 5: Onboarding infrastructure — adapters, config, deptrac

**Files:**
- Create: `src/Onboarding/Infrastructure/Adapter/OrganizationRegistrarAdapter.php`
- Create: `src/Onboarding/Infrastructure/Adapter/OwnerOperatorRegistrarAdapter.php`
- Create: `config/services/onboarding.yaml`
- Modify: `config/services.yaml`
- Modify: `deptrac-contexts.yaml`

**Interfaces:**
- Consumes: `Onboarding\Application\Port\OrganizationRegistrarInterface` (Task 4); `Onboarding\Application\Port\OwnerOperatorRegistrarInterface` (Task 4); `Organization\Application\Contract\OrganizationRegistrarInterface` (Task 2); `Operator\Application\Contract\OwnerOperatorRegistrarInterface` (Task 3)

- [ ] **Step 1: Create `OrganizationRegistrarAdapter`**

```php
<?php

declare(strict_types=1);

namespace App\Onboarding\Infrastructure\Adapter;

use App\Onboarding\Application\Port\OrganizationRegistrarInterface;
use App\Organization\Application\Contract\OrganizationRegistrarInterface as OrganizationContract;

final readonly class OrganizationRegistrarAdapter implements OrganizationRegistrarInterface
{
    public function __construct(private OrganizationContract $contract)
    {
    }

    public function register(
        string $organizationId,
        string $name,
        string $contactEmail,
        \DateTimeImmutable $registeredAt,
    ): void {
        $this->contract->register($organizationId, $name, $contactEmail, $registeredAt);
    }
}
```

- [ ] **Step 2: Create `OwnerOperatorRegistrarAdapter`**

```php
<?php

declare(strict_types=1);

namespace App\Onboarding\Infrastructure\Adapter;

use App\Onboarding\Application\Port\OwnerOperatorRegistrarInterface;
use App\Operator\Application\Contract\OwnerOperatorRegistrarInterface as OperatorContract;

final readonly class OwnerOperatorRegistrarAdapter implements OwnerOperatorRegistrarInterface
{
    public function __construct(private OperatorContract $contract)
    {
    }

    public function registerOwner(
        string $operatorId,
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $password,
        string $organizationId,
        \DateTimeImmutable $registeredAt,
    ): void {
        $this->contract->registerOwner(
            $operatorId, $firstName, $lastName, $email, $phone,
            $password, $organizationId, $registeredAt,
        );
    }
}
```

- [ ] **Step 3: Create `config/services/onboarding.yaml`**

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

    App\Onboarding\Application\:
        resource: '../../src/Onboarding/Application/'
        exclude:
            - '../../src/Onboarding/Application/**/*Command.php'

    App\Onboarding\Infrastructure\:
        resource: '../../src/Onboarding/Infrastructure/'

    App\Onboarding\UI\:
        resource: '../../src/Onboarding/UI/'
        exclude:
            - '../../src/Onboarding/UI/**/*Request.php'

    App\Onboarding\Application\Port\OrganizationRegistrarInterface:
        class: App\Onboarding\Infrastructure\Adapter\OrganizationRegistrarAdapter

    App\Onboarding\Application\Port\OwnerOperatorRegistrarInterface:
        class: App\Onboarding\Infrastructure\Adapter\OwnerOperatorRegistrarAdapter

when@test:
    services:
        App\Security\Application\Contract\AccountRegistrarInterface:
            class: App\Tests\Security\Infrastructure\NullAccountRegistrar
```

- [ ] **Step 4: Import in `config/services.yaml`**

Add `- { resource: './services/onboarding.yaml' }` after the `organization.yaml` line:

```yaml
    - { resource: './services/organization.yaml' }
    - { resource: './services/onboarding.yaml' }
```

- [ ] **Step 5: Update `deptrac-contexts.yaml` — add Onboarding layer**

Add in the `layers:` list (after the existing `Organization` / `OrganizationContract` blocks):

```yaml
        -
            name: Onboarding
            collectors:
                -
                    type: classLike
                    value: 'App\\Onboarding\\.*'
```

Add in the `ruleset:` section:

```yaml
        Onboarding:
            - OrganizationContract
            - OperatorContract
            - Shared
            - Vendor
```

- [ ] **Step 6: Run deptrac**

```bash
make deptrac 2>&1 | tail -15
```

Expected: no violations.

- [ ] **Step 7: Run PHPStan**

```bash
make static-code-analysis 2>&1 | tail -5
```

Expected: `[OK] No errors`.

- [ ] **Step 8: Commit**

```bash
git add src/Onboarding/Infrastructure/ \
        config/services/onboarding.yaml \
        config/services.yaml \
        deptrac-contexts.yaml
git commit -m "feat(onboarding): add infrastructure adapters, service config, and deptrac rules"
```

---

## Task 6: HTTP controller, security config, and functional tests

**Files:**
- Create: `src/Onboarding/UI/Http/OnboardOrganizationRequest.php`
- Create: `src/Onboarding/UI/Http/OnboardOrganizationController.php`
- Modify: `config/packages/security.yaml`
- Create: `tests/Onboarding/UI/Http/OnboardOrganizationControllerTest.php`

**Interfaces:**
- Consumes: `OnboardOrganizationCommand` (Task 4); `SyncCommandBusInterface` (already exists in Shared); `Symfony\Component\Uid\Uuid`

- [ ] **Step 1: Write the failing functional tests**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Onboarding\UI\Http;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class OnboardOrganizationControllerTest extends WebTestCase
{
    private const array VALID_PAYLOAD = [
        'organizationName' => 'Hôtel Bellevue',
        'contactEmail' => 'owner@bellevue.com',
        'ownerFirstName' => 'Alice',
        'ownerLastName' => 'Martin',
        'ownerPhone' => '+33612345678',
        'password' => 'SecurePass123!',
    ];

    #[Test]
    public function itOnboardsAndReturns201(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/onboarding',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{organizationId: string, operatorId: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $body['organizationId'],
        );
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $body['operatorId'],
        );
    }

    #[Test]
    public function itReturns409WhenEmailAlreadyUsed(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/onboarding',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'POST',
            uri: '/api/v1/onboarding',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns422WithViolationsWhenFieldIsMissing(): void
    {
        $client = static::createClient();

        $payload = self::VALID_PAYLOAD;
        unset($payload['contactEmail']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/onboarding',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{violations: list<array{field: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('contactEmail', $fields);
    }

    #[Test]
    public function itReturns422WhenEmailIsInvalid(): void
    {
        $client = static::createClient();

        $payload = array_merge(self::VALID_PAYLOAD, ['contactEmail' => 'not-an-email']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/onboarding',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenPasswordIsTooShort(): void
    {
        $client = static::createClient();

        $payload = array_merge(self::VALID_PAYLOAD, [
            'password' => 'short',
            'contactEmail' => 'short-pw@example.com',
        ]);

        $client->request(
            method: 'POST',
            uri: '/api/v1/onboarding',
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
    public function itReturns401WithoutAuthentication(): void
    {
        // Ensures the route is not accidentally restricted to authenticated users
        // (PUBLIC_ACCESS means we should NOT get a 401/403 — we get a real response)
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/onboarding',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        // A 201 (not 401 or 403) confirms PUBLIC_ACCESS is in effect
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
make functional-test 2>&1 | grep -E "FAIL|Error|OnboardOrganization" | head -20
```

Expected: FAIL — route `/api/v1/onboarding` not found (404).

- [ ] **Step 3: Create `OnboardOrganizationRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Onboarding\UI\Http;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class OnboardOrganizationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 255)]
        #[OA\Property(type: 'string', example: 'Hôtel Bellevue', maxLength: 255, minLength: 1)]
        public string $organizationName,

        #[Assert\NotBlank]
        #[Assert\Email]
        #[OA\Property(type: 'string', format: 'email', example: 'owner@bellevue.com')]
        public string $contactEmail,

        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        #[OA\Property(type: 'string', example: 'Alice', maxLength: 100, minLength: 1)]
        public string $ownerFirstName,

        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        #[OA\Property(type: 'string', example: 'Martin', maxLength: 100, minLength: 1)]
        public string $ownerLastName,

        #[Assert\NotBlank]
        #[Assert\Length(min: 5, max: 50)]
        #[OA\Property(type: 'string', example: '+33612345678', maxLength: 50, minLength: 5)]
        public string $ownerPhone,

        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 100)]
        #[OA\Property(type: 'string', example: 'MySecurePassword123!', minLength: 8, maxLength: 100)]
        public string $password,
    ) {
    }
}
```

- [ ] **Step 4: Create `OnboardOrganizationController`**

```php
<?php

declare(strict_types=1);

namespace App\Onboarding\UI\Http;

use App\Onboarding\Application\UseCase\OnboardOrganization\OnboardOrganizationCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final readonly class OnboardOrganizationController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route('/onboarding', name: 'onboarding_register', methods: ['POST'])]
    #[OA\Post(
        summary: 'Self-service hotel onboarding — creates an Organization and its owner Operator',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: OnboardOrganizationRequest::class)),
        ),
        tags: ['Onboarding'],
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Organization and owner Operator created (Organization status: pending)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'organizationId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'operatorId', type: 'string', format: 'uuid'),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_CONFLICT,
                description: 'Email already registered',
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
        #[MapRequestPayload(
            acceptFormat: 'json',
            validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
        )]
        OnboardOrganizationRequest $request,
    ): Response {
        $organizationId = Uuid::v4()->toString();
        $operatorId = Uuid::v4()->toString();

        $this->commandBus->execute(new OnboardOrganizationCommand(
            organizationId: $organizationId,
            operatorId: $operatorId,
            organizationName: $request->organizationName,
            contactEmail: $request->contactEmail,
            ownerFirstName: $request->ownerFirstName,
            ownerLastName: $request->ownerLastName,
            ownerPhone: $request->ownerPhone,
            password: $request->password,
            registeredAt: new \DateTimeImmutable(),
        ));

        return new JsonResponse([
            'organizationId' => $organizationId,
            'operatorId' => $operatorId,
        ], Response::HTTP_CREATED);
    }
}
```

- [ ] **Step 5: Add PUBLIC_ACCESS rule for the onboarding route**

In `config/packages/security.yaml`, add before the existing public routes (before `^/api/v1/search`):

```yaml
        - { path: ^/api/v1/onboarding$, roles: PUBLIC_ACCESS, methods: [POST] }
```

- [ ] **Step 6: Run functional tests**

```bash
make functional-test 2>&1 | grep -E "OK|FAIL|ERROR|OnboardOrganization"
```

Expected: `OK` — all functional tests green.

- [ ] **Step 7: Run full lint suite**

```bash
make lint 2>&1 | tail -20
```

Expected: no errors, no violations, no CS issues.

- [ ] **Step 8: Regenerate OpenAPI spec**

```bash
make openapi
```

Verify `openapi.yaml` now includes the `/onboarding` route.

- [ ] **Step 9: Commit**

```bash
git add src/Onboarding/UI/ \
        config/packages/security.yaml \
        tests/Onboarding/UI/ \
        openapi.yaml
git commit -m "feat(onboarding): add POST /api/v1/onboarding controller and functional tests"
```

---

## Self-Review

### Spec coverage

| Spec requirement | Task |
|-----------------|------|
| `POST /api/v1/onboarding` — public | Task 5 (security.yaml) + Task 6 (controller) |
| Organization created (status: pending) | Task 2 (`DoctrineOrganizationRegistrar` calls `Organization::register` which sets Pending) |
| Keycloak account created with password | Task 3 (`DoctrineOwnerOperatorRegistrar` calls `accountRegistrar.register`) |
| `organization_id` Keycloak attribute set | Task 1 (`setOrganizationId`) + Task 3 (called in registrar) |
| Operator saved with `role: Owner` and `organizationId` | Task 3 |
| 201 `{ organizationId, operatorId }` | Task 6 |
| 409 on duplicate email | Task 2 + Task 3 (throw existing domain exceptions, already mapped to 409) |
| 422 on validation errors | Task 6 (request DTO constraints) |
| Keycloak compensation if DB write fails | Task 3 (`unregister` in catch block) |
| deptrac rules for Onboarding | Task 5 |
| `NullAccountRegistrar` for test env | Task 3 + Task 5 (`when@test`) |
| OpenAPI spec updated | Task 6 (`make openapi`) |

### Placeholder scan

None found.

### Type consistency

- `OrganizationRegistrarInterface` (port) and `Organization\Application\Contract\OrganizationRegistrarInterface`: same 4-param signature — consistent.
- `OwnerOperatorRegistrarInterface` (port) and `Operator\Application\Contract\OwnerOperatorRegistrarInterface`: same 8-param signature — consistent.
- `OnboardOrganizationCommand` fields match `OnboardOrganizationHandler.__invoke()` usage — consistent.
- `contactEmail` is used as both org contact email and owner email throughout — explicitly designed, no inconsistency.

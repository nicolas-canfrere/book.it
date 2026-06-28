# SaaS — Fondation multi-tenant : Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Poser les fondations multi-tenant de book.it : bounded context `Organization` (tenant root), isolation DBAL via `TenantScopeAware`, propagation de l'`OrganizationId` depuis le JWT Keycloak, et mise à jour des repositories `Hotel` et `Operator` pour n'exposer que les données du tenant courant.

**Architecture:** Le contexte `Organization` est le tenant root. `OrganizationId` est propagé depuis le claim JWT `organization_id` (injecté par Keycloak) → `TenantContextMiddleware` → `TenantContext` → chaque repository DBAL via `applyTenantScope()`. Les contextes `Booker`, `Geo`, `Search` et `Notification` restent délibérément non-scopés.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL (pas d'ORM), PostgreSQL 16 avec isolation par schema, Keycloak Admin REST API, PHPUnit 13.

## Global Constraints

- DBAL uniquement — aucune utilisation de l'ORM Doctrine, pas de SQL Filters
- Chaque bounded context a sa propre connexion DBAL nommée (`hotelConnection`, `organizationConnection`, etc.)
- `SearchPathMiddleware` positionne le `search_path` PostgreSQL par connexion
- Tables des schémas existants : `hotel.hotel`, `operator.operator`, `organization.organizations`
- `final readonly class` pour tous les repositories et handlers — TenantContext injecté via paramètre constructeur non-promu
- TDD obligatoire : écrire le test en premier, vérifier qu'il échoue, implémenter, vérifier qu'il passe
- `make unit-test ARGS="--filter NomDeLaClasse"` pour cibler une classe de test spécifique
- `make functional-test ARGS="--filter NomDeLaClasse"` pour les tests fonctionnels
- `make lint` après chaque tâche pour vérifier PHPStan + deptrac

---

## File Structure

### Nouveaux fichiers

```
src/Shared/Domain/ValueObject/OrganizationId.php
src/Shared/Domain/Event/OrganizationRegistered.php
src/Shared/Domain/Event/OrganizationSuspended.php
src/Shared/Domain/Exception/TenantContextNotInitializedException.php
src/Shared/Application/TenantContext.php
src/Shared/Infrastructure/Persistence/TenantScopeAware.php
src/Shared/Infrastructure/Http/TenantContextMiddleware.php

src/Organization/Domain/Model/Organization.php
src/Organization/Domain/Model/OrganizationStatus.php          # enum
src/Organization/Domain/ValueObject/OrganizationName.php
src/Organization/Domain/ValueObject/OrganizationEmail.php
src/Organization/Domain/Port/OrganizationRepositoryInterface.php
src/Organization/Domain/Port/OrganizationIdGeneratorInterface.php
src/Organization/Domain/Exception/OrganizationNotFoundException.php
src/Organization/Domain/Exception/OrganizationAlreadyExistsException.php
src/Organization/Application/UseCase/RegisterOrganization/RegisterOrganizationCommand.php
src/Organization/Application/UseCase/RegisterOrganization/RegisterOrganizationHandler.php
src/Organization/Application/UseCase/ActivateOrganization/ActivateOrganizationCommand.php
src/Organization/Application/UseCase/ActivateOrganization/ActivateOrganizationHandler.php
src/Organization/Application/UseCase/SuspendOrganization/SuspendOrganizationCommand.php
src/Organization/Application/UseCase/SuspendOrganization/SuspendOrganizationHandler.php
src/Organization/Application/Contract/OrganizationCheckerInterface.php
src/Organization/Application/Contract/OrganizationView.php
src/Organization/Infrastructure/Persistence/Doctrine/OrganizationRepository.php
src/Organization/Infrastructure/Contract/DoctrineOrganizationChecker.php
src/Organization/Infrastructure/Service/OrganizationIdGenerator.php
src/Organization/Infrastructure/EventListener/OrganizationRegisteredListener.php
src/Organization/Infrastructure/EventListener/OrganizationSuspendedListener.php  # dans Security !
config/services/organization.yaml

src/Operator/Domain/ValueObject/OperatorRole.php               # enum

migrations/Version20260627000001.php

tests/Organization/Domain/Model/OrganizationTest.php
tests/Organization/Domain/ValueObject/OrganizationNameTest.php
tests/Organization/Domain/ValueObject/OrganizationEmailTest.php
tests/Organization/Application/UseCase/RegisterOrganization/RegisterOrganizationHandlerTest.php
tests/Organization/Application/UseCase/ActivateOrganization/ActivateOrganizationHandlerTest.php
tests/Organization/Application/UseCase/SuspendOrganization/SuspendOrganizationHandlerTest.php
tests/Organization/Infrastructure/Persistence/InMemory/InMemoryOrganizationRepository.php
tests/Hotel/Integration/HotelTenantIsolationTest.php
```

**Note :** Les listeners d'événements Organization vivent dans le contexte `Security` (ils parlent à Keycloak) :
```
src/Security/Infrastructure/EventListener/OrganizationRegisteredListener.php
src/Security/Infrastructure/EventListener/OrganizationSuspendedListener.php
```

### Fichiers modifiés

```
src/Hotel/Domain/Port/HotelPublicReaderInterface.php          # NEW — lecture publique non-scopée
src/Hotel/Infrastructure/Persistence/Doctrine/HotelPublicReader.php  # NEW — implémentation DBAL non-scopée
src/Hotel/Domain/Model/Hotel.php                              # + organizationId: OrganizationId
src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php  # + TenantContext, scoped get/list/add/save
src/Hotel/Application/UseCase/GetHotel/GetHotelQueryHandler.php  # HotelRepositoryInterface → HotelPublicReaderInterface
src/Hotel/Infrastructure/Contract/DoctrineHotelFinder.php     # HotelRepositoryInterface → HotelPublicReaderInterface
config/services/hotel.yaml                                     # + wire HotelPublicReaderInterface
src/Operator/Domain/Model/Operator.php                        # + organizationId + role
src/Operator/Infrastructure/Persistence/Doctrine/OperatorRepository.php  # + TenantContext
src/Security/Infrastructure/Keycloak/OperatorUser.php         # + organizationId: ?string
src/Security/Infrastructure/Keycloak/BearerTokenAuthenticator.php  # extrait organization_id du JWT
src/Security/Infrastructure/Keycloak/KeycloakHttpClientInterface.php  # + setUserAttribute, disableUser, revokeUserSessions
src/Security/Infrastructure/Keycloak/KeycloakHttpClient.php   # implémente les nouvelles méthodes
config/packages/doctrine.yaml                                  # + connexion organization
config/services/shared.yaml                                    # + TenantContext, TenantContextMiddleware
config/services/security.yaml                                  # + listeners
deptrac-contexts.yaml                                          # + Organization + OrganizationContract
config/services/exceptions.yaml                                # + Organization exceptions
tests/Shared/AuthenticatedWebTestCase.php                      # + organization_id dans JWT
```

---

### Task 1: OrganizationId VO + événements de domaine + TenantContextNotInitializedException

**Files:**
- Create: `src/Shared/Domain/ValueObject/OrganizationId.php`
- Create: `src/Shared/Domain/Event/OrganizationRegistered.php`
- Create: `src/Shared/Domain/Event/OrganizationSuspended.php`
- Create: `src/Shared/Domain/Exception/TenantContextNotInitializedException.php`
- Test: `tests/Shared/Domain/ValueObject/OrganizationIdTest.php`

**Interfaces:**
- Produces: `OrganizationId` (value object avec `string $value`, `equals()`, `__toString()`) — utilisé par toutes les tâches suivantes
- Produces: `OrganizationRegistered { organizationId: string, contactEmail: string, registeredAt: \DateTimeImmutable }` — écouté par Security (Task 13)
- Produces: `OrganizationSuspended { organizationId: string, suspendedAt: \DateTimeImmutable }` — écouté par Security (Task 13)

- [ ] **Step 1: Écrire le test OrganizationId**

```php
// tests/Shared/Domain/ValueObject/OrganizationIdTest.php
<?php
declare(strict_types=1);
namespace App\Tests\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\OrganizationId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class OrganizationIdTest extends TestCase
{
    #[Test]
    public function itWrapsAStringValue(): void
    {
        $id = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $id->value);
    }

    #[Test]
    public function itCastsToString(): void
    {
        $id = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $id);
    }

    #[Test]
    public function itComparesEquality(): void
    {
        $a = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $b = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $c = new OrganizationId('660e8400-e29b-41d4-a716-446655440000');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
```

- [ ] **Step 2: Vérifier que le test échoue**

```bash
make unit-test ARGS="--filter OrganizationIdTest"
```
Expected: FAIL — `Class "App\Shared\Domain\ValueObject\OrganizationId" not found`

- [ ] **Step 3: Créer OrganizationId**

```php
// src/Shared/Domain/ValueObject/OrganizationId.php
<?php
declare(strict_types=1);
namespace App\Shared\Domain\ValueObject;

final readonly class OrganizationId
{
    public function __construct(public string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(OrganizationId $other): bool
    {
        return $this->value === $other->value;
    }
}
```

- [ ] **Step 4: Créer les événements de domaine**

```php
// src/Shared/Domain/Event/OrganizationRegistered.php
<?php
declare(strict_types=1);
namespace App\Shared\Domain\Event;

final readonly class OrganizationRegistered
{
    public function __construct(
        public string $organizationId,
        public string $contactEmail,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
```

```php
// src/Shared/Domain/Event/OrganizationSuspended.php
<?php
declare(strict_types=1);
namespace App\Shared\Domain\Event;

final readonly class OrganizationSuspended
{
    public function __construct(
        public string $organizationId,
        public \DateTimeImmutable $suspendedAt,
    ) {
    }
}
```

- [ ] **Step 5: Créer TenantContextNotInitializedException**

```php
// src/Shared/Domain/Exception/TenantContextNotInitializedException.php
<?php
declare(strict_types=1);
namespace App\Shared\Domain\Exception;

final class TenantContextNotInitializedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('TenantContext has not been initialized for this request.');
    }
}
```

- [ ] **Step 6: Vérifier que les tests passent**

```bash
make unit-test ARGS="--filter OrganizationIdTest"
```
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add src/Shared/Domain/ValueObject/OrganizationId.php \
        src/Shared/Domain/Event/OrganizationRegistered.php \
        src/Shared/Domain/Event/OrganizationSuspended.php \
        src/Shared/Domain/Exception/TenantContextNotInitializedException.php \
        tests/Shared/Domain/ValueObject/OrganizationIdTest.php
git commit -m "feat(shared): add OrganizationId VO, domain events and TenantContextNotInitializedException"
```

---

### Task 2: TenantContext (Shared\Application)

**Files:**
- Create: `src/Shared/Application/TenantContext.php`
- Test: `tests/Shared/Application/TenantContextTest.php`

**Interfaces:**
- Consumes: `OrganizationId` (Task 1), `TenantContextNotInitializedException` (Task 1)
- Produces: `TenantContext::set(OrganizationId): void`, `TenantContext::getOrganizationId(): OrganizationId`, `TenantContext::isInitialized(): bool` — injecté dans TenantContextMiddleware (Task 11) et les repositories scopés (Tasks 8 et 9)

- [ ] **Step 1: Écrire les tests TenantContext**

```php
// tests/Shared/Application/TenantContextTest.php
<?php
declare(strict_types=1);
namespace App\Tests\Shared\Application;

use App\Shared\Application\TenantContext;
use App\Shared\Domain\Exception\TenantContextNotInitializedException;
use App\Shared\Domain\ValueObject\OrganizationId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class TenantContextTest extends TestCase
{
    #[Test]
    public function itIsNotInitializedByDefault(): void
    {
        $ctx = new TenantContext();
        self::assertFalse($ctx->isInitialized());
    }

    #[Test]
    public function itThrowsWhenAccessedBeforeInitialization(): void
    {
        $ctx = new TenantContext();
        $this->expectException(TenantContextNotInitializedException::class);
        $ctx->getOrganizationId();
    }

    #[Test]
    public function itReturnsOrganizationIdAfterSet(): void
    {
        $ctx = new TenantContext();
        $id = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $ctx->set($id);

        self::assertTrue($ctx->isInitialized());
        self::assertTrue($id->equals($ctx->getOrganizationId()));
    }
}
```

- [ ] **Step 2: Vérifier que les tests échouent**

```bash
make unit-test ARGS="--filter TenantContextTest"
```
Expected: FAIL

- [ ] **Step 3: Créer TenantContext**

```php
// src/Shared/Application/TenantContext.php
<?php
declare(strict_types=1);
namespace App\Shared\Application;

use App\Shared\Domain\Exception\TenantContextNotInitializedException;
use App\Shared\Domain\ValueObject\OrganizationId;

final class TenantContext
{
    private ?OrganizationId $organizationId = null;

    public function set(OrganizationId $id): void
    {
        $this->organizationId = $id;
    }

    public function getOrganizationId(): OrganizationId
    {
        if (null === $this->organizationId) {
            throw new TenantContextNotInitializedException();
        }

        return $this->organizationId;
    }

    public function isInitialized(): bool
    {
        return null !== $this->organizationId;
    }
}
```

**Important :** `TenantContext` n'est PAS `readonly` car `$organizationId` est mutable. Ne pas ajouter `readonly` ici.

- [ ] **Step 4: Vérifier que les tests passent**

```bash
make unit-test ARGS="--filter TenantContextTest"
```
Expected: PASS

- [ ] **Step 5: Enregistrer TenantContext comme service Symfony dans shared.yaml**

Dans `config/services/shared.yaml`, ajouter sous la section `services:` (après les entrées existantes) :

```yaml
    App\Shared\Application\TenantContext:
        shared: true
```

Le `shared: true` garantit un singleton par requête (comportement par défaut de Symfony, déclaration explicite pour la lisibilité).

- [ ] **Step 6: Commit**

```bash
git add src/Shared/Application/TenantContext.php \
        tests/Shared/Application/TenantContextTest.php \
        config/services/shared.yaml
git commit -m "feat(shared): add TenantContext service with OrganizationId lifecycle"
```

---

### Task 3: TenantScopeAware trait (Shared\Infrastructure\Persistence)

**Files:**
- Create: `src/Shared/Infrastructure/Persistence/TenantScopeAware.php`
- Test: `tests/Shared/Infrastructure/Persistence/TenantScopeAwareTest.php`

**Interfaces:**
- Consumes: `TenantContext` (Task 2), `OrganizationId` (Task 1)
- Produces: trait `TenantScopeAware` avec méthode `applyTenantScope(QueryBuilder $qb, string $tableAlias = 't'): QueryBuilder` — utilisé comme référence pour les repositories scopés (Tasks 8 et 9)

**Note architecture :** Les repositories `Hotel` et `Operator` sont des `final readonly class` — ils ne peuvent pas utiliser le trait directement car les propriétés de trait doivent être readonly dans ce contexte et l'initialisation par constructeur est complexe. Ces repositories implémentent `applyTenantScope()` inline avec la même signature. Le trait est la référence canonique et sera utilisé par les futurs repositories.

- [ ] **Step 1: Écrire le test TenantScopeAware**

```php
// tests/Shared/Infrastructure/Persistence/TenantScopeAwareTest.php
<?php
declare(strict_types=1);
namespace App\Tests\Shared\Infrastructure\Persistence;

use App\Shared\Application\TenantContext;
use App\Shared\Domain\ValueObject\OrganizationId;
use App\Shared\Infrastructure\Persistence\TenantScopeAware;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class TenantScopeAwareTest extends TestCase
{
    #[Test]
    public function itAddsOrganizationIdWhereClause(): void
    {
        $orgId = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $ctx = new TenantContext();
        $ctx->set($orgId);

        $consumer = new class($ctx) {
            use TenantScopeAware;

            public function __construct(private readonly TenantContext $tenantContext)
            {
            }

            public function expose(QueryBuilder $qb, string $alias): QueryBuilder
            {
                return $this->applyTenantScope($qb, $alias);
            }
        };

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();

        $result = $consumer->expose($qb, 'h');
        self::assertSame($qb, $result);
    }
}
```

- [ ] **Step 2: Vérifier que le test échoue**

```bash
make unit-test ARGS="--filter TenantScopeAwareTest"
```
Expected: FAIL

- [ ] **Step 3: Créer le trait TenantScopeAware**

```php
// src/Shared/Infrastructure/Persistence/TenantScopeAware.php
<?php
declare(strict_types=1);
namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\TenantContext;
use Doctrine\DBAL\Query\QueryBuilder;

trait TenantScopeAware
{
    private readonly TenantContext $tenantContext;

    private function applyTenantScope(QueryBuilder $qb, string $tableAlias = 't'): QueryBuilder
    {
        return $qb
            ->andWhere("{$tableAlias}.organization_id = :tenant_id")
            ->setParameter('tenant_id', $this->tenantContext->getOrganizationId()->value);
    }
}
```

- [ ] **Step 4: Vérifier que le test passe**

```bash
make unit-test ARGS="--filter TenantScopeAwareTest"
```
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Shared/Infrastructure/Persistence/TenantScopeAware.php \
        tests/Shared/Infrastructure/Persistence/TenantScopeAwareTest.php
git commit -m "feat(shared): add TenantScopeAware trait for DBAL repository scoping"
```

---

### Task 4: Organization — couche Domain

**Files:**
- Create: `src/Organization/Domain/Model/Organization.php`
- Create: `src/Organization/Domain/Model/OrganizationStatus.php`
- Create: `src/Organization/Domain/ValueObject/OrganizationName.php`
- Create: `src/Organization/Domain/ValueObject/OrganizationEmail.php`
- Create: `src/Organization/Domain/Port/OrganizationRepositoryInterface.php`
- Create: `src/Organization/Domain/Port/OrganizationIdGeneratorInterface.php`
- Create: `src/Organization/Domain/Exception/OrganizationNotFoundException.php`
- Create: `src/Organization/Domain/Exception/OrganizationAlreadyExistsException.php`
- Test: `tests/Organization/Domain/Model/OrganizationTest.php`
- Test: `tests/Organization/Domain/ValueObject/OrganizationNameTest.php`
- Test: `tests/Organization/Domain/ValueObject/OrganizationEmailTest.php`

**Interfaces:**
- Consumes: `OrganizationId`, `OrganizationRegistered`, `OrganizationSuspended` (Task 1)
- Produces: `Organization::register()` factory, `Organization::activate()`, `Organization::suspend()`, `Organization::pullEvents()` — utilisés par les handlers (Task 5)

- [ ] **Step 1: Écrire les tests OrganizationName et OrganizationEmail**

```php
// tests/Organization/Domain/ValueObject/OrganizationNameTest.php
<?php
declare(strict_types=1);
namespace App\Tests\Organization\Domain\ValueObject;

use App\Organization\Domain\ValueObject\OrganizationName;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class OrganizationNameTest extends TestCase
{
    #[Test]
    public function itAcceptsAValidName(): void
    {
        $name = new OrganizationName('Hôtel du Palais');
        self::assertSame('Hôtel du Palais', $name->value);
    }

    #[Test]
    public function itRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Organization name cannot be empty');
        new OrganizationName('');
    }

    #[Test]
    public function itRejectsANameExceeding255Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Organization name cannot exceed 255 characters');
        new OrganizationName(str_repeat('a', 256));
    }

    #[Test]
    public function itAccepts255CharacterName(): void
    {
        $name = new OrganizationName(str_repeat('a', 255));
        self::assertSame(255, strlen($name->value));
    }
}
```

```php
// tests/Organization/Domain/ValueObject/OrganizationEmailTest.php
<?php
declare(strict_types=1);
namespace App\Tests\Organization\Domain\ValueObject;

use App\Organization\Domain\ValueObject\OrganizationEmail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class OrganizationEmailTest extends TestCase
{
    #[Test]
    public function itAcceptsAValidEmail(): void
    {
        $email = new OrganizationEmail('contact@hotel.fr');
        self::assertSame('contact@hotel.fr', $email->value);
    }

    #[Test]
    public function itRejectsAnInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid organization email');
        new OrganizationEmail('not-an-email');
    }

    #[Test]
    public function itRejectsAnEmptyEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OrganizationEmail('');
    }
}
```

- [ ] **Step 2: Écrire le test Organization**

```php
// tests/Organization/Domain/Model/OrganizationTest.php
<?php
declare(strict_types=1);
namespace App\Tests\Organization\Domain\Model;

use App\Organization\Domain\Exception\OrganizationAlreadyExistsException;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationStatus;
use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Domain\Event\OrganizationRegistered;
use App\Shared\Domain\Event\OrganizationSuspended;
use App\Shared\Domain\ValueObject\OrganizationId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class OrganizationTest extends TestCase
{
    #[Test]
    public function itRegistersAnOrganizationWithPendingStatus(): void
    {
        $id = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $at = new \DateTimeImmutable('2026-06-27T10:00:00Z');

        $org = Organization::register($id, new OrganizationName('Hotel ABC'), new OrganizationEmail('abc@hotel.fr'), $at);

        self::assertSame(OrganizationStatus::Pending, $org->status);
        self::assertTrue($id->equals($org->id));

        $events = $org->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrganizationRegistered::class, $events[0]);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $events[0]->organizationId);
        self::assertSame('abc@hotel.fr', $events[0]->contactEmail);
    }

    #[Test]
    public function itActivatesAPendingOrganization(): void
    {
        $org = $this->aPendingOrganization();
        $org->pullEvents(); // vider les events du register

        $org->activate();

        self::assertSame(OrganizationStatus::Active, $org->status);
        self::assertEmpty($org->pullEvents());
    }

    #[Test]
    public function itSuspendsAnActiveOrganization(): void
    {
        $org = $this->anActiveOrganization();
        $org->pullEvents();

        $at = new \DateTimeImmutable('2026-06-28T00:00:00Z');
        $org->suspend($at);

        self::assertSame(OrganizationStatus::Suspended, $org->status);
        $events = $org->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrganizationSuspended::class, $events[0]);
    }

    private function aPendingOrganization(): Organization
    {
        return Organization::register(
            new OrganizationId('550e8400-e29b-41d4-a716-446655440000'),
            new OrganizationName('Test Hotel'),
            new OrganizationEmail('test@hotel.fr'),
            new \DateTimeImmutable('2026-06-27T10:00:00Z'),
        );
    }

    private function anActiveOrganization(): Organization
    {
        $org = $this->aPendingOrganization();
        $org->activate();

        return $org;
    }
}
```

- [ ] **Step 3: Vérifier que les tests échouent**

```bash
make unit-test ARGS="--filter OrganizationNameTest|OrganizationEmailTest|OrganizationTest"
```
Expected: FAIL

- [ ] **Step 4: Créer OrganizationStatus, OrganizationName, OrganizationEmail**

```php
// src/Organization/Domain/Model/OrganizationStatus.php
<?php
declare(strict_types=1);
namespace App\Organization\Domain\Model;

enum OrganizationStatus: string
{
    case Pending   = 'pending';
    case Active    = 'active';
    case Suspended = 'suspended';
}
```

```php
// src/Organization/Domain/ValueObject/OrganizationName.php
<?php
declare(strict_types=1);
namespace App\Organization\Domain\ValueObject;

final readonly class OrganizationName
{
    public function __construct(public string $value)
    {
        if ('' === trim($value)) {
            throw new \InvalidArgumentException('Organization name cannot be empty');
        }
        if (strlen($value) > 255) {
            throw new \InvalidArgumentException('Organization name cannot exceed 255 characters');
        }
    }
}
```

```php
// src/Organization/Domain/ValueObject/OrganizationEmail.php
<?php
declare(strict_types=1);
namespace App\Organization\Domain\ValueObject;

final readonly class OrganizationEmail
{
    public function __construct(public string $value)
    {
        if ('' === $value || false === filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid organization email');
        }
    }
}
```

- [ ] **Step 5: Créer Organization**

```php
// src/Organization/Domain/Model/Organization.php
<?php
declare(strict_types=1);
namespace App\Organization\Domain\Model;

use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Domain\Event\OrganizationRegistered;
use App\Shared\Domain\Event\OrganizationSuspended;
use App\Shared\Domain\ValueObject\OrganizationId;

final class Organization
{
    /** @var list<object> */
    private array $events = [];

    private function __construct(
        public readonly OrganizationId $id,
        public readonly OrganizationName $name,
        public readonly OrganizationEmail $contactEmail,
        public OrganizationStatus $status,
        public readonly \DateTimeImmutable $registeredAt,
    ) {
    }

    public static function register(
        OrganizationId $id,
        OrganizationName $name,
        OrganizationEmail $contactEmail,
        \DateTimeImmutable $registeredAt,
    ): self {
        $org = new self($id, $name, $contactEmail, OrganizationStatus::Pending, $registeredAt);
        $org->events[] = new OrganizationRegistered(
            organizationId: $id->value,
            contactEmail: $contactEmail->value,
            registeredAt: $registeredAt,
        );

        return $org;
    }

    public static function reconstitute(
        OrganizationId $id,
        OrganizationName $name,
        OrganizationEmail $contactEmail,
        OrganizationStatus $status,
        \DateTimeImmutable $registeredAt,
    ): self {
        return new self($id, $name, $contactEmail, $status, $registeredAt);
    }

    public function activate(): void
    {
        $this->status = OrganizationStatus::Active;
    }

    public function suspend(\DateTimeImmutable $at): void
    {
        $this->status = OrganizationStatus::Suspended;
        $this->events[] = new OrganizationSuspended(
            organizationId: $this->id->value,
            suspendedAt: $at,
        );
    }

    /** @return list<object> */
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
```

- [ ] **Step 6: Créer les ports et exceptions**

```php
// src/Organization/Domain/Port/OrganizationRepositoryInterface.php
<?php
declare(strict_types=1);
namespace App\Organization\Domain\Port;

use App\Organization\Domain\Model\Organization;
use App\Shared\Domain\ValueObject\OrganizationId;

interface OrganizationRepositoryInterface
{
    public function add(Organization $organization): void;
    public function save(Organization $organization): void;
    public function get(OrganizationId $id): ?Organization;
    public function existsByContactEmail(string $email): bool;
}
```

```php
// src/Organization/Domain/Port/OrganizationIdGeneratorInterface.php
<?php
declare(strict_types=1);
namespace App\Organization\Domain\Port;

use App\Shared\Domain\ValueObject\OrganizationId;

interface OrganizationIdGeneratorInterface
{
    public function generate(): OrganizationId;
}
```

```php
// src/Organization/Domain/Exception/OrganizationNotFoundException.php
<?php
declare(strict_types=1);
namespace App\Organization\Domain\Exception;

use App\Shared\Domain\ValueObject\OrganizationId;

final class OrganizationNotFoundException extends \DomainException
{
    public function __construct(OrganizationId $id)
    {
        parent::__construct("Organization '{$id->value}' not found.");
    }
}
```

```php
// src/Organization/Domain/Exception/OrganizationAlreadyExistsException.php
<?php
declare(strict_types=1);
namespace App\Organization\Domain\Exception;

final class OrganizationAlreadyExistsException extends \DomainException
{
    public function __construct(string $email)
    {
        parent::__construct("An organization with email '{$email}' already exists.");
    }
}
```

- [ ] **Step 7: Vérifier que les tests passent**

```bash
make unit-test ARGS="--filter OrganizationNameTest|OrganizationEmailTest|OrganizationTest"
```
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add src/Organization/ tests/Organization/Domain/
git commit -m "feat(organization): add domain layer — model, value objects, ports, exceptions"
```

---

### Task 5: Organization — couche Application (use cases + contract)

**Files:**
- Create: `src/Organization/Application/UseCase/RegisterOrganization/RegisterOrganizationCommand.php`
- Create: `src/Organization/Application/UseCase/RegisterOrganization/RegisterOrganizationHandler.php`
- Create: `src/Organization/Application/UseCase/ActivateOrganization/ActivateOrganizationCommand.php`
- Create: `src/Organization/Application/UseCase/ActivateOrganization/ActivateOrganizationHandler.php`
- Create: `src/Organization/Application/UseCase/SuspendOrganization/SuspendOrganizationCommand.php`
- Create: `src/Organization/Application/UseCase/SuspendOrganization/SuspendOrganizationHandler.php`
- Create: `src/Organization/Application/Contract/OrganizationCheckerInterface.php`
- Create: `src/Organization/Application/Contract/OrganizationView.php`
- Test: `tests/Organization/Application/UseCase/RegisterOrganization/RegisterOrganizationHandlerTest.php`
- Test: `tests/Organization/Application/UseCase/ActivateOrganization/ActivateOrganizationHandlerTest.php`
- Test: `tests/Organization/Application/UseCase/SuspendOrganization/SuspendOrganizationHandlerTest.php`
- Test (helper): `tests/Organization/Infrastructure/Persistence/InMemory/InMemoryOrganizationRepository.php`

**Interfaces:**
- Consumes: Domain layer (Task 4), `EventDispatcherInterface` (Symfony), InMemoryOrganizationRepository
- Produces: `RegisterOrganizationHandler`, `ActivateOrganizationHandler`, `SuspendOrganizationHandler`, `OrganizationCheckerInterface`, `OrganizationView`

- [ ] **Step 1: Créer InMemoryOrganizationRepository (helper de test)**

```php
// tests/Organization/Infrastructure/Persistence/InMemory/InMemoryOrganizationRepository.php
<?php
declare(strict_types=1);
namespace App\Tests\Organization\Infrastructure\Persistence\InMemory;

use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Port\OrganizationRepositoryInterface;
use App\Shared\Domain\ValueObject\OrganizationId;

final class InMemoryOrganizationRepository implements OrganizationRepositoryInterface
{
    /** @var array<string, Organization> */
    private array $store = [];

    public function add(Organization $organization): void
    {
        $this->store[$organization->id->value] = $organization;
    }

    public function save(Organization $organization): void
    {
        $this->store[$organization->id->value] = $organization;
    }

    public function get(OrganizationId $id): ?Organization
    {
        return $this->store[$id->value] ?? null;
    }

    public function existsByContactEmail(string $email): bool
    {
        foreach ($this->store as $org) {
            if ($org->contactEmail->value === $email) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 2: Écrire les tests des handlers**

```php
// tests/Organization/Application/UseCase/RegisterOrganization/RegisterOrganizationHandlerTest.php
<?php
declare(strict_types=1);
namespace App\Tests\Organization\Application\UseCase\RegisterOrganization;

use App\Organization\Application\UseCase\RegisterOrganization\RegisterOrganizationCommand;
use App\Organization\Application\UseCase\RegisterOrganization\RegisterOrganizationHandler;
use App\Organization\Domain\Exception\OrganizationAlreadyExistsException;
use App\Organization\Domain\Model\OrganizationStatus;
use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Domain\Event\OrganizationRegistered;
use App\Shared\Domain\ValueObject\OrganizationId;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Organization\Infrastructure\Persistence\InMemory\InMemoryOrganizationRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RegisterOrganizationHandlerTest extends TestCase
{
    private InMemoryOrganizationRepository $repository;
    private FakeEventDispatcher $dispatcher;
    private RegisterOrganizationHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryOrganizationRepository();
        $this->dispatcher = new FakeEventDispatcher();
        $this->handler = new RegisterOrganizationHandler($this->repository, $this->dispatcher);
    }

    #[Test]
    public function itRegistersAnOrganizationAndDispatchesEvent(): void
    {
        $id = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $at = new \DateTimeImmutable('2026-06-27T10:00:00Z');

        ($this->handler)(new RegisterOrganizationCommand(
            id: $id,
            name: new OrganizationName('Hotel ABC'),
            contactEmail: new OrganizationEmail('abc@hotel.fr'),
            registeredAt: $at,
        ));

        $saved = $this->repository->get($id);
        self::assertNotNull($saved);
        self::assertSame(OrganizationStatus::Pending, $saved->status);

        $event = $this->dispatcher->getLastDispatched();
        self::assertInstanceOf(OrganizationRegistered::class, $event);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $event->organizationId);
        self::assertSame('abc@hotel.fr', $event->contactEmail);
    }

    #[Test]
    public function itRejectsADuplicateEmail(): void
    {
        $id1 = new OrganizationId('550e8400-e29b-41d4-a716-446655440001');
        $id2 = new OrganizationId('550e8400-e29b-41d4-a716-446655440002');
        $at = new \DateTimeImmutable('2026-06-27T10:00:00Z');

        ($this->handler)(new RegisterOrganizationCommand($id1, new OrganizationName('Hotel A'), new OrganizationEmail('same@hotel.fr'), $at));

        $this->expectException(OrganizationAlreadyExistsException::class);
        ($this->handler)(new RegisterOrganizationCommand($id2, new OrganizationName('Hotel B'), new OrganizationEmail('same@hotel.fr'), $at));
    }
}
```

```php
// tests/Organization/Application/UseCase/ActivateOrganization/ActivateOrganizationHandlerTest.php
<?php
declare(strict_types=1);
namespace App\Tests\Organization\Application\UseCase\ActivateOrganization;

use App\Organization\Application\UseCase\ActivateOrganization\ActivateOrganizationCommand;
use App\Organization\Application\UseCase\ActivateOrganization\ActivateOrganizationHandler;
use App\Organization\Domain\Exception\OrganizationNotFoundException;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationStatus;
use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Domain\ValueObject\OrganizationId;
use App\Tests\Organization\Infrastructure\Persistence\InMemory\InMemoryOrganizationRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ActivateOrganizationHandlerTest extends TestCase
{
    #[Test]
    public function itActivatesAPendingOrganization(): void
    {
        $repo = new InMemoryOrganizationRepository();
        $id = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $repo->add(Organization::register($id, new OrganizationName('Hotel X'), new OrganizationEmail('x@hotel.fr'), new \DateTimeImmutable()));

        $handler = new ActivateOrganizationHandler($repo);
        ($handler)(new ActivateOrganizationCommand($id));

        $saved = $repo->get($id);
        self::assertNotNull($saved);
        self::assertSame(OrganizationStatus::Active, $saved->status);
    }

    #[Test]
    public function itThrowsWhenOrganizationNotFound(): void
    {
        $repo = new InMemoryOrganizationRepository();
        $handler = new ActivateOrganizationHandler($repo);

        $this->expectException(OrganizationNotFoundException::class);
        ($handler)(new ActivateOrganizationCommand(new OrganizationId('00000000-0000-0000-0000-000000000000')));
    }
}
```

```php
// tests/Organization/Application/UseCase/SuspendOrganization/SuspendOrganizationHandlerTest.php
<?php
declare(strict_types=1);
namespace App\Tests\Organization\Application\UseCase\SuspendOrganization;

use App\Organization\Application\UseCase\SuspendOrganization\SuspendOrganizationCommand;
use App\Organization\Application\UseCase\SuspendOrganization\SuspendOrganizationHandler;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationStatus;
use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Domain\Event\OrganizationSuspended;
use App\Shared\Domain\ValueObject\OrganizationId;
use App\Tests\Fake\FakeEventDispatcher;
use App\Tests\Organization\Infrastructure\Persistence\InMemory\InMemoryOrganizationRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SuspendOrganizationHandlerTest extends TestCase
{
    #[Test]
    public function itSuspendsAnOrganizationAndDispatchesEvent(): void
    {
        $repo = new InMemoryOrganizationRepository();
        $dispatcher = new FakeEventDispatcher();
        $id = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $org = Organization::register($id, new OrganizationName('Hotel Y'), new OrganizationEmail('y@hotel.fr'), new \DateTimeImmutable());
        $org->activate();
        $repo->add($org);

        $handler = new SuspendOrganizationHandler($repo, $dispatcher);
        $at = new \DateTimeImmutable('2026-06-28T00:00:00Z');
        ($handler)(new SuspendOrganizationCommand($id, $at));

        $saved = $repo->get($id);
        self::assertNotNull($saved);
        self::assertSame(OrganizationStatus::Suspended, $saved->status);

        $event = $dispatcher->getLastDispatched();
        self::assertInstanceOf(OrganizationSuspended::class, $event);
    }
}
```

- [ ] **Step 3: Vérifier que les tests échouent**

```bash
make unit-test ARGS="--filter RegisterOrganizationHandlerTest|ActivateOrganizationHandlerTest|SuspendOrganizationHandlerTest"
```
Expected: FAIL

- [ ] **Step 4: Créer les commands**

```php
// src/Organization/Application/UseCase/RegisterOrganization/RegisterOrganizationCommand.php
<?php
declare(strict_types=1);
namespace App\Organization\Application\UseCase\RegisterOrganization;

use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\OrganizationId;

final readonly class RegisterOrganizationCommand implements SyncCommandInterface
{
    public function __construct(
        public OrganizationId $id,
        public OrganizationName $name,
        public OrganizationEmail $contactEmail,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}
```

```php
// src/Organization/Application/UseCase/ActivateOrganization/ActivateOrganizationCommand.php
<?php
declare(strict_types=1);
namespace App\Organization\Application\UseCase\ActivateOrganization;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\OrganizationId;

final readonly class ActivateOrganizationCommand implements SyncCommandInterface
{
    public function __construct(public OrganizationId $id)
    {
    }
}
```

```php
// src/Organization/Application/UseCase/SuspendOrganization/SuspendOrganizationCommand.php
<?php
declare(strict_types=1);
namespace App\Organization\Application\UseCase\SuspendOrganization;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\OrganizationId;

final readonly class SuspendOrganizationCommand implements SyncCommandInterface
{
    public function __construct(
        public OrganizationId $id,
        public \DateTimeImmutable $suspendedAt,
    ) {
    }
}
```

- [ ] **Step 5: Créer les handlers**

```php
// src/Organization/Application/UseCase/RegisterOrganization/RegisterOrganizationHandler.php
<?php
declare(strict_types=1);
namespace App\Organization\Application\UseCase\RegisterOrganization;

use App\Organization\Domain\Exception\OrganizationAlreadyExistsException;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Port\OrganizationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class RegisterOrganizationHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(RegisterOrganizationCommand $command): void
    {
        if ($this->repository->existsByContactEmail($command->contactEmail->value)) {
            throw new OrganizationAlreadyExistsException($command->contactEmail->value);
        }

        $organization = Organization::register(
            $command->id,
            $command->name,
            $command->contactEmail,
            $command->registeredAt,
        );

        $this->repository->add($organization);

        foreach ($organization->pullEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
```

```php
// src/Organization/Application/UseCase/ActivateOrganization/ActivateOrganizationHandler.php
<?php
declare(strict_types=1);
namespace App\Organization\Application\UseCase\ActivateOrganization;

use App\Organization\Domain\Exception\OrganizationNotFoundException;
use App\Organization\Domain\Port\OrganizationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class ActivateOrganizationHandler implements SyncCommandHandlerInterface
{
    public function __construct(private OrganizationRepositoryInterface $repository)
    {
    }

    public function __invoke(ActivateOrganizationCommand $command): void
    {
        $organization = $this->repository->get($command->id);
        if (null === $organization) {
            throw new OrganizationNotFoundException($command->id);
        }

        $organization->activate();
        $this->repository->save($organization);
    }
}
```

```php
// src/Organization/Application/UseCase/SuspendOrganization/SuspendOrganizationHandler.php
<?php
declare(strict_types=1);
namespace App\Organization\Application\UseCase\SuspendOrganization;

use App\Organization\Domain\Exception\OrganizationNotFoundException;
use App\Organization\Domain\Port\OrganizationRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class SuspendOrganizationHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(SuspendOrganizationCommand $command): void
    {
        $organization = $this->repository->get($command->id);
        if (null === $organization) {
            throw new OrganizationNotFoundException($command->id);
        }

        $organization->suspend($command->suspendedAt);
        $this->repository->save($organization);

        foreach ($organization->pullEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
```

- [ ] **Step 6: Créer le contract OrganizationChecker**

```php
// src/Organization/Application/Contract/OrganizationCheckerInterface.php
<?php
declare(strict_types=1);
namespace App\Organization\Application\Contract;

interface OrganizationCheckerInterface
{
    public function exists(string $organizationId): bool;
    public function isActive(string $organizationId): bool;
}
```

```php
// src/Organization/Application/Contract/OrganizationView.php
<?php
declare(strict_types=1);
namespace App\Organization\Application\Contract;

final readonly class OrganizationView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $contactEmail,
        public string $status,
    ) {
    }
}
```

- [ ] **Step 7: Vérifier que les tests passent**

```bash
make unit-test ARGS="--filter RegisterOrganizationHandlerTest|ActivateOrganizationHandlerTest|SuspendOrganizationHandlerTest"
```
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add src/Organization/Application/ \
        tests/Organization/Application/ \
        tests/Organization/Infrastructure/Persistence/InMemory/InMemoryOrganizationRepository.php
git commit -m "feat(organization): add application layer — use cases, handlers, contract"
```

---

### Task 6: Organization — Infrastructure + service config + connexion DBAL

**Files:**
- Create: `src/Organization/Infrastructure/Persistence/Doctrine/OrganizationRepository.php`
- Create: `src/Organization/Infrastructure/Contract/DoctrineOrganizationChecker.php`
- Create: `src/Organization/Infrastructure/Service/OrganizationIdGenerator.php`
- Create: `config/services/organization.yaml`
- Modify: `config/packages/doctrine.yaml` — ajouter connexion `organization`

**Interfaces:**
- Consumes: `Organization::reconstitute()` (Task 4), `OrganizationStatus` enum (Task 4)
- Produces: `DoctrineOrganizationRepository` implémentant `OrganizationRepositoryInterface`, `DoctrineOrganizationChecker` implémentant `OrganizationCheckerInterface`, `OrganizationIdGenerator` implémentant `OrganizationIdGeneratorInterface`

- [ ] **Step 1: Ajouter la connexion DBAL `organization` dans `config/packages/doctrine.yaml`**

Ajouter après la dernière entrée de connexion (avant la section commentée ORM) :

```yaml
            organization:
                server_version: '16'
                url: '%env(resolve:BOOKIT_DATABASE_URL)%' # same DB, isolated via search_path=organization (set by SearchPathMiddleware)
                profiling_collect_backtrace: '%kernel.debug%'
```

- [ ] **Step 2: Créer OrganizationRepository**

```php
// src/Organization/Infrastructure/Persistence/Doctrine/OrganizationRepository.php
<?php
declare(strict_types=1);
namespace App\Organization\Infrastructure\Persistence\Doctrine;

use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationStatus;
use App\Organization\Domain\Port\OrganizationRepositoryInterface;
use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Domain\ValueObject\OrganizationId;
use Doctrine\DBAL\Connection;

final readonly class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function __construct(private Connection $organizationConnection)
    {
    }

    public function add(Organization $organization): void
    {
        $this->organizationConnection->insert('organizations', [
            'id'            => $organization->id->value,
            'name'          => $organization->name->value,
            'contact_email' => $organization->contactEmail->value,
            'status'        => $organization->status->value,
            'registered_at' => $organization->registeredAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function save(Organization $organization): void
    {
        $this->organizationConnection->update('organizations', [
            'status' => $organization->status->value,
        ], ['id' => $organization->id->value]);
    }

    public function get(OrganizationId $id): ?Organization
    {
        /** @var array{id: string, name: string, contact_email: string, status: string, registered_at: string}|false $row */
        $row = $this->organizationConnection->fetchAssociative(
            'SELECT id, name, contact_email, status, registered_at FROM organizations WHERE id = :id',
            ['id' => $id->value],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function existsByContactEmail(string $email): bool
    {
        /** @var int|string $count */
        $count = $this->organizationConnection->fetchOne(
            'SELECT COUNT(*) FROM organizations WHERE LOWER(contact_email) = LOWER(:email)',
            ['email' => $email],
        );

        return (int) $count > 0;
    }

    /**
     * @param array{id: string, name: string, contact_email: string, status: string, registered_at: string} $row
     */
    private function hydrate(array $row): Organization
    {
        return Organization::reconstitute(
            id: new OrganizationId($row['id']),
            name: new OrganizationName($row['name']),
            contactEmail: new OrganizationEmail($row['contact_email']),
            status: OrganizationStatus::from($row['status']),
            registeredAt: new \DateTimeImmutable($row['registered_at']),
        );
    }
}
```

- [ ] **Step 3: Créer DoctrineOrganizationChecker**

```php
// src/Organization/Infrastructure/Contract/DoctrineOrganizationChecker.php
<?php
declare(strict_types=1);
namespace App\Organization\Infrastructure\Contract;

use App\Organization\Application\Contract\OrganizationCheckerInterface;
use App\Organization\Domain\Model\OrganizationStatus;
use Doctrine\DBAL\Connection;

final readonly class DoctrineOrganizationChecker implements OrganizationCheckerInterface
{
    public function __construct(private Connection $organizationConnection)
    {
    }

    public function exists(string $organizationId): bool
    {
        /** @var int|string $count */
        $count = $this->organizationConnection->fetchOne(
            'SELECT COUNT(*) FROM organizations WHERE id = :id',
            ['id' => $organizationId],
        );

        return (int) $count > 0;
    }

    public function isActive(string $organizationId): bool
    {
        /** @var int|string $count */
        $count = $this->organizationConnection->fetchOne(
            'SELECT COUNT(*) FROM organizations WHERE id = :id AND status = :status',
            ['id' => $organizationId, 'status' => OrganizationStatus::Active->value],
        );

        return (int) $count > 0;
    }
}
```

- [ ] **Step 4: Créer OrganizationIdGenerator**

```php
// src/Organization/Infrastructure/Service/OrganizationIdGenerator.php
<?php
declare(strict_types=1);
namespace App\Organization\Infrastructure\Service;

use App\Organization\Domain\Port\OrganizationIdGeneratorInterface;
use App\Shared\Domain\ValueObject\OrganizationId;
use Symfony\Component\Uid\Uuid;

final readonly class OrganizationIdGenerator implements OrganizationIdGeneratorInterface
{
    public function generate(): OrganizationId
    {
        return new OrganizationId(Uuid::v4()->toRfc4122());
    }
}
```

- [ ] **Step 5: Créer config/services/organization.yaml**

```yaml
# config/services/organization.yaml
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
        App\Shared\Application\Bus\AsyncCommandHandlerInterface:
            tags:
                - {name: messenger.message_handler, bus: messenger.bus.default}

    App\Organization\Domain\:
        resource: '../../src/Organization/Domain/'
        exclude:
            - '../../src/Organization/Domain/Model/'
            - '../../src/Organization/Domain/ValueObject/'

    App\Organization\Application\:
        resource: '../../src/Organization/Application/'
        exclude:
            - '../../src/Organization/Application/**/*Command.php'
            - '../../src/Organization/Application/Contract/*View.php'

    App\Organization\Infrastructure\:
        resource: '../../src/Organization/Infrastructure/'

    App\Organization\Domain\Port\OrganizationRepositoryInterface:
        class: App\Organization\Infrastructure\Persistence\Doctrine\OrganizationRepository

    App\Organization\Domain\Port\OrganizationIdGeneratorInterface:
        class: App\Organization\Infrastructure\Service\OrganizationIdGenerator

    App\Organization\Application\Contract\OrganizationCheckerInterface:
        class: App\Organization\Infrastructure\Contract\DoctrineOrganizationChecker

    bookit.doctrine.middleware.search_path.organization:
        class: App\Shared\Infrastructure\Doctrine\SearchPathMiddleware
        arguments:
            $schema: 'organization'
        tags:
            - {name: doctrine.middleware, connection: organization}
```

- [ ] **Step 6: Enregistrer organization.yaml dans le kernel**

Dans `config/services.yaml` (ou le fichier qui importe les YAML de contextes), ajouter :
```yaml
- { resource: services/organization.yaml }
```

Vérifier d'abord comment les autres fichiers sont importés :
```bash
grep -r "services/hotel\|services/operator" config/
```

- [ ] **Step 7: Vérifier que le container compile**

```bash
make unit-test ARGS="--filter RegisterOrganizationHandlerTest"
```
Expected: PASS (le container doit compiler)

- [ ] **Step 8: Commit**

```bash
git add src/Organization/Infrastructure/ \
        config/services/organization.yaml \
        config/packages/doctrine.yaml
git commit -m "feat(organization): add infrastructure layer — DBAL repo, checker, id generator, service config"
```

---

### Task 7: Migration de base de données

**Files:**
- Create: `migrations/Version20260627000001.php`

**Interfaces:**
- Produces: schema `organization`, table `organization.organizations`, colonnes `organization_id` sur `hotel.hotel` et `operator.operator`, Organisation "Default" pour les données existantes

- [ ] **Step 1: Créer la migration**

```php
// migrations/Version20260627000001.php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'SaaS foundation: organization schema, organizations table, organization_id on hotel and operator';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS organization');

        $this->addSql('
            CREATE TABLE organization.organizations (
                id            UUID         PRIMARY KEY,
                name          VARCHAR(255) NOT NULL,
                contact_email VARCHAR(255) NOT NULL,
                status        VARCHAR(20)  NOT NULL DEFAULT \'pending\',
                registered_at TIMESTAMPTZ  NOT NULL
            )
        ');

        // Organisation de migration pour les données existantes
        $this->addSql("
            INSERT INTO organization.organizations (id, name, contact_email, status, registered_at)
            VALUES (
                '00000000-0000-0000-0000-000000000001',
                'Default Organization',
                'admin@book.it',
                'active',
                NOW()
            )
        ");

        // Ajouter organization_id sur hotel.hotel (NOT NULL avec valeur par défaut pour la migration atomique)
        $this->addSql("
            ALTER TABLE hotel.hotel
                ADD COLUMN organization_id UUID NOT NULL
                    DEFAULT '00000000-0000-0000-0000-000000000001'
                    REFERENCES organization.organizations(id)
        ");
        $this->addSql('ALTER TABLE hotel.hotel ALTER COLUMN organization_id DROP DEFAULT');

        // Ajouter organization_id et role sur operator.operator
        $this->addSql("
            ALTER TABLE operator.operator
                ADD COLUMN organization_id UUID NOT NULL
                    DEFAULT '00000000-0000-0000-0000-000000000001'
                    REFERENCES organization.organizations(id),
                ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'owner'
        ");
        $this->addSql('ALTER TABLE operator.operator ALTER COLUMN organization_id DROP DEFAULT');
        // Garder DEFAULT 'owner' sur role — nouveau opérateur sans rôle explicite = owner
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operator.operator DROP COLUMN role');
        $this->addSql('ALTER TABLE operator.operator DROP COLUMN organization_id');
        $this->addSql('ALTER TABLE hotel.hotel DROP COLUMN organization_id');
        $this->addSql('DROP TABLE organization.organizations');
        $this->addSql('DROP SCHEMA IF EXISTS organization');
    }
}
```

- [ ] **Step 2: Exécuter la migration**

```bash
make migrate
```
Expected: migration appliquée sans erreur.

- [ ] **Step 3: Commit**

```bash
git add migrations/Version20260627000001.php
git commit -m "feat(migration): add organization schema, organizations table, organization_id columns on hotel and operator"
```

---

### Task 8: Update Hotel — lecture publique/opérateur séparées, model, tenant scope

**Contexte :** `GET /api/v1/hotels/{id}` est public (visiteurs). La découverte publique passe par le contexte Search. `GET /api/v1/hotels` (liste) devient opérateur uniquement (scoped). Deux ports distincts : `HotelPublicReaderInterface` (non-scoped) et `HotelRepositoryInterface` (scoped).

```
HotelPublicReaderInterface  → get()           → DoctrineHotelPublicReader (pas de scope)
                                                 utilisé par : GetHotelQueryHandler, DoctrineHotelFinder

HotelRepositoryInterface    → get(), list(),  → HotelRepository (scoped, TenantContext)
                               add(), save(),    utilisé par : tous les command handlers,
                               existsByName()    ListHotelsQueryHandler (opérateur)
```

**Files:**
- Create: `src/Hotel/Domain/Port/HotelPublicReaderInterface.php`
- Create: `src/Hotel/Infrastructure/Persistence/Doctrine/HotelPublicReader.php`
- Modify: `src/Hotel/Domain/Model/Hotel.php` — + `organizationId: OrganizationId`
- Modify: `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php` — + TenantContext, scoped
- Modify: `src/Hotel/Application/UseCase/GetHotel/GetHotelQueryHandler.php` — `HotelRepositoryInterface` → `HotelPublicReaderInterface`
- Modify: `src/Hotel/Infrastructure/Contract/DoctrineHotelFinder.php` — `HotelRepositoryInterface` → `HotelPublicReaderInterface`
- Modify: `config/services/hotel.yaml` — wire `HotelPublicReaderInterface`
- Test: `tests/Hotel/Integration/HotelTenantIsolationTest.php`

**Interfaces:**
- Consumes: `OrganizationId` (Task 1), `TenantContext` (Task 2), migration appliquée (Task 7)
- Produces:
  - `HotelPublicReaderInterface::get(HotelId): ?Hotel` — non-scoped
  - `HotelRepositoryInterface::get/list/add/save/existsByNameAndAddress` — scoped
  - `Hotel::organizationId: OrganizationId` (readonly)

- [ ] **Step 1: Écrire le test d'isolation (HotelRepository scoped)**

```php
// tests/Hotel/Integration/HotelTenantIsolationTest.php
<?php
declare(strict_types=1);
namespace App\Tests\Hotel\Integration;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Infrastructure\Persistence\Doctrine\HotelPublicReader;
use App\Hotel\Infrastructure\Persistence\Doctrine\HotelRepository;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\OrganizationId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class HotelTenantIsolationTest extends KernelTestCase
{
    private HotelRepository $hotelRepository;
    private HotelPublicReader $publicReader;
    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->hotelRepository = $container->get(HotelRepository::class);
        $this->publicReader    = $container->get(HotelPublicReader::class);
        $this->tenantContext   = $container->get(TenantContext::class);
    }

    #[Test]
    public function scopedRepositoryOnlyReturnsOwnTenantHotels(): void
    {
        $org1 = new OrganizationId('aaaaaaaa-0000-0000-0000-000000000001');
        $org2 = new OrganizationId('bbbbbbbb-0000-0000-0000-000000000002');

        $this->tenantContext->set($org1);
        $this->hotelRepository->add($this->aHotel('11111111-0000-0000-0000-000000000001', 'Hotel Org 1', $org1));

        $this->tenantContext->set($org2);
        $this->hotelRepository->add($this->aHotel('22222222-0000-0000-0000-000000000002', 'Hotel Org 2', $org2));

        // org1 voit son hôtel
        $this->tenantContext->set($org1);
        $found = $this->hotelRepository->get(new HotelId('11111111-0000-0000-0000-000000000001'));
        self::assertNotNull($found);
        self::assertTrue($org1->equals($found->organizationId));

        // org1 ne voit PAS l'hôtel de org2
        $notFound = $this->hotelRepository->get(new HotelId('22222222-0000-0000-0000-000000000002'));
        self::assertNull($notFound);
    }

    #[Test]
    public function publicReaderReturnsAnyHotelRegardlessOfTenant(): void
    {
        $org1 = new OrganizationId('aaaaaaaa-0000-0000-0000-000000000001');
        $org2 = new OrganizationId('bbbbbbbb-0000-0000-0000-000000000002');

        $this->tenantContext->set($org1);
        $this->hotelRepository->add($this->aHotel('33333333-0000-0000-0000-000000000003', 'Hotel Public 1', $org1));

        $this->tenantContext->set($org2);
        $this->hotelRepository->add($this->aHotel('44444444-0000-0000-0000-000000000004', 'Hotel Public 2', $org2));

        // Sans TenantContext initialisé, le public reader retourne n'importe quel hôtel
        $hotel1 = $this->publicReader->get(new HotelId('33333333-0000-0000-0000-000000000003'));
        $hotel2 = $this->publicReader->get(new HotelId('44444444-0000-0000-0000-000000000004'));

        self::assertNotNull($hotel1);
        self::assertNotNull($hotel2);
        self::assertTrue($org1->equals($hotel1->organizationId));
        self::assertTrue($org2->equals($hotel2->organizationId));
    }

    private function aHotel(string $id, string $name, OrganizationId $orgId): Hotel
    {
        return new Hotel(
            new HotelId($id),
            $name,
            new Address('1 rue Test', '75001', 'Paris', 'FR'),
            new \DateTimeImmutable(),
            $orgId,
        );
    }
}
```

- [ ] **Step 2: Vérifier que le test échoue**

```bash
make unit-test ARGS="--filter HotelTenantIsolationTest"
```
Expected: FAIL — `Hotel::organizationId` n'existe pas encore

- [ ] **Step 3: Mettre à jour Hotel model**

```php
// src/Hotel/Domain/Model/Hotel.php
<?php
declare(strict_types=1);
namespace App\Hotel\Domain\Model;

use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\OrganizationId;

final readonly class Hotel
{
    /**
     * @param array<HotelAmenity> $amenities
     */
    public function __construct(
        public HotelId $id,
        public string $name,
        public Address $address,
        public \DateTimeImmutable $createdAt,
        public OrganizationId $organizationId,
        public ?StarRating $starRating = null,
        public array $amenities = [],
    ) {
    }

    public function withStarRating(?StarRating $starRating): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            address: $this->address,
            createdAt: $this->createdAt,
            organizationId: $this->organizationId,
            starRating: $starRating,
            amenities: $this->amenities,
        );
    }

    /**
     * @param array<HotelAmenity> $amenities
     */
    public function withAmenities(array $amenities): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            address: $this->address,
            createdAt: $this->createdAt,
            organizationId: $this->organizationId,
            starRating: $this->starRating,
            amenities: $amenities,
        );
    }
}
```

- [ ] **Step 4: Créer HotelPublicReaderInterface**

```php
// src/Hotel/Domain/Port/HotelPublicReaderInterface.php
<?php
declare(strict_types=1);
namespace App\Hotel\Domain\Port;

use App\Hotel\Domain\Model\Hotel;
use App\Shared\Domain\ValueObject\HotelId;

interface HotelPublicReaderInterface
{
    public function get(HotelId $id): ?Hotel;
}
```

- [ ] **Step 5: Créer DoctrineHotelPublicReader**

Requête DBAL directe, sans TenantContext — `final readonly class` car pas de mutation d'état.

```php
// src/Hotel/Infrastructure/Persistence/Doctrine/HotelPublicReader.php
<?php
declare(strict_types=1);
namespace App\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelPublicReaderInterface;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\OrganizationId;
use Doctrine\DBAL\Connection;

final readonly class HotelPublicReader implements HotelPublicReaderInterface
{
    public function __construct(private Connection $hotelConnection)
    {
    }

    public function get(HotelId $id): ?Hotel
    {
        /** @var array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, geo_place_id: string|null, created_at: string, stars: int|null, superior: string|bool, amenities: string, organization_id: string}|false $row */
        $row = $this->hotelConnection->fetchAssociative(
            'SELECT id, name, street_address, postal_code, city, country, geo_place_id, created_at, stars, superior, amenities, organization_id FROM hotel WHERE id = :id',
            ['id' => $id->value],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @param array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, geo_place_id: string|null, created_at: string, stars: int|null, superior: string|bool, amenities: string, organization_id: string} $row
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
            new OrganizationId($row['organization_id']),
            $starRating,
            $this->parseAmenities($row['amenities']),
        );
    }

    /** @return array<HotelAmenity> */
    private function parseAmenities(string $raw): array
    {
        if ('{}' === $raw) {
            return [];
        }

        preg_match_all('/"([^"]+)"|([^,{}]+)/', $raw, $matches);
        $values = array_map(
            static fn(string $quoted, string $plain): string => '' !== $quoted ? $quoted : $plain,
            $matches[1],
            $matches[2],
        );

        return array_map(HotelAmenity::from(...), $values);
    }
}
```

- [ ] **Step 6: Mettre à jour HotelRepository (scoped)**

`get()` et `list()` appliquent le scope — ils servent désormais exclusivement les opérateurs. Retirer `readonly` de la déclaration de classe pour permettre l'initialisation manuelle de `$tenantContext`.

```php
// src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php
<?php
declare(strict_types=1);
namespace App\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\OrganizationId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\String\Slugger\SluggerInterface;

final class HotelRepository implements HotelRepositoryInterface
{
    private readonly TenantContext $tenantContext;

    public function __construct(
        private readonly Connection $hotelConnection,
        private readonly SluggerInterface $slugger,
        TenantContext $tenantContext,
    ) {
        $this->tenantContext = $tenantContext;
    }

    public function add(Hotel $hotel): void
    {
        $this->hotelConnection->insert('hotel', [
            'id'              => $hotel->id->value,
            'name'            => $hotel->name,
            'street_address'  => $hotel->address->streetAddress,
            'postal_code'     => $hotel->address->postalCode,
            'city'            => $hotel->address->city,
            'country'         => $hotel->address->country,
            'geo_place_id'    => $hotel->address->geoPlaceId?->value,
            'search_key'      => $this->buildSearchKey($hotel->name, $hotel->address),
            'created_at'      => $hotel->createdAt->format('Y-m-d H:i:s'),
            'stars'           => $hotel->starRating?->stars,
            'superior'        => null !== $hotel->starRating ? $hotel->starRating->superior : false,
            'amenities'       => $this->serializeAmenities($hotel->amenities),
            'organization_id' => $hotel->organizationId->value,
        ], ['superior' => Types::BOOLEAN]);
    }

    public function save(Hotel $hotel): void
    {
        $this->hotelConnection->update('hotel', [
            'stars'    => $hotel->starRating?->stars,
            'superior' => null !== $hotel->starRating ? $hotel->starRating->superior : false,
            'amenities' => $this->serializeAmenities($hotel->amenities),
        ], [
            'id'              => $hotel->id->value,
            'organization_id' => $this->tenantContext->getOrganizationId()->value,
        ], ['superior' => Types::BOOLEAN]);
    }

    public function get(HotelId $id): ?Hotel
    {
        $qb = $this->hotelConnection->createQueryBuilder()
            ->select('h.id, h.name, h.street_address, h.postal_code, h.city, h.country, h.geo_place_id, h.created_at, h.stars, h.superior, h.amenities, h.organization_id')
            ->from('hotel', 'h')
            ->where('h.id = :id')
            ->setParameter('id', $id->value);

        $this->applyTenantScope($qb, 'h');

        /** @var array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, geo_place_id: string|null, created_at: string, stars: int|null, superior: string|bool, amenities: string, organization_id: string}|false $row */
        $row = $qb->fetchAssociative();

        return false === $row ? null : $this->hydrate($row);
    }

    public function existsByNameAndAddress(string $name, Address $address): bool
    {
        $qb = $this->hotelConnection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('hotel', 'h')
            ->where('h.search_key = :key')
            ->setParameter('key', $this->buildSearchKey($name, $address));

        $this->applyTenantScope($qb, 'h');

        /** @var int|string $count */
        $count = $qb->fetchOne();

        return (int) $count > 0;
    }

    /**
     * @param array<HotelAmenity>|null $amenities
     */
    public function list(int $page, int $limit, ?string $city, ?string $country, ?int $minStars = null, ?array $amenities = null): HotelPage
    {
        $qb = $this->hotelConnection->createQueryBuilder()
            ->select('h.id, h.name, h.street_address, h.postal_code, h.city, h.country, h.geo_place_id, h.created_at, h.stars, h.superior, h.amenities, h.organization_id')
            ->from('hotel', 'h');

        $this->applyTenantScope($qb, 'h');

        if (null !== $city) {
            $qb->andWhere('h.city = :city')->setParameter('city', $city);
        }
        if (null !== $country) {
            $qb->andWhere('h.country = :country')->setParameter('country', $country);
        }
        if (null !== $minStars) {
            $qb->andWhere('h.stars >= :minStars')->setParameter('minStars', $minStars);
        }
        if (null !== $amenities && [] !== $amenities) {
            $qb->andWhere('h.amenities @> :amenities::text[]')
               ->setParameter('amenities', $this->serializeAmenities($amenities));
        }

        $countQb = clone $qb;
        $countQb->select('COUNT(*)');
        /** @var int|string $count */
        $count    = $countQb->fetchOne();
        $total    = (int) $count;

        $qb->orderBy('h.name', 'ASC')
           ->setMaxResults($limit)
           ->setFirstResult(($page - 1) * $limit);

        /** @var list<array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, geo_place_id: string|null, created_at: string, stars: int|null, superior: string|bool, amenities: string, organization_id: string}> $rows */
        $rows = $qb->fetchAllAssociative();

        return new HotelPage(array_map($this->hydrate(...), $rows), $total);
    }

    /**
     * @param array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, geo_place_id: string|null, created_at: string, stars: int|null, superior: string|bool, amenities: string, organization_id: string} $row
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
            new OrganizationId($row['organization_id']),
            $starRating,
            $this->parseAmenities($row['amenities']),
        );
    }

    private function applyTenantScope(QueryBuilder $qb, string $tableAlias = 't'): void
    {
        $qb->andWhere("{$tableAlias}.organization_id = :tenant_id")
           ->setParameter('tenant_id', $this->tenantContext->getOrganizationId()->value);
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

    /** @return array<HotelAmenity> */
    private function parseAmenities(string $raw): array
    {
        if ('{}' === $raw) {
            return [];
        }

        preg_match_all('/"([^"]+)"|([^,{}]+)/', $raw, $matches);
        $values = array_map(
            static fn(string $quoted, string $plain): string => '' !== $quoted ? $quoted : $plain,
            $matches[1],
            $matches[2],
        );

        return array_map(HotelAmenity::from(...), $values);
    }

    /** @param array<HotelAmenity> $amenities */
    private function serializeAmenities(array $amenities): string
    {
        if ([] === $amenities) {
            return '{}';
        }

        return '{' . implode(',', array_map(static fn(HotelAmenity $a) => $a->value, $amenities)) . '}';
    }
}
```

- [ ] **Step 7: Recâbler GetHotelQueryHandler sur HotelPublicReaderInterface**

```php
// src/Hotel/Application/UseCase/GetHotel/GetHotelQueryHandler.php
<?php
declare(strict_types=1);
namespace App\Hotel\Application\UseCase\GetHotel;

use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelPublicReaderInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetHotelQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(private HotelPublicReaderInterface $hotelPublicReader)
    {
    }

    public function __invoke(GetHotelQuery $query): ?Hotel
    {
        return $this->hotelPublicReader->get($query->hotelId);
    }
}
```

- [ ] **Step 8: Recâbler DoctrineHotelFinder sur HotelPublicReaderInterface**

`DoctrineHotelFinder` est utilisé cross-context (Room, Reservation, Pricing) — il doit rester non-scoped.

```php
// src/Hotel/Infrastructure/Contract/DoctrineHotelFinder.php
<?php
declare(strict_types=1);
namespace App\Hotel\Infrastructure\Contract;

use App\Hotel\Application\Contract\HotelFinderInterface;
use App\Hotel\Application\Contract\HotelView;
use App\Hotel\Domain\Port\HotelPublicReaderInterface;
use App\Shared\Domain\ValueObject\HotelId;

final readonly class DoctrineHotelFinder implements HotelFinderInterface
{
    public function __construct(private HotelPublicReaderInterface $hotelPublicReader)
    {
    }

    public function find(HotelId $hotelId): ?HotelView
    {
        $hotel = $this->hotelPublicReader->get($hotelId);

        if (null === $hotel) {
            return null;
        }

        return new HotelView(id: $hotel->id->value);
    }
}
```

- [ ] **Step 9: Ajouter le wiring dans config/services/hotel.yaml**

Ajouter sous les interfaces déjà câblées :

```yaml
    App\Hotel\Domain\Port\HotelPublicReaderInterface:
        class: App\Hotel\Infrastructure\Persistence\Doctrine\HotelPublicReader
```

- [ ] **Step 10: Corriger les tests unitaires Hotel existants**

`Hotel` a maintenant `organizationId` comme paramètre obligatoire. Chercher toutes les constructions :

```bash
grep -rn "new Hotel(" tests/ src/ --include="*.php"
```

Ajouter `organizationId: new OrganizationId('00000000-0000-0000-0000-000000000001')` partout. Penser à importer `App\Shared\Domain\ValueObject\OrganizationId`.

- [ ] **Step 11: Vérifier que tous les tests Hotel passent**

```bash
make unit-test ARGS="--filter Hotel"
```
Expected: PASS

- [ ] **Step 12: Commit**

```bash
git add src/Hotel/ \
        config/services/hotel.yaml \
        tests/Hotel/
git commit -m "feat(hotel): add organizationId, HotelPublicReaderInterface for public reads, scoped HotelRepository for operators"
```

---

### Task 9: OperatorRole enum + update Operator model et repository

**Files:**
- Create: `src/Operator/Domain/ValueObject/OperatorRole.php`
- Modify: `src/Operator/Domain/Model/Operator.php`
- Modify: `src/Operator/Infrastructure/Persistence/Doctrine/OperatorRepository.php`

**Interfaces:**
- Consumes: `OrganizationId` (Task 1), `TenantContext` (Task 2), migration appliquée (Task 7)
- Produces: `Operator::organizationId: OrganizationId`, `Operator::role: OperatorRole`, `OperatorRepository` filtré par tenant

- [ ] **Step 1: Créer OperatorRole**

```php
// src/Operator/Domain/ValueObject/OperatorRole.php
<?php
declare(strict_types=1);
namespace App\Operator\Domain\ValueObject;

enum OperatorRole: string
{
    case Owner   = 'owner';
    case Manager = 'manager';
    case Staff   = 'staff';
}
```

- [ ] **Step 2: Mettre à jour Operator model**

```php
// src/Operator/Domain/Model/Operator.php
<?php
declare(strict_types=1);
namespace App\Operator\Domain\Model;

use App\Operator\Domain\ValueObject\OperatorRole;
use App\Shared\Domain\ValueObject\OperatorId;
use App\Shared\Domain\ValueObject\OrganizationId;

final readonly class Operator
{
    public function __construct(
        public OperatorId $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public \DateTimeImmutable $registeredAt,
        public OrganizationId $organizationId,
        public OperatorRole $role = OperatorRole::Owner,
    ) {
    }
}
```

- [ ] **Step 3: Mettre à jour OperatorRepository**

```php
// src/Operator/Infrastructure/Persistence/Doctrine/OperatorRepository.php
<?php
declare(strict_types=1);
namespace App\Operator\Infrastructure\Persistence\Doctrine;

use App\Operator\Domain\Model\Operator;
use App\Operator\Domain\Port\OperatorRepositoryInterface;
use App\Operator\Domain\ValueObject\OperatorRole;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\ValueObject\OrganizationId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

final class OperatorRepository implements OperatorRepositoryInterface
{
    private readonly TenantContext $tenantContext;

    public function __construct(
        private readonly Connection $operatorConnection,
        TenantContext $tenantContext,
    ) {
        $this->tenantContext = $tenantContext;
    }

    public function add(Operator $operator): void
    {
        $this->operatorConnection->insert('operator', [
            'id'              => $operator->id->value,
            'first_name'      => $operator->firstName,
            'last_name'       => $operator->lastName,
            'email'           => $operator->email,
            'phone'           => $operator->phone,
            'registered_at'   => $operator->registeredAt->format('Y-m-d H:i:s'),
            'organization_id' => $operator->organizationId->value,
            'role'            => $operator->role->value,
        ]);
    }

    public function existsByEmail(string $email): bool
    {
        $qb = $this->operatorConnection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('operator', 'o')
            ->where('LOWER(o.email) = LOWER(:email)')
            ->setParameter('email', $email);

        $this->applyTenantScope($qb, 'o');

        /** @var int|string $count */
        $count = $qb->fetchOne();

        return (int) $count > 0;
    }

    private function applyTenantScope(QueryBuilder $qb, string $tableAlias = 't'): void
    {
        $qb
            ->andWhere("{$tableAlias}.organization_id = :tenant_id")
            ->setParameter('tenant_id', $this->tenantContext->getOrganizationId()->value);
    }
}
```

**Note :** Retirer `readonly` de la déclaration de classe (même raison que HotelRepository — propriété assignée manuellement).

- [ ] **Step 4: Corriger les tests Operator existants**

Chercher les constructions `new Operator(...)` :
```bash
grep -r "new Operator(" tests/ src/ --include="*.php"
```

Ajouter `organizationId: new OrganizationId('00000000-0000-0000-0000-000000000001')` partout où un Operator est construit dans les tests.

- [ ] **Step 5: Vérifier les tests**

```bash
make unit-test ARGS="--filter Operator"
```
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Operator/ tests/
git commit -m "feat(operator): add organizationId and OperatorRole to model, apply tenant scope in repository"
```

---

### Task 10: OperatorUser porte organizationId + BearerTokenAuthenticator extrait le claim JWT

**Files:**
- Modify: `src/Security/Infrastructure/Keycloak/OperatorUser.php`
- Modify: `src/Security/Infrastructure/Keycloak/BearerTokenAuthenticator.php`
- Modify: `tests/Shared/AuthenticatedWebTestCase.php`

**Interfaces:**
- Produces: `OperatorUser::organizationId: ?string` — lu par TenantContextMiddleware (Task 11)

- [ ] **Step 1: Mettre à jour OperatorUser**

```php
// src/Security/Infrastructure/Keycloak/OperatorUser.php
<?php
declare(strict_types=1);
namespace App\Security\Infrastructure\Keycloak;

use Symfony\Component\Security\Core\User\UserInterface;

final class OperatorUser implements UserInterface
{
    /** @var list<string> */
    private array $roles = [];

    /**
     * @param list<string> $roles
     */
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        array $roles = [],
        public readonly ?string $organizationId = null,
    ) {
        $this->setRoles($roles);
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

    /**
     * @param list<string> $roles
     */
    private function setRoles(array $roles): void
    {
        $roles = array_merge($roles, ['ROLE_OPERATOR']);
        $this->roles = array_values(array_unique($roles));
    }
}
```

- [ ] **Step 2: Mettre à jour BearerTokenAuthenticator**

Localiser la construction `new OperatorUser(...)` dans `authenticate()` et y ajouter l'extraction du claim `organization_id` :

```php
// Dans BearerTokenAuthenticator::authenticate(), remplacer la construction de OperatorUser :

$organizationId = null;
$rawOrgId = $payload->organization_id ?? null;
if (\is_scalar($rawOrgId)) {
    $organizationId = (string) $rawOrgId;
}

$user = new OperatorUser($operator->id, $operator->email, $roles, $organizationId);
```

- [ ] **Step 3: Mettre à jour AuthenticatedWebTestCase**

Ajouter `organization_id` dans le payload JWT de test. La valeur `'00000000-0000-0000-0000-000000000001'` correspond à l'Organisation Default de la migration.

```php
// Dans createAuthenticatedClient(), remplacer le tableau JWT :
$token = JWT::encode([
    'sub' => 'test-operator',
    'iss' => 'http://localhost:9000/realms/bookit',
    'iat' => time(),
    'exp' => time() + 3600,
    'realm_access' => ['roles' => $roles],
    'organization_id' => '00000000-0000-0000-0000-000000000001',
], self::$privateKey, 'RS256');
```

- [ ] **Step 4: Vérifier les tests**

```bash
make unit-test ARGS="--filter BearerAuth|OperatorUser"
```
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Security/Infrastructure/Keycloak/OperatorUser.php \
        src/Security/Infrastructure/Keycloak/BearerTokenAuthenticator.php \
        tests/Shared/AuthenticatedWebTestCase.php
git commit -m "feat(security): OperatorUser carries organizationId from JWT claim"
```

---

### Task 11: TenantContextMiddleware

**Files:**
- Create: `src/Shared/Infrastructure/Http/TenantContextMiddleware.php`
- Modify: `config/services/shared.yaml` — enregistrer comme subscriber

**Interfaces:**
- Consumes: `TenantContext` (Task 2), `OperatorUser::organizationId` (Task 10), `OrganizationId` (Task 1)
- Produces: `TenantContext` initialisé avant chaque requête d'un opérateur authentifié

- [ ] **Step 1: Créer TenantContextMiddleware**

```php
// src/Shared/Infrastructure/Http/TenantContextMiddleware.php
<?php
declare(strict_types=1);
namespace App\Shared\Infrastructure\Http;

use App\Security\Infrastructure\Keycloak\OperatorUser;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\ValueObject\OrganizationId;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class TenantContextMiddleware implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private TenantContext $tenantContext,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof OperatorUser) {
            return;
        }

        if (null !== $user->organizationId) {
            $this->tenantContext->set(new OrganizationId($user->organizationId));
        }
    }
}
```

**Note architecture :** `TenantContextMiddleware` importe `OperatorUser` depuis `Security\Infrastructure`. Cette dépendance est acceptable dans `Shared\Infrastructure` car `OperatorUser` est une infrastructure Symfony Security, pas un concept domain. Si deptrac se plaint, ajouter l'exception dans `deptrac.yaml`.

- [ ] **Step 2: Enregistrer dans config/services/shared.yaml**

Ajouter sous `services:` :

```yaml
    App\Shared\Infrastructure\Http\TenantContextMiddleware:
        tags:
            - {name: kernel.event_subscriber}
```

- [ ] **Step 3: Vérifier que le container compile et les tests passent**

```bash
make unit-test
```
Expected: PASS (le middleware est enregistré, le container compile)

- [ ] **Step 4: Commit**

```bash
git add src/Shared/Infrastructure/Http/TenantContextMiddleware.php \
        config/services/shared.yaml
git commit -m "feat(shared): add TenantContextMiddleware — sets OrganizationId from JWT claim on each request"
```

---

### Task 12: Keycloak HTTP client — nouvelles méthodes admin

**Files:**
- Modify: `src/Security/Infrastructure/Keycloak/KeycloakHttpClientInterface.php`
- Modify: `src/Security/Infrastructure/Keycloak/KeycloakHttpClient.php`

**Interfaces:**
- Produces: `setUserAttribute(string $keycloakId, string $attribute, string $value): void`, `disableUser(string $keycloakId): void`, `revokeUserSessions(string $keycloakId): void` — utilisés par les listeners (Task 13)

- [ ] **Step 1: Étendre l'interface**

```php
// Ajouter à KeycloakHttpClientInterface :
public function setUserAttribute(string $keycloakId, string $attribute, string $value): void;
public function disableUser(string $keycloakId): void;
public function revokeUserSessions(string $keycloakId): void;
```

- [ ] **Step 2: Implémenter dans KeycloakHttpClient**

Ajouter les trois méthodes à la suite des méthodes existantes :

```php
public function setUserAttribute(string $keycloakId, string $attribute, string $value): void
{
    $this->request('PUT', "/admin/realms/{$this->keycloakRealm}/users/{$keycloakId}", [
        'json' => [
            'attributes' => [$attribute => [$value]],
        ],
    ]);
}

public function disableUser(string $keycloakId): void
{
    $this->request('PUT', "/admin/realms/{$this->keycloakRealm}/users/{$keycloakId}", [
        'json' => ['enabled' => false],
    ]);
}

public function revokeUserSessions(string $keycloakId): void
{
    $this->request('DELETE', "/admin/realms/{$this->keycloakRealm}/users/{$keycloakId}/sessions");
}
```

- [ ] **Step 3: Vérifier que PHPStan passe**

```bash
make static-code-analysis
```
Expected: no errors

- [ ] **Step 4: Commit**

```bash
git add src/Security/Infrastructure/Keycloak/KeycloakHttpClientInterface.php \
        src/Security/Infrastructure/Keycloak/KeycloakHttpClient.php
git commit -m "feat(security): add setUserAttribute, disableUser, revokeUserSessions to Keycloak HTTP client"
```

---

### Task 13: Security — listeners OrganizationRegistered et OrganizationSuspended

**Files:**
- Create: `src/Security/Infrastructure/EventListener/OrganizationRegisteredListener.php`
- Create: `src/Security/Infrastructure/EventListener/OrganizationSuspendedListener.php`
- Modify: `config/services/security.yaml` — enregistrer les listeners

**Interfaces:**
- Consumes: `OrganizationRegistered` (Task 1), `OrganizationSuspended` (Task 1), `KeycloakHttpClientInterface::setUserAttribute`, `disableUser`, `revokeUserSessions` (Task 12), `IdentityMappingRepository` (existant)

- [ ] **Step 1: Créer OrganizationRegisteredListener**

Ce listener réagit à `OrganizationRegistered`. Il cherche le compte Keycloak de l'opérateur propriétaire (celui créé lors du register de l'opérateur) via `IdentityMappingRepository`, puis appelle `setUserAttribute` pour injecter `organization_id`.

**Important :** En V1, `OrganizationRegistered` est dispatché mais il n'y a pas encore d'opérateur créé automatiquement à ce stade (c'est le sous-projet 2 — Onboarding). Ce listener prépare l'infrastructure sans créer de régression. Il sera activé pleinement en sous-projet 2.

```php
// src/Security/Infrastructure/EventListener/OrganizationRegisteredListener.php
<?php
declare(strict_types=1);
namespace App\Security\Infrastructure\EventListener;

use App\Security\Infrastructure\Keycloak\KeycloakHttpClientInterface;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use App\Shared\Domain\Event\OrganizationRegistered;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: OrganizationRegistered::class)]
final readonly class OrganizationRegisteredListener
{
    public function __construct(
        private KeycloakHttpClientInterface $keycloakClient,
        private IdentityMappingRepository $identityMapping,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(OrganizationRegistered $event): void
    {
        // En V1 : aucun opérateur n'est encore créé à ce stade.
        // Ce listener sera activé pleinement en sous-projet 2 (Onboarding)
        // quand un OrganizationOwner sera créé en même temps que l'Organization.
        $this->logger->info('OrganizationRegistered received, no operator to map in V1', [
            'organization_id' => $event->organizationId,
        ]);
    }
}
```

- [ ] **Step 2: Créer OrganizationSuspendedListener**

```php
// src/Security/Infrastructure/EventListener/OrganizationSuspendedListener.php
<?php
declare(strict_types=1);
namespace App\Security\Infrastructure\EventListener;

use App\Security\Infrastructure\Keycloak\KeycloakHttpClientInterface;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use App\Shared\Domain\Event\OrganizationSuspended;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: OrganizationSuspended::class)]
final readonly class OrganizationSuspendedListener
{
    public function __construct(
        private KeycloakHttpClientInterface $keycloakClient,
        private IdentityMappingRepository $identityMapping,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(OrganizationSuspended $event): void
    {
        // Trouver tous les opérateurs de cette organisation et désactiver leurs comptes Keycloak
        // En V1 : pas d'opérateurs scoped par organization_id dans la DB encore (pas de query par org)
        // Ce listener sera enrichi en sous-projet 2.
        $this->logger->info('OrganizationSuspended received', [
            'organization_id' => $event->organizationId,
            'suspended_at'    => $event->suspendedAt->format(\DateTimeInterface::ATOM),
        ]);
    }
}
```

- [ ] **Step 3: Enregistrer dans config/services/security.yaml**

Les listeners utilisent `#[AsEventListener]` — ils sont auto-découverts par le `resource:` existant dans `security.yaml`. Vérifier que `src/Security/Infrastructure/` est bien couvert par le scan :

```bash
grep "resource.*Security" config/services/security.yaml
```

Si `App\Security\Infrastructure\:` est déjà scané avec `autoconfigure: true`, les listeners sont automatiquement enregistrés. Sinon ajouter :

```yaml
    App\Security\Infrastructure\EventListener\:
        resource: '../../src/Security/Infrastructure/EventListener/'
```

- [ ] **Step 4: Vérifier que les tests passent**

```bash
make unit-test
```
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Security/Infrastructure/EventListener/
git commit -m "feat(security): add OrganizationRegistered and OrganizationSuspended event listeners (V1 stubs)"
```

---

### Task 14: deptrac + exceptions mappings + vérification globale

**Files:**
- Modify: `deptrac-contexts.yaml` — ajouter `Organization` + `OrganizationContract`
- Modify: `config/services/exceptions.yaml` — mapper les exceptions Organization

**Interfaces:**
- Consumes: tout le code des tâches précédentes
- Produces: analyse deptrac propre, exceptions HTTP correctement mappées, tous les tests verts

- [ ] **Step 1: Ajouter Organization et OrganizationContract dans deptrac-contexts.yaml**

Dans la section `layers:`, ajouter après les layers existants :

```yaml
        -
            name: Organization
            collectors:
                -
                    type: bool
                    must:
                        -
                            type: classLike
                            value: 'App\\Organization\\.*'
                    must_not:
                        -
                            type: classLike
                            value: 'App\\Organization\\Application\\Contract\\.*'
        -
            name: OrganizationContract
            collectors:
                -
                    type: classLike
                    value: 'App\\Organization\\Application\\Contract\\.*'
```

Dans la section `ruleset:`, ajouter les autorisations :

```yaml
  Organization:
      - Shared
  Security:
      - OrganizationContract
      - BookerContract       # déjà présent probablement
  Hotel:
      - Shared
  Operator:
      - Shared
```

- [ ] **Step 2: Mapper les exceptions Organization dans config/services/exceptions.yaml**

```yaml
            App\Organization\Domain\Exception\OrganizationNotFoundException:
                type: 'https://book.it/problems/organization-not-found'
                title: 'Organization Not Found'
                status: 404
            App\Organization\Domain\Exception\OrganizationAlreadyExistsException:
                type: 'https://book.it/problems/organization-already-exists'
                title: 'Organization Already Exists'
                status: 409
```

- [ ] **Step 3: Vérifier deptrac**

```bash
make deptrac
```
Expected: aucune violation

Si des violations apparaissent sur `TenantContextMiddleware` (qui importe `OperatorUser` depuis Security), ajouter dans `deptrac.yaml` sous le ruleset Infrastructure :

```yaml
  Infrastructure:
      - Domain
      - Shared
      - SecurityInfrastructure   # si nécessaire
```

Ou ajouter une exception spécifique — regarder le message d'erreur exact avant d'agir.

- [ ] **Step 4: Lancer tous les tests unitaires et d'intégration**

```bash
make unit-test
```
Expected: tous PASS

- [ ] **Step 5: Lancer les tests fonctionnels**

```bash
make functional-test
```
Expected: tous PASS (la migration est appliquée, les tests fonctionnels existants passent avec le TenantContext initialisé via le JWT de test qui porte `organization_id`)

- [ ] **Step 6: Lancer le lint complet**

```bash
make lint
```
Expected: aucune erreur PHPStan, PHPCSFixer, deptrac, OpenAPI

- [ ] **Step 7: Commit final**

```bash
git add deptrac-contexts.yaml config/services/exceptions.yaml
git commit -m "feat(config): add Organization to deptrac boundaries and HTTP exception mappings"
```

---

## Résumé des commits attendus

1. `feat(shared): add OrganizationId VO, domain events and TenantContextNotInitializedException`
2. `feat(shared): add TenantContext service with OrganizationId lifecycle`
3. `feat(shared): add TenantScopeAware trait for DBAL repository scoping`
4. `feat(organization): add domain layer — model, value objects, ports, exceptions`
5. `feat(organization): add application layer — use cases, handlers, contract`
6. `feat(organization): add infrastructure layer — DBAL repo, checker, id generator, service config`
7. `feat(migration): add organization schema, organizations table, organization_id columns on hotel and operator`
8. `feat(hotel): add organizationId, HotelPublicReaderInterface for public reads, scoped HotelRepository for operators`
9. `feat(operator): add organizationId and OperatorRole to model, apply tenant scope in repository`
10. `feat(security): OperatorUser carries organizationId from JWT claim`
11. `feat(shared): add TenantContextMiddleware — sets OrganizationId from JWT claim on each request`
12. `feat(security): add setUserAttribute, disableUser, revokeUserSessions to Keycloak HTTP client`
13. `feat(security): add OrganizationRegistered and OrganizationSuspended event listeners (V1 stubs)`
14. `feat(config): add Organization to deptrac boundaries and HTTP exception mappings`

---

## Points d'attention pour l'implémenteur

1. **readonly class → final class** : `HotelRepository` et `OperatorRepository` passent de `final readonly class` à `final class` pour permettre l'initialisation manuelle de `TenantContext`. Les propriétés individuelles gardent `readonly`.

2. **TenantContext non initialisé** : Les endpoints publics (GET hotel, recherche) qui n'ont pas de JWT opérateur ne doivent pas déclencher `TenantContextNotInitializedException`. Vérifier les routes publiques — elles ne doivent pas appeler `HotelRepository::get()` via un chemin scoped. Si `list()` est utilisé par une route publique, elle a besoin d'un traitement spécial (pas de scope, ou scope optionnel).

3. **Tests fonctionnels existants** : Les tests qui créent des hôtels via `$this->registerHotel($client)` vont maintenant passer par le `TenantContextMiddleware`. Le JWT de test (`AuthenticatedWebTestCase`) porte `organization_id = '00000000-0000-0000-0000-000000000001'` (Default Organization). La migration doit avoir créé cette organisation dans la DB de test pour que les FK ne cassent pas.

4. **Séparation lecture publique / opérateur (résolue en Task 8) :** `GET /api/v1/hotels/{id}` est `PUBLIC_ACCESS` — le `GetHotelQueryHandler` utilise `HotelPublicReaderInterface` (non-scoped). `HotelRepository` (scoped) n'est jamais appelé depuis une route publique. `DoctrineHotelFinder` (utilisé cross-context) utilise aussi `HotelPublicReaderInterface`. `GET /api/v1/hotels` (liste) passe via `HotelRepositoryInterface` scoped — c'est désormais une route opérateur uniquement ; la découverte publique passe par le contexte Search.

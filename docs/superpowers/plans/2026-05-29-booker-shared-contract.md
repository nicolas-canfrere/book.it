# Booker Shared Contract Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Supprimer la dépendance de `Reservation\Infrastructure` vers `Booker\Application` en introduisant `BookerProviderInterface` dans `Shared\Domain\Port`, implémentée par `Booker\Infrastructure`.

**Architecture:** `BookerExistenceChecker` (Reservation) injecte `BookerProviderInterface` au lieu du query bus. `BookerProvider` (Booker\Infrastructure) implémente ce contrat en appelant `GetBookerQuery` via le query bus — il reste dans son propre contexte, donc autorisé. Le câblage est déclaré dans `booker.yaml`.

**Tech Stack:** PHP 8.4, Symfony 8.0, PHPUnit, Messenger (SyncQueryBus)

**Spec:** `docs/superpowers/specs/2026-05-29-booker-shared-contract-design.md`

---

### Task 1 — BookerProviderInterface

**Files:**
- Create: `src/Shared/Domain/Port/BookerProviderInterface.php`

- [ ] **Créer l'interface**

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Port;

interface BookerProviderInterface
{
    public function exists(string $bookerId): bool;
}
```

- [ ] **Vérifier que le container compile**

```bash
make lint
```

Expected : aucune erreur.

- [ ] **Commit**

```bash
git add src/Shared/Domain/Port/BookerProviderInterface.php
git commit -m "feat(shared): add BookerProviderInterface"
```

---

### Task 2 — BookerProvider (Booker\Infrastructure)

**Files:**
- Create: `src/Booker/Infrastructure/Service/BookerProvider.php`
- Create: `tests/Booker/Infrastructure/Service/BookerProviderTest.php`

- [ ] **Écrire le test (TDD — il doit échouer)**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Booker\Infrastructure\Service;

use App\Booker\Application\UseCase\GetBooker\GetBookerQuery;
use App\Booker\Domain\Model\Booker;
use App\Booker\Infrastructure\Service\BookerProvider;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BookerProviderTest extends TestCase
{
    #[Test]
    public function itReturnsTrueWhenBookerExists(): void
    {
        $booker = new Booker(
            id: '550e8400-e29b-41d4-a716-446655440001',
            firstName: 'Alice',
            lastName: 'Martin',
            email: 'alice@example.com',
            phone: '+33600000000',
            dateOfBirth: new \DateTimeImmutable('1990-01-01'),
            registeredAt: new \DateTimeImmutable('2026-01-01'),
        );

        $queryBus = $this->createMock(SyncQueryBusInterface::class);
        $queryBus
            ->expects($this->once())
            ->method('ask')
            ->with($this->equalTo(new GetBookerQuery('550e8400-e29b-41d4-a716-446655440001')))
            ->willReturn($booker);

        $provider = new BookerProvider($queryBus);

        self::assertTrue($provider->exists('550e8400-e29b-41d4-a716-446655440001'));
    }

    #[Test]
    public function itReturnsFalseWhenBookerDoesNotExist(): void
    {
        $queryBus = $this->createMock(SyncQueryBusInterface::class);
        $queryBus
            ->expects($this->once())
            ->method('ask')
            ->with($this->equalTo(new GetBookerQuery('550e8400-e29b-41d4-a716-446655440002')))
            ->willReturn(null);

        $provider = new BookerProvider($queryBus);

        self::assertFalse($provider->exists('550e8400-e29b-41d4-a716-446655440002'));
    }
}
```

- [ ] **Lancer le test — vérifier qu'il échoue**

```bash
make unit-test
```

Expected : FAIL — `BookerProvider` n'existe pas.

- [ ] **Créer l'implémentation**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Infrastructure\Service;

use App\Booker\Application\UseCase\GetBooker\GetBookerQuery;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use App\Shared\Domain\Port\BookerProviderInterface;

final readonly class BookerProvider implements BookerProviderInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus)
    {
    }

    public function exists(string $bookerId): bool
    {
        return null !== $this->queryBus->ask(new GetBookerQuery($bookerId));
    }
}
```

- [ ] **Lancer le test — vérifier qu'il passe**

```bash
make unit-test
```

Expected : PASS.

- [ ] **Commit**

```bash
git add src/Booker/Infrastructure/Service/BookerProvider.php tests/Booker/Infrastructure/Service/BookerProviderTest.php
git commit -m "feat(booker): add BookerProvider implementing BookerProviderInterface"
```

---

### Task 3 — Mise à jour de BookerExistenceChecker

**Files:**
- Modify: `src/Reservation/Infrastructure/Service/BookerExistenceChecker.php`
- Create: `tests/Reservation/Infrastructure/Service/BookerExistenceCheckerTest.php`

- [ ] **Écrire le test (TDD — il doit échouer)**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Service;

use App\Reservation\Infrastructure\Service\BookerExistenceChecker;
use App\Shared\Domain\Port\BookerProviderInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BookerExistenceCheckerTest extends TestCase
{
    #[Test]
    public function itReturnsTrueWhenProviderConfirmsExistence(): void
    {
        $provider = $this->createMock(BookerProviderInterface::class);
        $provider
            ->expects($this->once())
            ->method('exists')
            ->with('550e8400-e29b-41d4-a716-446655440001')
            ->willReturn(true);

        $checker = new BookerExistenceChecker($provider);

        self::assertTrue($checker->exists('550e8400-e29b-41d4-a716-446655440001'));
    }

    #[Test]
    public function itReturnsFalseWhenProviderDeniesExistence(): void
    {
        $provider = $this->createMock(BookerProviderInterface::class);
        $provider
            ->expects($this->once())
            ->method('exists')
            ->with('550e8400-e29b-41d4-a716-446655440002')
            ->willReturn(false);

        $checker = new BookerExistenceChecker($provider);

        self::assertFalse($checker->exists('550e8400-e29b-41d4-a716-446655440002'));
    }
}
```

- [ ] **Lancer le test — vérifier qu'il échoue**

```bash
make unit-test
```

Expected : FAIL — `BookerExistenceChecker` n'accepte pas `BookerProviderInterface` dans son constructeur.

- [ ] **Mettre à jour BookerExistenceChecker**

Remplacer le contenu de `src/Reservation/Infrastructure/Service/BookerExistenceChecker.php` :

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\BookerExistsInterface;
use App\Shared\Domain\Port\BookerProviderInterface;

final readonly class BookerExistenceChecker implements BookerExistsInterface
{
    public function __construct(private BookerProviderInterface $bookerProvider)
    {
    }

    public function exists(string $bookerId): bool
    {
        return $this->bookerProvider->exists($bookerId);
    }
}
```

- [ ] **Lancer tous les tests unitaires — vérifier qu'ils passent**

```bash
make unit-test
```

Expected : PASS — y compris `CreateReservationCommandHandlerTest` qui n'est pas affecté (il mocke `BookerExistsInterface`, inchangé).

- [ ] **Commit**

```bash
git add src/Reservation/Infrastructure/Service/BookerExistenceChecker.php tests/Reservation/Infrastructure/Service/BookerExistenceCheckerTest.php
git commit -m "refactor(reservation): inject BookerProviderInterface into BookerExistenceChecker"
```

---

### Task 4 — Câblage DI et vérification finale

**Files:**
- Modify: `config/services/booker.yaml`

- [ ] **Ajouter l'alias dans booker.yaml**

Dans `config/services/booker.yaml`, ajouter sous la section `services:` (après les blocs `_defaults` et `_instanceof`) :

```yaml
    App\Shared\Domain\Port\BookerProviderInterface: '@App\Booker\Infrastructure\Service\BookerProvider'
```

Le fichier complet doit ressembler à :

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

    App\Shared\Domain\Port\BookerProviderInterface: '@App\Booker\Infrastructure\Service\BookerProvider'

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

- [ ] **Vérifier que le container compile et que deptrac passe**

```bash
make lint
```

Expected : aucune erreur deptrac, container valide.

- [ ] **Lancer la suite complète**

```bash
make test
```

Expected : toutes les suites (unit, integration, functional) passent.

- [ ] **Commit final**

```bash
git add config/services/booker.yaml
git commit -m "config(booker): wire BookerProviderInterface to BookerProvider"
```

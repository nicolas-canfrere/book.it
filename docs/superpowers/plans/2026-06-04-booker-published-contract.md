# Booker Published Contract (BookerFinder) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer le couplage `Reservation → Booker/Application/UseCase/GetBooker/GetBookerQuery` par un contrat publié (`BookerFinder` interface + `BookerView` DTO) pour le cas `BookerExistenceChecker`.

**Architecture:** Le contexte Booker publie une interface `BookerFinder` et un DTO `BookerView` dans `Application/Contract/` — sa surface publique stable. Une implémentation `DoctrineBookerFinder` dans `Infrastructure/Contract/` mappe l'agrégat `Booker` vers `BookerView`. Le `BookerExistenceChecker` dans le contexte Reservation injecte directement `BookerFinder` au lieu du `SyncQueryBusInterface` générique.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL, PHPUnit

---

## File Map

| Action | Chemin | Responsabilité |
|--------|--------|----------------|
| Create | `src/Booker/Application/Contract/BookerFinder.php` | Interface publique — seul point de contact autorisé cross-contexte |
| Create | `src/Booker/Application/Contract/BookerView.php` | DTO stable — snapshot publié de l'agrégat Booker |
| Create | `src/Booker/Infrastructure/Contract/DoctrineBookerFinder.php` | Implémentation : `BookerRepositoryInterface` → `?BookerView` |
| Modify | `src/Reservation/Infrastructure/Service/BookerExistenceChecker.php` | Injecter `BookerFinder` au lieu de `SyncQueryBusInterface + GetBookerQuery` |
| Modify | `config/services/booker.yaml` | Exclure `BookerView` de l'auto-registration DI |
| Create | `tests/Booker/Infrastructure/Contract/DoctrineBookerFinderTest.php` | Test unitaire du mapping agrégat → DTO |
| Create | `tests/Reservation/Infrastructure/Service/BookerExistenceCheckerTest.php` | Test unitaire du checker via stub `BookerFinder` |

---

## Task 1 : Créer l'interface `BookerFinder` et le DTO `BookerView`

**Files:**
- Create: `src/Booker/Application/Contract/BookerFinder.php`
- Create: `src/Booker/Application/Contract/BookerView.php`

- [ ] **Step 1 : Créer l'interface `BookerFinder`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Application\Contract;

interface BookerFinder
{
    public function find(string $bookerId): ?BookerView;
}
```

- [ ] **Step 2 : Créer le DTO `BookerView`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Application\Contract;

final readonly class BookerView
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $email,
    ) {
    }
}
```

- [ ] **Step 3 : Exclure `BookerView` de l'auto-registration DI**

Symfony tente d'instancier toutes les classes sous `App\Booker\Application\` — `BookerView` a des args `string` sans valeur par défaut, ce qui échouerait. L'exclure.

Dans `config/services/booker.yaml`, modifier le bloc `App\Booker\Application\:` :

```yaml
    App\Booker\Application\:
        resource: '../../src/Booker/Application/'
        exclude:
            - '../../src/Booker/Application/**/*Exception.php'
            - '../../src/Booker/Application/**/*Command.php'
            - '../../src/Booker/Application/**/*Query.php'
            - '../../src/Booker/Application/Contract/*View.php'
```

- [ ] **Step 4 : Vérifier la compilation du container**

```bash
make lint
```

Expected : aucune erreur de compilation DI.

- [ ] **Step 5 : Commit**

```bash
git add src/Booker/Application/Contract/ config/services/booker.yaml
git commit -m "feat(booker): add BookerFinder contract and BookerView DTO"
```

---

## Task 2 : Créer `DoctrineBookerFinder`

**Files:**
- Create: `src/Booker/Infrastructure/Contract/DoctrineBookerFinder.php`
- Create: `tests/Booker/Infrastructure/Contract/DoctrineBookerFinderTest.php`

- [ ] **Step 1 : Écrire le test unitaire**

```php
<?php

declare(strict_types=1);

namespace Tests\Booker\Infrastructure\Contract;

use App\Booker\Application\Contract\BookerFinder;
use App\Booker\Application\Contract\BookerView;
use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use App\Booker\Infrastructure\Contract\DoctrineBookerFinder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineBookerFinderTest extends TestCase
{
    private BookerRepositoryInterface $repository;
    private BookerFinder $finder;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(BookerRepositoryInterface::class);
        $this->finder = new DoctrineBookerFinder($this->repository);
    }

    #[Test]
    public function itReturnsNullWhenBookerDoesNotExist(): void
    {
        $this->repository->method('get')->willReturn(null);

        self::assertNull($this->finder->find('unknown-id'));
    }

    #[Test]
    public function itReturnsBookerViewWhenBookerExists(): void
    {
        $booker = new Booker(
            id: 'b1b2b3b4-0000-0000-0000-000000000001',
            firstName: 'Jean',
            lastName: 'Dupont',
            email: 'jean.dupont@example.com',
            phone: '+33600000000',
            dateOfBirth: new \DateTimeImmutable('1985-03-15'),
            registeredAt: new \DateTimeImmutable('2024-01-01'),
        );
        $this->repository->method('get')->willReturn($booker);

        $view = $this->finder->find('b1b2b3b4-0000-0000-0000-000000000001');

        self::assertNotNull($view);
        self::assertSame('b1b2b3b4-0000-0000-0000-000000000001', $view->id);
        self::assertSame('Jean', $view->firstName);
        self::assertSame('Dupont', $view->lastName);
        self::assertSame('jean.dupont@example.com', $view->email);
    }
}
```

- [ ] **Step 2 : Lancer le test pour confirmer l'échec**

```bash
make unit-test -- --filter DoctrineBookerFinderTest
```

Expected : FAIL — `DoctrineBookerFinder` n'existe pas encore.

- [ ] **Step 3 : Implémenter `DoctrineBookerFinder`**

```php
<?php

declare(strict_types=1);

namespace App\Booker\Infrastructure\Contract;

use App\Booker\Application\Contract\BookerFinder;
use App\Booker\Application\Contract\BookerView;
use App\Booker\Domain\Port\BookerRepositoryInterface;

final readonly class DoctrineBookerFinder implements BookerFinder
{
    public function __construct(private BookerRepositoryInterface $bookerRepository)
    {
    }

    public function find(string $bookerId): ?BookerView
    {
        $booker = $this->bookerRepository->get($bookerId);

        if (null === $booker) {
            return null;
        }

        return new BookerView(
            id: $booker->id,
            firstName: $booker->firstName,
            lastName: $booker->lastName,
            email: $booker->email,
        );
    }
}
```

- [ ] **Step 4 : Lancer le test pour confirmer le succès**

```bash
make unit-test -- --filter DoctrineBookerFinderTest
```

Expected : PASS.

- [ ] **Step 5 : Commit**

```bash
git add src/Booker/Infrastructure/Contract/ tests/Booker/Infrastructure/Contract/
git commit -m "feat(booker): implement DoctrineBookerFinder mapping Booker aggregate to BookerView"
```

---

## Task 3 : Mettre à jour `BookerExistenceChecker`

**Files:**
- Modify: `src/Reservation/Infrastructure/Service/BookerExistenceChecker.php`
- Create: `tests/Reservation/Infrastructure/Service/BookerExistenceCheckerTest.php`

- [ ] **Step 1 : Écrire le test unitaire**

```php
<?php

declare(strict_types=1);

namespace Tests\Reservation\Infrastructure\Service;

use App\Booker\Application\Contract\BookerFinder;
use App\Booker\Application\Contract\BookerView;
use App\Reservation\Infrastructure\Service\BookerExistenceChecker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BookerExistenceCheckerTest extends TestCase
{
    private BookerFinder $bookerFinder;
    private BookerExistenceChecker $checker;

    protected function setUp(): void
    {
        $this->bookerFinder = $this->createStub(BookerFinder::class);
        $this->checker = new BookerExistenceChecker($this->bookerFinder);
    }

    #[Test]
    public function itReturnsFalseWhenBookerDoesNotExist(): void
    {
        $this->bookerFinder->method('find')->willReturn(null);

        self::assertFalse($this->checker->exists('unknown-id'));
    }

    #[Test]
    public function itReturnsTrueWhenBookerExists(): void
    {
        $view = new BookerView(
            id: 'b1b2b3b4-0000-0000-0000-000000000001',
            firstName: 'Jean',
            lastName: 'Dupont',
            email: 'jean.dupont@example.com',
        );
        $this->bookerFinder->method('find')->willReturn($view);

        self::assertTrue($this->checker->exists('b1b2b3b4-0000-0000-0000-000000000001'));
    }
}
```

- [ ] **Step 2 : Lancer le test pour confirmer l'échec**

```bash
make unit-test -- --filter BookerExistenceCheckerTest
```

Expected : FAIL — `BookerExistenceChecker` injecte encore `SyncQueryBusInterface`.

- [ ] **Step 3 : Mettre à jour `BookerExistenceChecker`**

Remplacer le fichier entier :

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Booker\Application\Contract\BookerFinder;
use App\Reservation\Domain\Port\BookerExistsInterface;

final readonly class BookerExistenceChecker implements BookerExistsInterface
{
    public function __construct(private BookerFinder $bookerFinder)
    {
    }

    public function exists(string $bookerId): bool
    {
        return null !== $this->bookerFinder->find($bookerId);
    }
}
```

- [ ] **Step 4 : Lancer le test pour confirmer le succès**

```bash
make unit-test -- --filter BookerExistenceCheckerTest
```

Expected : PASS.

- [ ] **Step 5 : Lancer l'ensemble des tests**

```bash
make test
```

Expected : tous les tests passent. En particulier les tests fonctionnels de création de réservation (`CreateReservationCommandHandlerTest`) qui exercent `BookerExistsInterface` via `FakeBookerExistenceChecker` — ceux-ci ne doivent pas régresser.

- [ ] **Step 6 : Vérifier l'architecture deptrac**

```bash
make deptrac
```

Expected : aucune violation. Le `use App\Booker\Application\Contract\BookerFinder` depuis `Reservation\Infrastructure` est un import cross-contexte — vérifier que deptrac ne le bloque pas. Si une violation apparaît, voir la note ci-dessous.

> **Note deptrac :** Si la règle actuelle `Infrastructure → Application` ne distingue pas les contextes, le cross-contexte passera sans violation pour l'instant. Le verrouillage fin (autoriser seulement `Application\Contract\*` cross-contexte) est une étape ultérieure décrite dans la note de design (`docs/adr/` ou Obsidian).

- [ ] **Step 7 : Commit**

```bash
git add src/Reservation/Infrastructure/Service/BookerExistenceChecker.php \
        tests/Reservation/Infrastructure/Service/BookerExistenceCheckerTest.php
git commit -m "refactor(reservation): replace SyncQueryBus+GetBookerQuery with BookerFinder contract"
```

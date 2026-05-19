# CancellationPolicy (Pricing context) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter la gestion de la CancellationPolicy dans le contexte Pricing — SetCancellationPolicy (upsert), DeleteCancellationPolicy, et GetCancellationPolicy — avec persistence DBAL et exposition HTTP.

**Architecture:** `CancellationPolicy` est un agrégat clé par `roomId` (au plus un par Room), sans ID séparé. La persistence utilise DBAL + SQL brut (même pattern que `DoctrineRatePeriodRepository`). La couche HTTP expose trois routes sur `/api/rooms/{roomId}/cancellation-policy` (PUT / DELETE / GET). Les handlers utilisent `RoomExistsInterface` pour valider l'existence de la Room uniquement sur le SET.

**Tech Stack:** PHP 8.4 / Symfony 8.0 / PostgreSQL 16 (connexion `bookit`) / Doctrine DBAL / Symfony Messenger (`sync.command.bus` / `sync.query.bus`)

---

## File Map

### Domain
- **Create** `src/Pricing/Domain/Model/CancellationPolicy.php` — agrégat (roomId, daysThreshold, updatedAt)
- **Create** `src/Pricing/Domain/Port/CancellationPolicyRepositoryInterface.php` — port de persistence
- **Create** `src/Pricing/Domain/Exception/CancellationPolicyNotFoundException.php` — levée si policy absente sur DELETE/GET

### Infrastructure
- **Create** `src/Pricing/Infrastructure/Persistence/Doctrine/DoctrineCancellationPolicyRepository.php` — DBAL + upsert SQL
- **Create** `migrations/Version20260519000000.php` — table `pricing_cancellation_policy`

### Application — Use Cases
- **Create** `src/Pricing/Application/UseCase/SetCancellationPolicy/SetCancellationPolicyCommand.php`
- **Create** `src/Pricing/Application/UseCase/SetCancellationPolicy/SetCancellationPolicyCommandHandler.php`
- **Create** `src/Pricing/Application/UseCase/DeleteCancellationPolicy/DeleteCancellationPolicyCommand.php`
- **Create** `src/Pricing/Application/UseCase/DeleteCancellationPolicy/DeleteCancellationPolicyCommandHandler.php`
- **Create** `src/Pricing/Application/UseCase/GetCancellationPolicy/GetCancellationPolicyQuery.php`
- **Create** `src/Pricing/Application/UseCase/GetCancellationPolicy/GetCancellationPolicyQueryHandler.php`

### UI
- **Create** `src/Pricing/UI/Http/Controller/CancellationPolicySerializer.php` — sérialisation partagée
- **Create** `src/Pricing/UI/Http/Controller/SetCancellationPolicy/SetCancellationPolicyController.php`
- **Create** `src/Pricing/UI/Http/Controller/SetCancellationPolicy/SetCancellationPolicyRequest.php`
- **Create** `src/Pricing/UI/Http/Controller/DeleteCancellationPolicy/DeleteCancellationPolicyController.php`
- **Create** `src/Pricing/UI/Http/Controller/GetCancellationPolicy/GetCancellationPolicyController.php`

### Config
- **Modify** `config/services/pricing.yaml` — alias repository
- **Modify** `config/services/exceptions.yaml` — mapping 404

### Tests
- **Create** `tests/Pricing/Domain/Model/CancellationPolicyTest.php` — unit
- **Create** `tests/Pricing/Application/UseCase/SetCancellationPolicy/SetCancellationPolicyCommandHandlerTest.php` — integration
- **Create** `tests/Pricing/Application/UseCase/DeleteCancellationPolicy/DeleteCancellationPolicyCommandHandlerTest.php` — integration
- **Create** `tests/Pricing/Application/UseCase/GetCancellationPolicy/GetCancellationPolicyQueryHandlerTest.php` — integration
- **Create** `tests/Pricing/UI/Http/Controller/SetCancellationPolicy/SetCancellationPolicyControllerTest.php` — functional
- **Create** `tests/Pricing/UI/Http/Controller/DeleteCancellationPolicy/DeleteCancellationPolicyControllerTest.php` — functional
- **Create** `tests/Pricing/UI/Http/Controller/GetCancellationPolicy/GetCancellationPolicyControllerTest.php` — functional

---

## Task 1 — Domain : modèle, port, exception

**Files:**
- Create: `src/Pricing/Domain/Model/CancellationPolicy.php`
- Create: `src/Pricing/Domain/Port/CancellationPolicyRepositoryInterface.php`
- Create: `src/Pricing/Domain/Exception/CancellationPolicyNotFoundException.php`
- Create: `tests/Pricing/Domain/Model/CancellationPolicyTest.php`

- [ ] **Step 1 : Écrire le test unitaire (doit échouer)**

```php
<?php
// tests/Pricing/Domain/Model/CancellationPolicyTest.php

declare(strict_types=1);

namespace App\Tests\Pricing\Domain\Model;

use App\Pricing\Domain\Model\CancellationPolicy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CancellationPolicyTest extends TestCase
{
    public function test_constructs_with_valid_data(): void
    {
        $policy = new CancellationPolicy(
            roomId: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            daysThreshold: 14,
            updatedAt: new \DateTimeImmutable('2026-05-19 00:00:00'),
        );

        self::assertSame('f47ac10b-58cc-4372-a567-0e02b2c3d479', $policy->roomId);
        self::assertSame(14, $policy->daysThreshold);
    }

    public function test_throws_on_zero_threshold(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Days threshold must be at least 1.');

        new CancellationPolicy(
            roomId: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            daysThreshold: 0,
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function test_throws_on_negative_threshold(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Days threshold must be at least 1.');

        new CancellationPolicy(
            roomId: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            daysThreshold: -5,
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
```

- [ ] **Step 2 : Lancer le test (doit échouer)**

```bash
make test-unit
```
Résultat attendu : FAIL — `App\Pricing\Domain\Model\CancellationPolicy not found`

- [ ] **Step 3 : Créer le modèle**

```php
<?php
// src/Pricing/Domain/Model/CancellationPolicy.php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

final readonly class CancellationPolicy
{
    public function __construct(
        public string $roomId,
        public int $daysThreshold,
        public \DateTimeImmutable $updatedAt,
    ) {
        if ($daysThreshold < 1) {
            throw new \InvalidArgumentException('Days threshold must be at least 1.');
        }
    }
}
```

- [ ] **Step 4 : Créer le port repository**

```php
<?php
// src/Pricing/Domain/Port/CancellationPolicyRepositoryInterface.php

declare(strict_types=1);

namespace App\Pricing\Domain\Port;

use App\Pricing\Domain\Model\CancellationPolicy;

interface CancellationPolicyRepositoryInterface
{
    public function findByRoomId(string $roomId): ?CancellationPolicy;

    public function save(CancellationPolicy $policy): void;

    public function deleteByRoomId(string $roomId): void;
}
```

- [ ] **Step 5 : Créer l'exception**

```php
<?php
// src/Pricing/Domain/Exception/CancellationPolicyNotFoundException.php

declare(strict_types=1);

namespace App\Pricing\Domain\Exception;

final class CancellationPolicyNotFoundException extends \RuntimeException
{
    public function __construct(string $roomId)
    {
        parent::__construct(sprintf('Cancellation policy not found for room "%s".', $roomId));
    }
}
```

- [ ] **Step 6 : Lancer les tests (doivent passer)**

```bash
make test-unit
```
Résultat attendu : PASS

- [ ] **Step 7 : Commit**

```bash
git add src/Pricing/Domain/ tests/Pricing/Domain/
git commit -m "feat(pricing): add CancellationPolicy domain model, port, and exception"
```

---

## Task 2 — Infrastructure : repository DBAL, migration, DI config

**Files:**
- Create: `src/Pricing/Infrastructure/Persistence/Doctrine/DoctrineCancellationPolicyRepository.php`
- Create: `migrations/Version20260519000000.php`
- Modify: `config/services/pricing.yaml`
- Modify: `config/services/exceptions.yaml`

- [ ] **Step 1 : Créer la migration**

```php
<?php
// migrations/Version20260519000000.php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260519000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pricing_cancellation_policy table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pricing_cancellation_policy (
            room_id UUID NOT NULL,
            days_threshold INT NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (room_id)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pricing_cancellation_policy');
    }
}
```

- [ ] **Step 2 : Appliquer la migration**

```bash
make migrate
```
Résultat attendu : migration appliquée sans erreur. Si `make migrate` n'existe pas, utiliser : `docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction`

- [ ] **Step 3 : Créer le repository DBAL**

```php
<?php
// src/Pricing/Infrastructure/Persistence/Doctrine/DoctrineCancellationPolicyRepository.php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine;

use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use Doctrine\DBAL\Connection;

final readonly class DoctrineCancellationPolicyRepository implements CancellationPolicyRepositoryInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function findByRoomId(string $roomId): ?CancellationPolicy
    {
        /** @var array{room_id: string, days_threshold: int, updated_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT room_id, days_threshold, updated_at
               FROM pricing_cancellation_policy
              WHERE room_id = :roomId',
            ['roomId' => $roomId],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function save(CancellationPolicy $policy): void
    {
        $this->bookit->executeStatement(
            'INSERT INTO pricing_cancellation_policy (room_id, days_threshold, updated_at)
             VALUES (:roomId, :daysThreshold, :updatedAt)
             ON CONFLICT (room_id) DO UPDATE
               SET days_threshold = :daysThreshold,
                   updated_at = :updatedAt',
            [
                'roomId'        => $policy->roomId,
                'daysThreshold' => $policy->daysThreshold,
                'updatedAt'     => $policy->updatedAt->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function deleteByRoomId(string $roomId): void
    {
        $this->bookit->delete('pricing_cancellation_policy', ['room_id' => $roomId]);
    }

    /**
     * @param array{room_id: string, days_threshold: int, updated_at: string} $row
     */
    private function hydrate(array $row): CancellationPolicy
    {
        return new CancellationPolicy(
            $row['room_id'],
            (int) $row['days_threshold'],
            new \DateTimeImmutable($row['updated_at']),
        );
    }
}
```

- [ ] **Step 4 : Ajouter l'alias DI dans `config/services/pricing.yaml`**

Ajouter après le bloc `# Promotion repository alias` existant :

```yaml
    # CancellationPolicy repository alias
    App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface:
        alias: App\Pricing\Infrastructure\Persistence\Doctrine\DoctrineCancellationPolicyRepository
```

- [ ] **Step 5 : Ajouter le mapping d'exception dans `config/services/exceptions.yaml`**

Ajouter dans le bloc `$map` de `ExceptionProblemRegistry` :

```yaml
            App\Pricing\Domain\Exception\CancellationPolicyNotFoundException:
                type: 'https://book.it/problems/cancellation-policy-not-found'
                title: 'Cancellation Policy Not Found'
                status: 404
```

- [ ] **Step 6 : Vérifier que le container compile**

```bash
make lint
```
Résultat attendu : pas d'erreur de compilation DI.

- [ ] **Step 7 : Commit**

```bash
git add src/Pricing/Infrastructure/ migrations/ config/services/
git commit -m "feat(pricing): add DoctrineCancellationPolicyRepository, migration, and DI wiring"
```

---

## Task 3 — Use case : SetCancellationPolicy

**Files:**
- Create: `src/Pricing/Application/UseCase/SetCancellationPolicy/SetCancellationPolicyCommand.php`
- Create: `src/Pricing/Application/UseCase/SetCancellationPolicy/SetCancellationPolicyCommandHandler.php`
- Create: `tests/Pricing/Application/UseCase/SetCancellationPolicy/SetCancellationPolicyCommandHandlerTest.php`

- [ ] **Step 1 : Écrire le test d'intégration (doit échouer)**

```php
<?php
// tests/Pricing/Application/UseCase/SetCancellationPolicy/SetCancellationPolicyCommandHandlerTest.php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\SetCancellationPolicy;

use App\Pricing\Application\UseCase\SetCancellationPolicy\SetCancellationPolicyCommand;
use App\Pricing\Application\UseCase\SetCancellationPolicy\SetCancellationPolicyCommandHandler;
use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use App\Pricing\Domain\Port\RoomExistsInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class SetCancellationPolicyCommandHandlerTest extends KernelTestCase
{
    private Connection $connection;
    private CancellationPolicyRepositoryInterface $repository;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->repository = $container->get(CancellationPolicyRepositoryInterface::class);
        $this->connection->executeStatement('TRUNCATE TABLE pricing_cancellation_policy');
    }

    private function handlerWithRoomExists(bool $exists): SetCancellationPolicyCommandHandler
    {
        $roomExists = $this->createMock(RoomExistsInterface::class);
        $roomExists->method('exists')->willReturn($exists);

        return new SetCancellationPolicyCommandHandler($this->repository, $roomExists);
    }

    public function test_creates_cancellation_policy(): void
    {
        $roomId = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $handler = $this->handlerWithRoomExists(true);

        ($handler)(new SetCancellationPolicyCommand($roomId, 14));

        $row = $this->connection->fetchAssociative(
            'SELECT room_id, days_threshold FROM pricing_cancellation_policy WHERE room_id = :roomId',
            ['roomId' => $roomId],
        );

        self::assertNotFalse($row);
        self::assertSame($roomId, $row['room_id']);
        self::assertSame(14, (int) $row['days_threshold']);
    }

    public function test_upserts_existing_policy(): void
    {
        $roomId = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $handler = $this->handlerWithRoomExists(true);

        ($handler)(new SetCancellationPolicyCommand($roomId, 7));
        ($handler)(new SetCancellationPolicyCommand($roomId, 30));

        $row = $this->connection->fetchAssociative(
            'SELECT days_threshold FROM pricing_cancellation_policy WHERE room_id = :roomId',
            ['roomId' => $roomId],
        );

        self::assertNotFalse($row);
        self::assertSame(30, (int) $row['days_threshold']);
    }

    public function test_throws_when_room_does_not_exist(): void
    {
        $handler = $this->handlerWithRoomExists(false);

        $this->expectException(RoomNotFoundException::class);

        ($handler)(new SetCancellationPolicyCommand('unknown-room-uuid', 14));
    }
}
```

- [ ] **Step 2 : Lancer le test (doit échouer)**

```bash
make test-integration
```
Résultat attendu : FAIL — handler introuvable.

- [ ] **Step 3 : Créer la commande**

```php
<?php
// src/Pricing/Application/UseCase/SetCancellationPolicy/SetCancellationPolicyCommand.php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\SetCancellationPolicy;

final readonly class SetCancellationPolicyCommand
{
    public function __construct(
        public string $roomId,
        public int $daysThreshold,
    ) {
    }
}
```

- [ ] **Step 4 : Créer le handler**

```php
<?php
// src/Pricing/Application/UseCase/SetCancellationPolicy/SetCancellationPolicyCommandHandler.php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\SetCancellationPolicy;

use App\Pricing\Domain\Exception\RoomNotFoundException;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class SetCancellationPolicyCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private CancellationPolicyRepositoryInterface $cancellationPolicyRepository,
        private RoomExistsInterface $roomExists,
    ) {
    }

    public function __invoke(SetCancellationPolicyCommand $command): void
    {
        if (!$this->roomExists->exists($command->roomId)) {
            throw new RoomNotFoundException($command->roomId);
        }

        $this->cancellationPolicyRepository->save(new CancellationPolicy(
            roomId: $command->roomId,
            daysThreshold: $command->daysThreshold,
            updatedAt: new \DateTimeImmutable(),
        ));
    }
}
```

- [ ] **Step 5 : Lancer les tests (doivent passer)**

```bash
make test-integration
```
Résultat attendu : PASS

- [ ] **Step 6 : Commit**

```bash
git add src/Pricing/Application/UseCase/SetCancellationPolicy/ tests/Pricing/Application/UseCase/SetCancellationPolicy/
git commit -m "feat(pricing): add SetCancellationPolicy use case"
```

---

## Task 4 — Use case : DeleteCancellationPolicy

**Files:**
- Create: `src/Pricing/Application/UseCase/DeleteCancellationPolicy/DeleteCancellationPolicyCommand.php`
- Create: `src/Pricing/Application/UseCase/DeleteCancellationPolicy/DeleteCancellationPolicyCommandHandler.php`
- Create: `tests/Pricing/Application/UseCase/DeleteCancellationPolicy/DeleteCancellationPolicyCommandHandlerTest.php`

- [ ] **Step 1 : Écrire le test d'intégration (doit échouer)**

```php
<?php
// tests/Pricing/Application/UseCase/DeleteCancellationPolicy/DeleteCancellationPolicyCommandHandlerTest.php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\DeleteCancellationPolicy;

use App\Pricing\Application\UseCase\DeleteCancellationPolicy\DeleteCancellationPolicyCommand;
use App\Pricing\Application\UseCase\DeleteCancellationPolicy\DeleteCancellationPolicyCommandHandler;
use App\Pricing\Domain\Exception\CancellationPolicyNotFoundException;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class DeleteCancellationPolicyCommandHandlerTest extends KernelTestCase
{
    private Connection $connection;
    private CancellationPolicyRepositoryInterface $repository;
    private DeleteCancellationPolicyCommandHandler $handler;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->repository = $container->get(CancellationPolicyRepositoryInterface::class);
        $this->handler = new DeleteCancellationPolicyCommandHandler($this->repository);
        $this->connection->executeStatement('TRUNCATE TABLE pricing_cancellation_policy');
    }

    public function test_deletes_existing_policy(): void
    {
        $roomId = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $this->repository->save(new CancellationPolicy($roomId, 14, new \DateTimeImmutable()));

        ($this->handler)(new DeleteCancellationPolicyCommand($roomId));

        $row = $this->connection->fetchAssociative(
            'SELECT room_id FROM pricing_cancellation_policy WHERE room_id = :roomId',
            ['roomId' => $roomId],
        );

        self::assertFalse($row);
    }

    public function test_throws_when_policy_not_found(): void
    {
        $this->expectException(CancellationPolicyNotFoundException::class);

        ($this->handler)(new DeleteCancellationPolicyCommand('f47ac10b-58cc-4372-a567-0e02b2c3d479'));
    }
}
```

- [ ] **Step 2 : Lancer le test (doit échouer)**

```bash
make test-integration
```
Résultat attendu : FAIL — handler introuvable.

- [ ] **Step 3 : Créer la commande**

```php
<?php
// src/Pricing/Application/UseCase/DeleteCancellationPolicy/DeleteCancellationPolicyCommand.php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\DeleteCancellationPolicy;

final readonly class DeleteCancellationPolicyCommand
{
    public function __construct(
        public string $roomId,
    ) {
    }
}
```

- [ ] **Step 4 : Créer le handler**

```php
<?php
// src/Pricing/Application/UseCase/DeleteCancellationPolicy/DeleteCancellationPolicyCommandHandler.php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\DeleteCancellationPolicy;

use App\Pricing\Domain\Exception\CancellationPolicyNotFoundException;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use App\Shared\Application\Bus\SyncCommandHandlerInterface;

final readonly class DeleteCancellationPolicyCommandHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private CancellationPolicyRepositoryInterface $cancellationPolicyRepository,
    ) {
    }

    public function __invoke(DeleteCancellationPolicyCommand $command): void
    {
        if (null === $this->cancellationPolicyRepository->findByRoomId($command->roomId)) {
            throw new CancellationPolicyNotFoundException($command->roomId);
        }

        $this->cancellationPolicyRepository->deleteByRoomId($command->roomId);
    }
}
```

- [ ] **Step 5 : Lancer les tests (doivent passer)**

```bash
make test-integration
```
Résultat attendu : PASS

- [ ] **Step 6 : Commit**

```bash
git add src/Pricing/Application/UseCase/DeleteCancellationPolicy/ tests/Pricing/Application/UseCase/DeleteCancellationPolicy/
git commit -m "feat(pricing): add DeleteCancellationPolicy use case"
```

---

## Task 5 — Use case : GetCancellationPolicy

**Files:**
- Create: `src/Pricing/Application/UseCase/GetCancellationPolicy/GetCancellationPolicyQuery.php`
- Create: `src/Pricing/Application/UseCase/GetCancellationPolicy/GetCancellationPolicyQueryHandler.php`
- Create: `tests/Pricing/Application/UseCase/GetCancellationPolicy/GetCancellationPolicyQueryHandlerTest.php`

- [ ] **Step 1 : Écrire le test d'intégration (doit échouer)**

```php
<?php
// tests/Pricing/Application/UseCase/GetCancellationPolicy/GetCancellationPolicyQueryHandlerTest.php

declare(strict_types=1);

namespace App\Tests\Pricing\Application\UseCase\GetCancellationPolicy;

use App\Pricing\Application\UseCase\GetCancellationPolicy\GetCancellationPolicyQuery;
use App\Pricing\Application\UseCase\GetCancellationPolicy\GetCancellationPolicyQueryHandler;
use App\Pricing\Domain\Exception\CancellationPolicyNotFoundException;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class GetCancellationPolicyQueryHandlerTest extends KernelTestCase
{
    private CancellationPolicyRepositoryInterface $repository;
    private GetCancellationPolicyQueryHandler $handler;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->repository = $container->get(CancellationPolicyRepositoryInterface::class);
        $this->handler = new GetCancellationPolicyQueryHandler($this->repository);
        $container->get(Connection::class)->executeStatement('TRUNCATE TABLE pricing_cancellation_policy');
    }

    public function test_returns_existing_policy(): void
    {
        $roomId = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $this->repository->save(new CancellationPolicy($roomId, 14, new \DateTimeImmutable()));

        $result = ($this->handler)(new GetCancellationPolicyQuery($roomId));

        self::assertSame($roomId, $result->roomId);
        self::assertSame(14, $result->daysThreshold);
    }

    public function test_throws_when_policy_not_found(): void
    {
        $this->expectException(CancellationPolicyNotFoundException::class);

        ($this->handler)(new GetCancellationPolicyQuery('f47ac10b-58cc-4372-a567-0e02b2c3d479'));
    }
}
```

- [ ] **Step 2 : Lancer le test (doit échouer)**

```bash
make test-integration
```
Résultat attendu : FAIL — handler introuvable.

- [ ] **Step 3 : Créer la query**

```php
<?php
// src/Pricing/Application/UseCase/GetCancellationPolicy/GetCancellationPolicyQuery.php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetCancellationPolicy;

final readonly class GetCancellationPolicyQuery
{
    public function __construct(
        public string $roomId,
    ) {
    }
}
```

- [ ] **Step 4 : Créer le handler**

```php
<?php
// src/Pricing/Application/UseCase/GetCancellationPolicy/GetCancellationPolicyQueryHandler.php

declare(strict_types=1);

namespace App\Pricing\Application\UseCase\GetCancellationPolicy;

use App\Pricing\Domain\Exception\CancellationPolicyNotFoundException;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use App\Shared\Application\Bus\SyncQueryHandlerInterface;

final readonly class GetCancellationPolicyQueryHandler implements SyncQueryHandlerInterface
{
    public function __construct(
        private CancellationPolicyRepositoryInterface $cancellationPolicyRepository,
    ) {
    }

    public function __invoke(GetCancellationPolicyQuery $query): CancellationPolicy
    {
        $policy = $this->cancellationPolicyRepository->findByRoomId($query->roomId);

        if (null === $policy) {
            throw new CancellationPolicyNotFoundException($query->roomId);
        }

        return $policy;
    }
}
```

- [ ] **Step 5 : Lancer les tests (doivent passer)**

```bash
make test-integration
```
Résultat attendu : PASS

- [ ] **Step 6 : Commit**

```bash
git add src/Pricing/Application/UseCase/GetCancellationPolicy/ tests/Pricing/Application/UseCase/GetCancellationPolicy/
git commit -m "feat(pricing): add GetCancellationPolicy use case"
```

---

## Task 6 — UI HTTP : contrôleurs, sérialiseur, tests fonctionnels

**Files:**
- Create: `src/Pricing/UI/Http/Controller/CancellationPolicySerializer.php`
- Create: `src/Pricing/UI/Http/Controller/SetCancellationPolicy/SetCancellationPolicyController.php`
- Create: `src/Pricing/UI/Http/Controller/SetCancellationPolicy/SetCancellationPolicyRequest.php`
- Create: `src/Pricing/UI/Http/Controller/DeleteCancellationPolicy/DeleteCancellationPolicyController.php`
- Create: `src/Pricing/UI/Http/Controller/GetCancellationPolicy/GetCancellationPolicyController.php`
- Create: `tests/Pricing/UI/Http/Controller/SetCancellationPolicy/SetCancellationPolicyControllerTest.php`
- Create: `tests/Pricing/UI/Http/Controller/DeleteCancellationPolicy/DeleteCancellationPolicyControllerTest.php`
- Create: `tests/Pricing/UI/Http/Controller/GetCancellationPolicy/GetCancellationPolicyControllerTest.php`

- [ ] **Step 1 : Écrire les tests fonctionnels (doivent échouer)**

```php
<?php
// tests/Pricing/UI/Http/Controller/SetCancellationPolicy/SetCancellationPolicyControllerTest.php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller\SetCancellationPolicy;

use App\Pricing\Domain\Port\RoomExistsInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class SetCancellationPolicyControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private const ROOM_ID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        static::getContainer()->get(Connection::class)
            ->executeStatement('TRUNCATE TABLE pricing_cancellation_policy');
    }

    private function mockRoomExists(bool $exists): void
    {
        $mock = $this->createMock(RoomExistsInterface::class);
        $mock->method('exists')->willReturn($exists);
        static::getContainer()->set(RoomExistsInterface::class, $mock);
    }

    private function putPolicy(KernelBrowser $client, string $roomId, mixed $body): void
    {
        $client->request('PUT', '/api/rooms/'.$roomId.'/cancellation-policy', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($body, \JSON_THROW_ON_ERROR));
    }

    public function test_set_returns_204(): void
    {
        $this->mockRoomExists(true);

        $this->putPolicy($this->client, self::ROOM_ID, ['days_threshold' => 14]);

        self::assertResponseStatusCodeSame(204);
    }

    public function test_set_returns_422_on_zero_threshold(): void
    {
        $this->mockRoomExists(true);

        $this->putPolicy($this->client, self::ROOM_ID, ['days_threshold' => 0]);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_set_returns_404_when_room_not_found(): void
    {
        $this->mockRoomExists(false);

        $this->putPolicy($this->client, self::ROOM_ID, ['days_threshold' => 14]);

        self::assertResponseStatusCodeSame(404);
    }
}
```

```php
<?php
// tests/Pricing/UI/Http/Controller/DeleteCancellationPolicy/DeleteCancellationPolicyControllerTest.php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller\DeleteCancellationPolicy;

use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class DeleteCancellationPolicyControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private const ROOM_ID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        static::getContainer()->get(Connection::class)
            ->executeStatement('TRUNCATE TABLE pricing_cancellation_policy');
    }

    private function deletePolicy(KernelBrowser $client, string $roomId): void
    {
        $client->request('DELETE', '/api/rooms/'.$roomId.'/cancellation-policy');
    }

    public function test_delete_returns_204(): void
    {
        static::getContainer()->get(CancellationPolicyRepositoryInterface::class)
            ->save(new CancellationPolicy(self::ROOM_ID, 14, new \DateTimeImmutable()));

        $this->deletePolicy($this->client, self::ROOM_ID);

        self::assertResponseStatusCodeSame(204);
    }

    public function test_delete_returns_404_when_policy_absent(): void
    {
        $this->deletePolicy($this->client, self::ROOM_ID);

        self::assertResponseStatusCodeSame(404);
    }
}
```

```php
<?php
// tests/Pricing/UI/Http/Controller/GetCancellationPolicy/GetCancellationPolicyControllerTest.php

declare(strict_types=1);

namespace App\Tests\Pricing\UI\Http\Controller\GetCancellationPolicy;

use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class GetCancellationPolicyControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private const ROOM_ID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        static::getContainer()->get(Connection::class)
            ->executeStatement('TRUNCATE TABLE pricing_cancellation_policy');
    }

    private function getPolicy(KernelBrowser $client, string $roomId): void
    {
        $client->request('GET', '/api/rooms/'.$roomId.'/cancellation-policy');
    }

    public function test_get_returns_200_with_policy(): void
    {
        static::getContainer()->get(CancellationPolicyRepositoryInterface::class)
            ->save(new CancellationPolicy(self::ROOM_ID, 14, new \DateTimeImmutable('2026-05-19 00:00:00')));

        $this->getPolicy($this->client, self::ROOM_ID);

        self::assertResponseStatusCodeSame(200);

        $body = json_decode($this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(self::ROOM_ID, $body['room_id']);
        self::assertSame(14, $body['days_threshold']);
        self::assertArrayHasKey('updated_at', $body);
    }

    public function test_get_returns_404_when_policy_absent(): void
    {
        $this->getPolicy($this->client, self::ROOM_ID);

        self::assertResponseStatusCodeSame(404);
    }
}
```

- [ ] **Step 2 : Lancer les tests fonctionnels (doivent échouer)**

```bash
make test-functional
```
Résultat attendu : FAIL — routes inconnues (404).

- [ ] **Step 3 : Créer le sérialiseur**

```php
<?php
// src/Pricing/UI/Http/Controller/CancellationPolicySerializer.php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller;

use App\Pricing\Domain\Model\CancellationPolicy;

final readonly class CancellationPolicySerializer
{
    /**
     * @return array{room_id: string, days_threshold: int, updated_at: string}
     */
    public function serialize(CancellationPolicy $policy): array
    {
        return [
            'room_id'        => $policy->roomId,
            'days_threshold' => $policy->daysThreshold,
            'updated_at'     => $policy->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
```

- [ ] **Step 4 : Créer le DTO de requête**

```php
<?php
// src/Pricing/UI/Http/Controller/SetCancellationPolicy/SetCancellationPolicyRequest.php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\SetCancellationPolicy;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetCancellationPolicyRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $daysThreshold,
    ) {
    }
}
```

- [ ] **Step 5 : Créer SetCancellationPolicyController**

```php
<?php
// src/Pricing/UI/Http/Controller/SetCancellationPolicy/SetCancellationPolicyController.php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\SetCancellationPolicy;

use App\Pricing\Application\UseCase\SetCancellationPolicy\SetCancellationPolicyCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class SetCancellationPolicyController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route(
        path: '/api/rooms/{roomId}/cancellation-policy',
        name: 'pricing_set_cancellation_policy',
        requirements: ['roomId' => Requirement::UUID_V4],
        methods: ['PUT'],
    )]
    #[OA\Put(
        path: '/api/rooms/{roomId}/cancellation-policy',
        summary: 'Set or update the cancellation policy for a room',
        tags: ['Pricing'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: SetCancellationPolicyRequest::class)),
        ),
    )]
    #[OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 204, description: 'Cancellation policy set')]
    #[OA\Response(response: 404, description: 'Room not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function __invoke(
        string $roomId,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        SetCancellationPolicyRequest $request,
    ): Response {
        $this->commandBus->dispatch(new SetCancellationPolicyCommand($roomId, $request->daysThreshold));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 6 : Créer DeleteCancellationPolicyController**

```php
<?php
// src/Pricing/UI/Http/Controller/DeleteCancellationPolicy/DeleteCancellationPolicyController.php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\DeleteCancellationPolicy;

use App\Pricing\Application\UseCase\DeleteCancellationPolicy\DeleteCancellationPolicyCommand;
use App\Shared\Application\Bus\SyncCommandBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class DeleteCancellationPolicyController
{
    public function __construct(private SyncCommandBusInterface $commandBus)
    {
    }

    #[Route(
        path: '/api/rooms/{roomId}/cancellation-policy',
        name: 'pricing_delete_cancellation_policy',
        requirements: ['roomId' => Requirement::UUID_V4],
        methods: ['DELETE'],
    )]
    #[OA\Delete(
        path: '/api/rooms/{roomId}/cancellation-policy',
        summary: 'Delete the cancellation policy for a room',
        tags: ['Pricing'],
    )]
    #[OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 204, description: 'Cancellation policy deleted')]
    #[OA\Response(response: 404, description: 'Cancellation policy not found')]
    public function __invoke(string $roomId): Response
    {
        $this->commandBus->dispatch(new DeleteCancellationPolicyCommand($roomId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
```

- [ ] **Step 7 : Créer GetCancellationPolicyController**

```php
<?php
// src/Pricing/UI/Http/Controller/GetCancellationPolicy/GetCancellationPolicyController.php

declare(strict_types=1);

namespace App\Pricing\UI\Http\Controller\GetCancellationPolicy;

use App\Pricing\Application\UseCase\GetCancellationPolicy\GetCancellationPolicyQuery;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\UI\Http\Controller\CancellationPolicySerializer;
use App\Shared\Application\Bus\SyncQueryBusInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final readonly class GetCancellationPolicyController
{
    public function __construct(
        private SyncQueryBusInterface $queryBus,
        private CancellationPolicySerializer $serializer,
    ) {
    }

    #[Route(
        path: '/api/rooms/{roomId}/cancellation-policy',
        name: 'pricing_get_cancellation_policy',
        requirements: ['roomId' => Requirement::UUID_V4],
        methods: ['GET'],
    )]
    #[OA\Get(
        path: '/api/rooms/{roomId}/cancellation-policy',
        summary: 'Get the cancellation policy for a room',
        tags: ['Pricing'],
    )]
    #[OA\Parameter(name: 'roomId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Cancellation policy')]
    #[OA\Response(response: 404, description: 'No cancellation policy set for this room')]
    public function __invoke(string $roomId): JsonResponse
    {
        /** @var CancellationPolicy $policy */
        $policy = $this->queryBus->dispatch(new GetCancellationPolicyQuery($roomId));

        return new JsonResponse($this->serializer->serialize($policy));
    }
}
```

- [ ] **Step 8 : Lancer les tests fonctionnels (doivent passer)**

```bash
make test-functional
```
Résultat attendu : PASS

- [ ] **Step 9 : Lancer la suite complète**

```bash
make test
```
Résultat attendu : tous les tests passent (unit + integration + functional).

- [ ] **Step 10 : Commit**

```bash
git add src/Pricing/UI/ tests/Pricing/UI/
git commit -m "feat(pricing): add SetCancellationPolicy, DeleteCancellationPolicy, GetCancellationPolicy HTTP controllers"
```

---

## Task 7 — OpenAPI

- [ ] **Step 1 : Régénérer le fichier OpenAPI**

```bash
make openapi
```
Résultat attendu : `public/api/openapi.yaml` mis à jour sans erreur, avec les trois nouvelles routes.

- [ ] **Step 2 : Vérifier les routes dans le fichier généré**

```bash
grep -A 2 "cancellation-policy" public/api/openapi.yaml
```
Résultat attendu : les trois entrées (`get`, `put`, `delete`) apparaissent.

- [ ] **Step 3 : Lancer lint complet**

```bash
make lint
```
Résultat attendu : 0 erreur (CS Fixer, deptrac, container).

- [ ] **Step 4 : Commit final**

```bash
git add public/api/openapi.yaml
git commit -m "docs(openapi): update spec with CancellationPolicy routes"
```

---

## Self-review

### Spec coverage
| Décision (note Obsidian) | Tâche |
|---|---|
| `SetCancellationPolicy` upsert | Task 3 + Task 6 (PUT) |
| `DeleteCancellationPolicy` | Task 4 + Task 6 (DELETE) |
| `GetCancellationPolicy` | Task 5 + Task 6 (GET) |
| Upsert = at most one per Room | SQL `ON CONFLICT (room_id) DO UPDATE` — Task 2 |
| `daysThreshold` ≥ 1 | Validé dans `CancellationPolicy::__construct` — Task 1 |
| `CancellationPolicyNotFoundException` → 404 | `exceptions.yaml` — Task 2 |
| `RoomNotFoundException` sur SET si Room inconnue | Handler Task 3, vérifié par test |
| Pas de query `GetCancellationEligibility` | Non inclus — conforme à la note |
| Changements n'affectent pas les Reservations existantes | Hors scope (Reservation context) |

### Placeholder scan
Aucun TBD, aucun TODO, aucun "implement later" dans le plan.

### Type consistency
- `CancellationPolicyRepositoryInterface` : `findByRoomId`, `save`, `deleteByRoomId` — noms cohérents dans le handler Task 3, 4, 5 et le repository Task 2.
- `SetCancellationPolicyCommand` : champs `roomId`, `daysThreshold` — utilisés identiquement dans le handler.
- `CancellationPolicySerializer::serialize` → `array{room_id, days_threshold, updated_at}` — cohérent avec l'assertion du test fonctionnel GET.

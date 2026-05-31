# Availability Schema Migration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrer les tables `availability_hold` et `blocked_period` du schéma `public` vers un schéma PostgreSQL dédié `availability`, et connecter le contexte Availability à une connexion DBAL nommée isolée.

**Architecture:** Même connexion PostgreSQL physique (`BOOKIT_DATABASE_URL`), schéma logique `availability` isolé via `SearchPathMiddleware`. `availability_hold` est renommé en `hold` (le schéma fournit le namespace) ; `blocked_period` garde son nom (pas de préfixe contextuel à supprimer). Les deux repositories conservent des noms de table non qualifiés — seule l'URL de connexion changerait lors d'une future extraction en microservice.

**Tech Stack:** PHP 8.4 / Symfony 8.0 / PostgreSQL 16 / Doctrine DBAL (sans ORM) / Doctrine Migrations

---

## Pré-requis

- Être sur la branche `feat/availability-db-schema` (déjà créée)
- Docker up : `make up`

## File Map

| Action | Fichier | Ce qui change |
|--------|---------|---------------|
| Modify | `config/packages/doctrine.yaml` | Ajout de la connexion `availability` |
| Modify | `config/services/availability.yaml` | `SearchPathMiddleware` + DI explicite des deux repositories |
| Create | `migrations/Version202605XXXXXX.php` | Généré puis édité |
| Modify | `src/Availability/Infrastructure/Persistence/Doctrine/AvailabilityHoldRepository.php` | `$bookit` → `$availabilityConnection`, `availability_hold` → `hold` |
| Modify | `src/Availability/Infrastructure/Persistence/Doctrine/BlockedPeriodRepository.php` | `$bookit` → `$availabilityConnection` |

---

### Task 1 : Ajouter la connexion DBAL `availability` dans `doctrine.yaml`

**Files:**
- Modify: `config/packages/doctrine.yaml`

- [ ] **Step 1 : Ajouter le bloc `availability` après le bloc `reservation`**

Ouvrir `config/packages/doctrine.yaml`. Le fichier actuel se termine ainsi (après le bloc `reservation`) :

```yaml
            reservation:
                server_version: '16'
                url: '%env(resolve:BOOKIT_DATABASE_URL)%' # same DB, isolated via search_path=reservation (set by SearchPathMiddleware)
                profiling_collect_backtrace: '%kernel.debug%'
```

Ajouter immédiatement après :

```yaml
            availability:
                server_version: '16'
                url: '%env(resolve:BOOKIT_DATABASE_URL)%' # same DB, isolated via search_path=availability (set by SearchPathMiddleware)
                profiling_collect_backtrace: '%kernel.debug%'
```

- [ ] **Step 2 : Vérifier que le container compile**

```bash
docker compose exec php bin/console debug:container doctrine.dbal.availability_connection
```

Expected : une ligne décrivant le service `doctrine.dbal.availability_connection`.

---

### Task 2 : Enregistrer le `SearchPathMiddleware` et câbler les repositories dans `availability.yaml`

**Files:**
- Modify: `config/services/availability.yaml`

- [ ] **Step 1 : Ajouter le middleware et les injections explicites**

Remplacer le contenu de `config/services/availability.yaml` par :

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

    App\Availability\Domain\:
        resource: '../../src/Availability/Domain/'
        exclude:
            - '../../src/Availability/Domain/Model/'

    App\Availability\Application\:
        resource: '../../src/Availability/Application/'
        exclude:
            - '../../src/Availability/Application/**/*Command.php'
            - '../../src/Availability/Application/**/*Query.php'

    App\Availability\Infrastructure\:
        resource: '../../src/Availability/Infrastructure/'

    App\Availability\UI\:
        resource: '../../src/Availability/UI/'
        exclude:
            - '../../src/Availability/UI/**/*Request.php'

    App\Availability\Infrastructure\Persistence\Doctrine\AvailabilityHoldRepository:
        arguments:
            $availabilityConnection: '@doctrine.dbal.availability_connection'

    App\Availability\Infrastructure\Persistence\Doctrine\BlockedPeriodRepository:
        arguments:
            $availabilityConnection: '@doctrine.dbal.availability_connection'

    bookit.doctrine.middleware.search_path.availability:
        class: App\Shared\Infrastructure\Doctrine\SearchPathMiddleware
        arguments:
            $schema: 'availability'
        tags:
            - {name: doctrine.middleware, connection: availability}
```

- [ ] **Step 2 : Vérifier que le container compile sans erreur**

```bash
docker compose exec php bin/console debug:container App\\Availability\\Infrastructure\\Persistence\\Doctrine\\AvailabilityHoldRepository
```

Expected : le service est listé avec l'argument `availabilityConnection` pointant vers `doctrine.dbal.availability_connection`.

---

### Task 3 : Mettre à jour `AvailabilityHoldRepository`

**Files:**
- Modify: `src/Availability/Infrastructure/Persistence/Doctrine/AvailabilityHoldRepository.php`

- [ ] **Step 1 : Renommer le paramètre de connexion et mettre à jour `availability_hold` → `hold`**

Remplacer le contenu du fichier par :

```php
<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\Persistence\Doctrine;

use App\Availability\Domain\Model\AvailabilityHold;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use Doctrine\DBAL\Connection;

final readonly class AvailabilityHoldRepository implements AvailabilityHoldRepositoryInterface
{
    public function __construct(private Connection $availabilityConnection)
    {
    }

    public function add(AvailabilityHold $hold): void
    {
        $this->availabilityConnection->insert('hold', [
            'id' => $hold->id,
            'room_id' => $hold->roomId,
            'reservation_id' => $hold->reservationId,
            'check_in' => $hold->period->checkIn->format('Y-m-d'),
            'check_out' => $hold->period->checkOut->format('Y-m-d'),
            'expires_at' => $hold->expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $hold->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function deleteByReservationId(string $reservationId): void
    {
        $this->availabilityConnection->delete('hold', ['reservation_id' => $reservationId]);
    }

    public function hasActiveOverlap(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        $count = $this->availabilityConnection->fetchOne(
            'SELECT COUNT(*) FROM hold
             WHERE room_id = :roomId
               AND check_in < :checkOut
               AND check_out > :checkIn
               AND expires_at > :now',
            [
                'roomId' => $roomId,
                'checkIn' => $checkIn->format('Y-m-d'),
                'checkOut' => $checkOut->format('Y-m-d'),
                'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
        );

        return $count > 0;
    }
}
```

- [ ] **Step 2 : Vérifier statiquement**

```bash
make static-code-analysis
```

Expected : 0 errors.

---

### Task 4 : Mettre à jour `BlockedPeriodRepository`

**Files:**
- Modify: `src/Availability/Infrastructure/Persistence/Doctrine/BlockedPeriodRepository.php`

- [ ] **Step 1 : Renommer le paramètre de connexion**

Remplacer le contenu du fichier par :

```php
<?php

declare(strict_types=1);

namespace App\Availability\Infrastructure\Persistence\Doctrine;

use App\Availability\Domain\Model\BlockedPeriod;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Availability\Domain\ValueObject\DatePeriod;
use Doctrine\DBAL\Connection;

final readonly class BlockedPeriodRepository implements BlockedPeriodRepositoryInterface
{
    public function __construct(private Connection $availabilityConnection)
    {
    }

    public function add(BlockedPeriod $period): void
    {
        $this->availabilityConnection->insert('blocked_period', [
            'id' => $period->id,
            'room_id' => $period->roomId,
            'check_in' => $period->period->checkIn->format('Y-m-d'),
            'check_out' => $period->period->checkOut->format('Y-m-d'),
            'created_at' => $period->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $id): ?BlockedPeriod
    {
        /** @var array{id: string, room_id: string, check_in: string, check_out: string, created_at: string}|false $row */
        $row = $this->availabilityConnection->fetchAssociative(
            'SELECT id, room_id, check_in, check_out, created_at FROM blocked_period WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function remove(string $id): void
    {
        $this->availabilityConnection->delete('blocked_period', ['id' => $id]);
    }

    public function hasOverlap(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        $count = $this->availabilityConnection->fetchOne(
            'SELECT COUNT(*) FROM blocked_period
             WHERE room_id = :roomId
               AND check_in < :checkOut
               AND check_out > :checkIn',
            [
                'roomId' => $roomId,
                'checkIn' => $checkIn->format('Y-m-d'),
                'checkOut' => $checkOut->format('Y-m-d'),
            ],
        );

        return $count > 0;
    }

    /** @return list<BlockedPeriod> */
    public function listByRoomId(string $roomId): array
    {
        /** @var list<array{id: string, room_id: string, check_in: string, check_out: string, created_at: string}> $rows */
        $rows = $this->availabilityConnection->fetchAllAssociative(
            'SELECT id, room_id, check_in, check_out, created_at FROM blocked_period
             WHERE room_id = :roomId
             ORDER BY check_in ASC',
            ['roomId' => $roomId],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function removeByRoomAndPeriod(
        string $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
    ): void {
        $this->availabilityConnection->executeStatement(
            'DELETE FROM blocked_period WHERE room_id = :roomId AND check_in = :checkIn AND check_out = :checkOut',
            [
                'roomId' => $roomId,
                'checkIn' => $checkIn->format('Y-m-d'),
                'checkOut' => $checkOut->format('Y-m-d'),
            ],
        );
    }

    /**
     * @param array{id: string, room_id: string, check_in: string, check_out: string, created_at: string} $row
     */
    private function hydrate(array $row): BlockedPeriod
    {
        return new BlockedPeriod(
            $row['id'],
            $row['room_id'],
            new DatePeriod(
                new \DateTimeImmutable($row['check_in']),
                new \DateTimeImmutable($row['check_out']),
            ),
            new \DateTimeImmutable($row['created_at']),
        );
    }
}
```

- [ ] **Step 2 : Vérifier statiquement**

```bash
make static-code-analysis
```

Expected : 0 errors.

---

### Task 5 : Générer et compléter la migration Doctrine

**Files:**
- Create: `migrations/Version202605XXXXXX.php` (nom généré automatiquement)

- [ ] **Step 1 : Générer le squelette de migration**

```bash
make generate-migration
```

Expected : un nouveau fichier `migrations/Version202605XXXXXX.php` est créé.

- [ ] **Step 2 : Remplacer le contenu `up()` / `down()`**

Ouvrir le fichier généré et remplacer les méthodes par :

```php
public function getDescription(): string
{
    return 'Move availability tables to availability schema, rename availability_hold to hold';
}

public function up(Schema $schema): void
{
    $this->addSql('CREATE SCHEMA IF NOT EXISTS availability');
    $this->addSql('ALTER TABLE availability_hold SET SCHEMA availability');
    $this->addSql('ALTER TABLE availability.availability_hold RENAME TO hold');
    $this->addSql('ALTER TABLE blocked_period SET SCHEMA availability');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE availability.blocked_period SET SCHEMA public');
    $this->addSql('ALTER TABLE availability.hold RENAME TO availability_hold');
    $this->addSql('ALTER TABLE availability.availability_hold SET SCHEMA public');
    $this->addSql('DROP SCHEMA IF EXISTS availability');
}
```

- [ ] **Step 3 : Appliquer la migration**

```bash
make migrate
```

Expected : `[OK] 1 migration executed`, sans erreur.

- [ ] **Step 4 : Vérifier en base**

```bash
docker compose exec postgres psql -U bookit -d bookit -c "\dt availability.*"
```

Expected : deux tables listées — `availability.hold` et `availability.blocked_period`.

---

### Task 6 : Vérifier les tests

- [ ] **Step 1 : Lancer l'analyse statique et l'architecture**

```bash
make lint
```

Expected : 0 erreurs CS Fixer, PHPStan, Deptrac.

- [ ] **Step 2 : Lancer tous les tests**

```bash
make test
```

Expected : tous les tests unitaires, d'intégration et fonctionnels passent (verts).

---

### Task 7 : Commit et ouverture de la PR

- [ ] **Step 1 : Détecter les changements**

```bash
npx gitnexus detect
```

Vérifier que seuls les symboles attendus sont affectés : `AvailabilityHoldRepository`, `BlockedPeriodRepository`, config DBAL.

- [ ] **Step 2 : Stager et committer**

Remplacer `Version202605XXXXXX` par le nom réel du fichier généré.

```bash
git add config/packages/doctrine.yaml \
        config/services/availability.yaml \
        src/Availability/Infrastructure/Persistence/Doctrine/AvailabilityHoldRepository.php \
        src/Availability/Infrastructure/Persistence/Doctrine/BlockedPeriodRepository.php \
        migrations/Version202605XXXXXX.php

git commit -m "feat(availability): migrate availability tables to availability schema"
```

- [ ] **Step 3 : Pousser et ouvrir la PR**

```bash
git push -u origin feat/availability-db-schema
gh pr create \
  --title "feat(availability): migrate availability tables to availability schema" \
  --body "Moves \`availability_hold\` and \`blocked_period\` tables from the \`public\` schema to a dedicated \`availability\` PostgreSQL schema. Renames \`availability_hold\` to \`hold\` (schema provides the namespace). Adds a named DBAL connection \`availability\` with \`SearchPathMiddleware\` for schema isolation. Follows the pattern established by Hotel, Room, Booker, Pricing, and Reservation migrations."
```

---

## Checklist de vérification finale

- [ ] `make lint` passe (CS + PHPStan + Deptrac)
- [ ] `make test` passe (unit + integration + functional)
- [ ] `\dt availability.*` montre `availability.hold` et `availability.blocked_period`
- [ ] La connexion `doctrine.dbal.availability_connection` est résolue dans le container
- [ ] PR ouverte sur GitHub

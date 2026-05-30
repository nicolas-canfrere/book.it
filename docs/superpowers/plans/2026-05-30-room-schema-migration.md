# Room Schema Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Déplacer les tables `room` et `room_type` du schéma `public` vers un schéma PostgreSQL dédié `room`, avec une connexion DBAL isolée, en suivant le pattern établi pour le contexte `Hotel`.

**Architecture:** Une connexion DBAL nommée `room` est ajoutée dans `doctrine.yaml` ; un `SearchPathMiddleware` fixe le `search_path = room` à chaque connexion. Les cinq classes Infrastructure qui injectent `Connection $bookit` passent à `Connection $roomConnection` (autowiring Symfony sur le nom du paramètre). La migration déplace les tables avec `ALTER TABLE ... SET SCHEMA room`.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL 4, PostgreSQL 16, Docker (`make` commands)

---

## File Map

| Action | Fichier | Rôle |
|--------|---------|------|
| Modify | `config/packages/doctrine.yaml` | Ajouter la connexion `room` |
| Modify | `config/services/room.yaml` | Enregistrer `SearchPathMiddleware` pour la connexion `room` |
| Modify | `src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php` | `$bookit` → `$roomConnection` |
| Modify | `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeRepository.php` | `$bookit` → `$roomConnection` |
| Modify | `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeHasRoomsChecker.php` | `$bookit` → `$roomConnection` |
| Modify | `src/Room/Infrastructure/Persistence/Doctrine/RoomCapacityFinder.php` | `$bookit` → `$roomConnection` |
| Modify | `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeExistenceChecker.php` | `$bookit` → `$roomConnection` |
| Create | `migrations/Version20260530120000.php` | Migration SQL : schéma + déplacement des tables |

---

## Task 1 : Connexion DBAL `room` dans `doctrine.yaml`

**Files:**
- Modify: `config/packages/doctrine.yaml`

- [ ] **Step 1 : Ajouter le bloc `room` dans les connexions DBAL**

Fichier complet après modification (`config/packages/doctrine.yaml`) :

```yaml
parameters:
    env(resolve:BOOKIT_DATABASE_URL): ~

doctrine:
    dbal:
        default_connection: bookit
        connections:
            bookit:
                server_version: '16'
                url: '%env(resolve:BOOKIT_DATABASE_URL)%'
                profiling_collect_backtrace: '%kernel.debug%'
            hotel:
                server_version: '16'
                url: '%env(resolve:BOOKIT_DATABASE_URL)%' # same DB, isolated via search_path=hotel (set by SearchPathMiddleware)
                profiling_collect_backtrace: '%kernel.debug%'
            room:
                server_version: '16'
                url: '%env(resolve:BOOKIT_DATABASE_URL)%' # same DB, isolated via search_path=room (set by SearchPathMiddleware)
                profiling_collect_backtrace: '%kernel.debug%'
```

- [ ] **Step 2 : Vérifier que le conteneur compile**

```bash
docker compose exec php bin/console debug:container --env=dev 2>&1 | grep -i "error\|exception" | head -5
```

Attendu : aucune ligne d'erreur.

- [ ] **Step 3 : Commit**

```bash
git add config/packages/doctrine.yaml
git commit -m "feat(room): add room DBAL connection in doctrine.yaml"
```

---

## Task 2 : SearchPathMiddleware dans `room.yaml`

**Files:**
- Modify: `config/services/room.yaml`

- [ ] **Step 1 : Ajouter l'enregistrement du middleware**

À la fin du fichier `config/services/room.yaml`, ajouter le bloc suivant (après les bindings de ports existants) :

```yaml
    App\Shared\Infrastructure\Doctrine\SearchPathMiddleware:
        arguments:
            $schema: 'room'
        tags:
            - {name: doctrine.middleware, connection: room}
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

    App\Room\Domain\:
        resource: '../../src/Room/Domain/'
        exclude:
            - '../../src/Room/Domain/Model/'

    App\Room\Application\:
        resource: '../../src/Room/Application/'
        exclude:
            - '../../src/Room/Application/**/*Exception.php'
            - '../../src/Room/Application/**/*Command.php'
            - '../../src/Room/Application/**/*Query.php'

    App\Room\Infrastructure\:
        resource: '../../src/Room/Infrastructure/'
        exclude:
            - '../../src/Room/Infrastructure/**/*Exception.php'

    App\Room\UI\:
        resource: '../../src/Room/UI/'
        exclude:
            - '../../src/Room/UI/**/*Request.php'

    App\Room\Domain\Port\RoomTypeIdGeneratorInterface:
        class: App\Room\Infrastructure\Service\RoomTypeIdGenerator

    App\Room\Domain\Port\RoomTypeHasRoomsInterface:
        class: App\Room\Infrastructure\Persistence\Doctrine\RoomTypeHasRoomsChecker

    App\Room\Domain\Port\RoomTypeExistsInterface:
        class: App\Room\Infrastructure\Persistence\Doctrine\RoomTypeExistenceChecker

    App\Room\Domain\Port\RoomCapacityFinderInterface:
        class: App\Room\Infrastructure\Persistence\Doctrine\RoomCapacityFinder

    App\Shared\Infrastructure\Doctrine\SearchPathMiddleware:
        arguments:
            $schema: 'room'
        tags:
            - {name: doctrine.middleware, connection: room}
```

- [ ] **Step 2 : Vérifier que le conteneur compile**

```bash
docker compose exec php bin/console debug:container --env=dev 2>&1 | grep -i "error\|exception" | head -5
```

Attendu : aucune ligne d'erreur.

- [ ] **Step 3 : Commit**

```bash
git add config/services/room.yaml
git commit -m "feat(room): register SearchPathMiddleware for room DBAL connection"
```

---

## Task 3 : Mise à jour des repositories — `$bookit` → `$roomConnection`

**Files:**
- Modify: `src/Room/Infrastructure/Persistence/Doctrine/RoomRepository.php`
- Modify: `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeRepository.php`
- Modify: `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeHasRoomsChecker.php`
- Modify: `src/Room/Infrastructure/Persistence/Doctrine/RoomCapacityFinder.php`
- Modify: `src/Room/Infrastructure/Persistence/Doctrine/RoomTypeExistenceChecker.php`

Le paramètre constructeur `Connection $bookit` est renommé en `Connection $roomConnection` dans les cinq fichiers. Symfony résout automatiquement `$roomConnection` → `doctrine.dbal.room_connection` par autowiring sur le nom du paramètre (même pattern que `$hotelConnection` dans `HotelRepository`).

- [ ] **Step 1 : Mettre à jour `RoomRepository`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Model\Room;
use App\Room\Domain\Model\RoomPage;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use Doctrine\DBAL\Connection;

final readonly class RoomRepository implements RoomRepositoryInterface
{
    public function __construct(private Connection $roomConnection)
    {
    }

    public function add(Room $room): void
    {
        $this->roomConnection->insert('room', [
            'id' => $room->id,
            'hotel_id' => $room->hotelId,
            'room_number' => $room->number->value,
            'room_floor' => $room->floor->value,
            'room_type_id' => $room->roomTypeId,
            'created_at' => $room->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function addAll(array $rooms): void
    {
        $this->roomConnection->transactional(function () use ($rooms): void {
            foreach ($rooms as $room) {
                $this->roomConnection->insert('room', [
                    'id' => $room->id,
                    'hotel_id' => $room->hotelId,
                    'room_number' => $room->number->value,
                    'room_floor' => $room->floor->value,
                    'room_type_id' => $room->roomTypeId,
                    'created_at' => $room->createdAt->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }

    public function get(string $id): ?Room
    {
        /** @var array{id: string, hotel_id: string, room_number: string, room_floor: int|string, room_type_id: string, created_at: string}|false $row */
        $row = $this->roomConnection->fetchAssociative(
            'SELECT id, hotel_id, room_number, room_floor, room_type_id, created_at FROM room WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return new Room(
            $row['id'],
            $row['hotel_id'],
            new RoomNumber($row['room_number']),
            new RoomFloor((int) $row['room_floor']),
            $row['room_type_id'],
            new \DateTimeImmutable($row['created_at']),
        );
    }

    public function existsByHotelIdAndNumber(string $hotelId, string $number): bool
    {
        $count = $this->roomConnection->fetchOne(
            'SELECT COUNT(*) FROM room WHERE hotel_id = :hotelId AND room_number = :number',
            ['hotelId' => $hotelId, 'number' => $number],
        );

        return $count > 0;
    }

    public function list(string $hotelId, int $page, int $limit): RoomPage
    {
        /** @var int|string $count */
        $count = $this->roomConnection->fetchOne(
            'SELECT COUNT(*) FROM room WHERE hotel_id = :hotelId',
            ['hotelId' => $hotelId],
        );
        $total = (int) $count;

        /** @var list<array{id: string, hotel_id: string, room_number: string, room_floor: int|string, room_type_id: string, created_at: string}> $rows */
        $rows = $this->roomConnection->fetchAllAssociative(
            'SELECT id, hotel_id, room_number, room_floor, room_type_id, created_at FROM room WHERE hotel_id = :hotelId ORDER BY room_number ASC LIMIT :limit OFFSET :offset',
            ['hotelId' => $hotelId, 'limit' => $limit, 'offset' => ($page - 1) * $limit],
        );

        $rooms = array_map(
            fn(array $row) => new Room(
                $row['id'],
                $row['hotel_id'],
                new RoomNumber($row['room_number']),
                new RoomFloor((int) $row['room_floor']),
                $row['room_type_id'],
                new \DateTimeImmutable($row['created_at']),
            ),
            $rows,
        );

        return new RoomPage($rooms, $total);
    }
}
```

- [ ] **Step 2 : Mettre à jour `RoomTypeRepository`**

Remplacer uniquement les occurrences de `$bookit` par `$roomConnection` (constructeur + corps). Contenu complet :

```php
<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Model\RoomType;
use App\Room\Domain\Model\RoomTypePage;
use App\Room\Domain\Port\RoomTypeRepositoryInterface;
use App\Room\Domain\ValueObject\BedComposition;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

final readonly class RoomTypeRepository implements RoomTypeRepositoryInterface
{
    public function __construct(private Connection $roomConnection)
    {
    }

    public function add(RoomType $roomType): void
    {
        $this->roomConnection->insert('room_type', [
            'id' => $roomType->id,
            'hotel_id' => $roomType->hotelId,
            'name' => $roomType->name,
            'living_space_count' => $roomType->livingSpaceCount,
            'surface_m2' => $roomType->surfaceM2,
            'guest_capacity' => $roomType->guestCapacity,
            'is_accessible' => $roomType->isAccessible,
            'bed_composition' => json_encode($roomType->bedComposition->toArray(), \JSON_THROW_ON_ERROR),
            'created_at' => $roomType->createdAt->format('Y-m-d H:i:s'),
        ], [
            'is_accessible' => Types::BOOLEAN,
        ]);
    }

    public function get(string $id): ?RoomType
    {
        /** @var array{id: string, hotel_id: string, name: string, living_space_count: int|string, surface_m2: int|string|null, guest_capacity: int|string, is_accessible: string|bool, bed_composition: string, created_at: string}|false $row */
        $row = $this->roomConnection->fetchAssociative(
            'SELECT id, hotel_id, name, living_space_count, surface_m2, guest_capacity, is_accessible, bed_composition, created_at FROM room_type WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function existsByHotelIdAndName(string $hotelId, string $name): bool
    {
        $count = $this->roomConnection->fetchOne(
            'SELECT COUNT(*) FROM room_type WHERE hotel_id = :hotelId AND name = :name',
            ['hotelId' => $hotelId, 'name' => $name],
        );

        return $count > 0;
    }

    public function update(RoomType $roomType): void
    {
        $this->roomConnection->update('room_type', [
            'name' => $roomType->name,
            'living_space_count' => $roomType->livingSpaceCount,
            'surface_m2' => $roomType->surfaceM2,
            'guest_capacity' => $roomType->guestCapacity,
            'is_accessible' => $roomType->isAccessible,
            'bed_composition' => json_encode($roomType->bedComposition->toArray(), \JSON_THROW_ON_ERROR),
        ], ['id' => $roomType->id], [
            'is_accessible' => Types::BOOLEAN,
        ]);
    }

    public function delete(string $id): void
    {
        $this->roomConnection->delete('room_type', ['id' => $id]);
    }

    public function list(string $hotelId, int $page, int $limit): RoomTypePage
    {
        /** @var int|string $count */
        $count = $this->roomConnection->fetchOne(
            'SELECT COUNT(*) FROM room_type WHERE hotel_id = :hotelId',
            ['hotelId' => $hotelId],
        );
        $total = (int) $count;

        /** @var list<array{id: string, hotel_id: string, name: string, living_space_count: int|string, surface_m2: int|string|null, guest_capacity: int|string, is_accessible: string|bool, bed_composition: string, created_at: string}> $rows */
        $rows = $this->roomConnection->fetchAllAssociative(
            'SELECT id, hotel_id, name, living_space_count, surface_m2, guest_capacity, is_accessible, bed_composition, created_at FROM room_type WHERE hotel_id = :hotelId ORDER BY name ASC LIMIT :limit OFFSET :offset',
            ['hotelId' => $hotelId, 'limit' => $limit, 'offset' => ($page - 1) * $limit],
        );

        return new RoomTypePage(array_map($this->hydrate(...), $rows), $total);
    }

    /**
     * @param array{id: string, hotel_id: string, name: string, living_space_count: int|string, surface_m2: int|string|null, guest_capacity: int|string, is_accessible: string|bool, bed_composition: string, created_at: string} $row
     */
    private function hydrate(array $row): RoomType
    {
        /** @var list<array{type: string, count: int}> $bedData */
        $bedData = json_decode($row['bed_composition'], true, 512, \JSON_THROW_ON_ERROR);

        return new RoomType(
            $row['id'],
            $row['hotel_id'],
            $row['name'],
            (int) $row['living_space_count'],
            null !== $row['surface_m2'] ? (int) $row['surface_m2'] : null,
            (int) $row['guest_capacity'],
            't' === $row['is_accessible'] || true === $row['is_accessible'],
            BedComposition::fromArray($bedData),
            new \DateTimeImmutable($row['created_at']),
        );
    }
}
```

- [ ] **Step 3 : Mettre à jour `RoomTypeHasRoomsChecker`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Port\RoomTypeHasRoomsInterface;
use Doctrine\DBAL\Connection;

final readonly class RoomTypeHasRoomsChecker implements RoomTypeHasRoomsInterface
{
    public function __construct(private Connection $roomConnection)
    {
    }

    public function hasRooms(string $roomTypeId): bool
    {
        $count = $this->roomConnection->fetchOne(
            'SELECT COUNT(*) FROM room WHERE room_type_id = :id',
            ['id' => $roomTypeId],
        );

        return $count > 0;
    }
}
```

- [ ] **Step 4 : Mettre à jour `RoomCapacityFinder`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Port\RoomCapacityFinderInterface;
use Doctrine\DBAL\Connection;

final readonly class RoomCapacityFinder implements RoomCapacityFinderInterface
{
    public function __construct(private Connection $roomConnection)
    {
    }

    public function findCapacity(string $roomId): int
    {
        $capacity = $this->roomConnection->fetchOne(
            'SELECT rt.guest_capacity
               FROM room r
               JOIN room_type rt ON rt.id = r.room_type_id
              WHERE r.id = :roomId',
            ['roomId' => $roomId],
        );

        if (false === $capacity) {
            return 0;
        }

        /** @var int|string $capacity */
        return (int) $capacity;
    }
}
```

- [ ] **Step 5 : Mettre à jour `RoomTypeExistenceChecker`**

```php
<?php

declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Room\Domain\Port\RoomTypeExistsInterface;
use Doctrine\DBAL\Connection;

final class RoomTypeExistenceChecker implements RoomTypeExistsInterface
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function __construct(private readonly Connection $roomConnection)
    {
    }

    public function exists(string $roomTypeId): bool
    {
        if (!array_key_exists($roomTypeId, $this->cache)) {
            $count = $this->roomConnection->fetchOne(
                'SELECT COUNT(*) FROM room_type WHERE id = :id',
                ['id' => $roomTypeId],
            );
            $this->cache[$roomTypeId] = $count > 0;
        }

        return $this->cache[$roomTypeId];
    }
}
```

- [ ] **Step 6 : Vérifier le lint (PHPStan + CS Fixer)**

```bash
make lint
```

Attendu : aucune erreur.

- [ ] **Step 7 : Commit**

```bash
git add src/Room/Infrastructure/Persistence/Doctrine/
git commit -m "feat(room): switch repositories to dedicated room DBAL connection"
```

---

## Task 4 : Migration SQL — schéma `room`

**Files:**
- Create: `migrations/Version20260530120000.php`

Le nom du fichier doit suivre le pattern `VersionYYYYMMDDHHMMSS.php`. Utiliser la date/heure actuelle, ex. `Version20260530120000.php`.

- [ ] **Step 1 : Créer le fichier de migration**

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move room and room_type tables to room schema, assign dedicated DBAL connection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS room');
        $this->addSql('ALTER TABLE room SET SCHEMA room');
        $this->addSql('ALTER TABLE room_type SET SCHEMA room');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE room.room_type SET SCHEMA public');
        $this->addSql('ALTER TABLE room.room SET SCHEMA public');
        $this->addSql('DROP SCHEMA IF EXISTS room');
    }
}
```

> **Note :** Le `down` qualifie les noms de tables (`room.room_type`, `room.room`) car le `search_path` par défaut pointe vers `public` lors d'un rollback depuis l'extérieur du schéma `room`.

- [ ] **Step 2 : Exécuter la migration**

```bash
make migrate
```

Attendu : `[OK] Successfully executed 1 migrations.`

- [ ] **Step 3 : Vérifier en base que les tables sont bien dans le schéma `room`**

```bash
docker compose exec postgres psql -U postgres -d bookit -c "\dt room.*"
```

Attendu :
```
        List of relations
 Schema |   Name    | Type  |  Owner
--------+-----------+-------+----------
 room   | room      | table | postgres
 room   | room_type | table | postgres
```

- [ ] **Step 4 : Commit**

```bash
git add migrations/Version20260530120000.php
git commit -m "feat(room): migrate room and room_type tables to room schema"
```

---

## Task 5 : Vérification finale

- [ ] **Step 1 : Lancer tous les tests**

```bash
make test
```

Attendu : toutes les suites passent (unit + functional).

- [ ] **Step 2 : Vérifier le lint complet**

```bash
make lint
```

Attendu : CS Fixer + PHPStan + Deptrac sans erreur.

- [ ] **Step 3 : Vérifier que l'OpenAPI spec n'a pas changé**

```bash
make openapi
git diff openapi.yaml
```

Attendu : aucun diff (aucune route modifiée).

- [ ] **Step 4 : Commit final si diff résiduel**

Si `make openapi` ou `make lint --fix` a produit un diff :

```bash
git add -p
git commit -m "chore(room): post-migration lint and openapi cleanup"
```

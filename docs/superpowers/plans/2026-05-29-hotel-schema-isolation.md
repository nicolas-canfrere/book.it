# Hotel Schema Isolation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrer le contexte `Hotel` vers son propre schéma PostgreSQL `hotel` avec une connexion DBAL dédiée et `search_path=hotel`, en pilote de la stratégie d'isolation par contexte borné.

**Architecture:** Chaque contexte borné obtient un schéma PostgreSQL dédié et une connexion DBAL nommée dont le `search_path` pointe exclusivement vers ce schéma. Les repositories conservent des noms de tables non qualifiés (`FROM hotel`) — seule l'URL de connexion change lors d'une future extraction en microservice. Aucun JOIN cross-schéma n'est autorisé.

**Tech Stack:** PHP 8.4 / Symfony 8.0 / Doctrine DBAL 4 (sans ORM) / PostgreSQL 16 / PDO PostgreSQL driver

---

## Fichiers modifiés / créés

| Fichier | Action | Responsabilité |
|---|---|---|
| `config/packages/doctrine.yaml` | Modifier | Ajouter connexion `hotel` avec `search_path=hotel` via `driverOptions` |
| `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php` | Modifier | Renommer param `$bookit` → `$connection` |
| `config/services/hotel.yaml` | Modifier | Câblage explicite de `HotelRepository.$connection` vers `@doctrine.dbal.hotel_connection` |
| `migrations/Version<timestamp>.php` | Créer | `CREATE SCHEMA hotel` + `ALTER TABLE hotel SET SCHEMA hotel` |

---

### Task 1: Créer la branche de travail

**Files:** aucun

- [ ] **Step 1: Créer la branche**

```bash
git checkout -b refactor/hotel-schema-isolation
```

- [ ] **Step 2: Vérifier**

```bash
git branch --show-current
```
Expected: `refactor/hotel-schema-isolation`

---

### Task 2: Impact analysis (obligatoire — CLAUDE.md)

**Files:** aucun

- [ ] **Step 1: Analyser l'impact de HotelRepository**

Via l'outil GitNexus MCP :
```
gitnexus_impact({target: "HotelRepository", direction: "upstream"})
```

- [ ] **Step 2: Analyser l'impact du param de connexion**

```
gitnexus_context({name: "HotelRepository"})
```

- [ ] **Step 3: Vérifier le niveau de risque**

Si GitNexus retourne HIGH ou CRITICAL, signaler à l'utilisateur et attendre confirmation avant de continuer.

---

### Task 3: Ajouter la connexion DBAL `hotel` dans `doctrine.yaml`

**Files:**
- Modify: `config/packages/doctrine.yaml`

**Contexte:** La connexion `hotel` réutilise `BOOKIT_DATABASE_URL` mais fixe `search_path=hotel` via le `driverOptions` PDO. La clé `1001` correspond à `PDO::PGSQL_ATTR_OPTIONS` — elle passe la string d'options libpq au moment de la connexion. La connexion `bookit` reste intacte pour les autres contextes.

- [ ] **Step 1: Modifier `config/packages/doctrine.yaml`**

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
                url: '%env(resolve:BOOKIT_DATABASE_URL)%'
                options:
                    1001: '--search_path=hotel'
```

- [ ] **Step 2: Vérifier que le container Symfony compile**

```bash
make lint
```
Expected: aucune erreur de compilation DI ni deptrac.

---

### Task 4: Mettre à jour `HotelRepository` et son câblage DI

**Files:**
- Modify: `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php`
- Modify: `config/services/hotel.yaml`

**Contexte:** Avec deux connexions `Connection` enregistrées dans le container, Symfony ne peut plus auto-câbler la bonne connexion par type seul. On renomme le param pour lever l'ambiguïté dans le code, et on câble explicitement dans `hotel.yaml`.

- [ ] **Step 1: Renommer le param dans `HotelRepository`**

Dans `src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php`, ligne 19–21 :

```php
    public function __construct(
        private Connection $connection,
        private SluggerInterface $slugger,
    ) {
    }
```

- [ ] **Step 2: Remplacer tous les usages de `$this->bookit` par `$this->connection`**

Dans le même fichier, remplacer toutes les occurrences de `$this->bookit->` par `$this->connection->` (lignes 26, 44, 55, 68, 100, 110).

Le fichier complet après remplacement :

```php
<?php

declare(strict_types=1);

namespace App\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\StarRating;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class HotelRepository implements HotelRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private SluggerInterface $slugger,
    ) {
    }

    public function add(Hotel $hotel): void
    {
        $this->connection->insert('hotel', [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'street_address' => $hotel->address->streetAddress,
            'postal_code' => $hotel->address->postalCode,
            'city' => $hotel->address->city,
            'country' => $hotel->address->country,
            'search_key' => $this->buildSearchKey($hotel->name, $hotel->address),
            'created_at' => $hotel->createdAt->format('Y-m-d H:i:s'),
            'stars' => $hotel->starRating?->stars,
            'superior' => null !== $hotel->starRating ? $hotel->starRating->superior : false,
        ], [
            'superior' => Types::BOOLEAN,
        ]);
    }

    public function save(Hotel $hotel): void
    {
        $this->connection->update('hotel', [
            'stars' => $hotel->starRating?->stars,
            'superior' => null !== $hotel->starRating ? $hotel->starRating->superior : false,
        ], ['id' => $hotel->id], [
            'superior' => Types::BOOLEAN,
        ]);
    }

    public function get(string $id): ?Hotel
    {
        /** @var array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string, stars: int|null, superior: string|bool}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT id, name, street_address, postal_code, city, country, created_at, stars, superior FROM hotel WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function existsByNameAndAddress(string $name, Address $address): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM hotel WHERE search_key = :key',
            ['key' => $this->buildSearchKey($name, $address)],
        );

        return $count > 0;
    }

    public function list(int $page, int $limit, ?string $city, ?string $country, ?int $minStars = null): HotelPage
    {
        $conditions = [];
        $params = [];

        if (null !== $city) {
            $conditions[] = 'city = :city';
            $params['city'] = $city;
        }

        if (null !== $country) {
            $conditions[] = 'country = :country';
            $params['country'] = $country;
        }

        if (null !== $minStars) {
            $conditions[] = 'stars >= :minStars';
            $params['minStars'] = $minStars;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        /** @var int|string $count */
        $count = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM hotel {$where}",
            $params,
        );
        $total = (int) $count;

        $params['limit'] = $limit;
        $params['offset'] = ($page - 1) * $limit;

        /** @var list<array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string, stars: int|null, superior: string|bool}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, name, street_address, postal_code, city, country, created_at, stars, superior FROM hotel {$where} ORDER BY name ASC LIMIT :limit OFFSET :offset",
            $params,
        );

        return new HotelPage(array_map($this->hydrate(...), $rows), $total);
    }

    /**
     * @param array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string, stars: int|null, superior: string|bool} $row
     */
    private function hydrate(array $row): Hotel
    {
        $starRating = null !== $row['stars']
            ? new StarRating((int) $row['stars'], 't' === $row['superior'] || true === $row['superior'])
            : null;

        return new Hotel(
            $row['id'],
            $row['name'],
            new Address($row['street_address'], $row['postal_code'], $row['city'], $row['country']),
            new \DateTimeImmutable($row['created_at']),
            $starRating,
        );
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
}
```

- [ ] **Step 3: Câbler explicitement dans `config/services/hotel.yaml`**

Ajouter sous les blocs `resource:` existants :

```yaml
    App\Hotel\Infrastructure\Persistence\Doctrine\HotelRepository:
        arguments:
            $connection: '@doctrine.dbal.hotel_connection'
```

Le fichier complet :

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

    App\Hotel\Domain\:
        resource: '../../src/Hotel/Domain/'
        exclude:
            - '../../src/Hotel/Domain/Model/'

    App\Hotel\Application\:
        resource: '../../src/Hotel/Application/'
        exclude:
            - '../../src/Hotel/Application/**/*Exception.php'
            - '../../src/Hotel/Application/**/*Command.php'
            - '../../src/Hotel/Application/**/*Query.php'

    App\Hotel\Infrastructure\:
        resource: '../../src/Hotel/Infrastructure/'
        exclude:
            - '../../src/Hotel/Infrastructure/**/*Exception.php'

    App\Hotel\UI\:
        resource: '../../src/Hotel/UI/'
        exclude:
            - '../../src/Hotel/UI/**/*Request.php'

    App\Hotel\Infrastructure\Persistence\Doctrine\HotelRepository:
        arguments:
            $connection: '@doctrine.dbal.hotel_connection'
```

- [ ] **Step 4: Vérifier la compilation du container**

```bash
make lint
```
Expected: aucune erreur.

---

### Task 5: Écrire et appliquer la migration PostgreSQL

**Files:**
- Create: `migrations/Version<timestamp>.php` (généré automatiquement)

**Contexte:** La migration déplace la table `hotel` du schéma `public` (actuel) vers le schéma `hotel`. Les noms de colonnes, contraintes et index sont préservés. Le `search_path=hotel` de la connexion DBAL garantit que les repositories continueront à faire `FROM hotel` sans qualifier le schéma.

Note : PostgreSQL 16 déplace la table et tous ses index avec `SET SCHEMA`. Les séquences associées (aucune ici — PK est UUID sans séquence) ne nécessitent pas de traitement séparé.

- [ ] **Step 1: Générer le squelette de migration**

```bash
make generate-migration
```
Expected: message indiquant le fichier créé, e.g. `migrations/Version20260529XXXXXX.php`.

- [ ] **Step 2: Remplacer le corps de la migration générée**

Ouvrir le fichier généré et remplacer les méthodes `up()` et `down()` :

```php
    public function getDescription(): string
    {
        return 'Move hotel table to hotel schema, assign dedicated DBAL connection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS hotel');
        $this->addSql('ALTER TABLE hotel SET SCHEMA hotel');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel SET SCHEMA public');
        $this->addSql('DROP SCHEMA IF EXISTS hotel');
    }
```

- [ ] **Step 3: Vérifier que la migration s'applique en prod (DB principale)**

```bash
make migrate
```
Expected: `[OK] Already at the latest version.` puis exécution de la nouvelle migration sans erreur.

- [ ] **Step 4: Vérifier manuellement le déplacement de la table**

```bash
docker compose -f compose.yaml --env-file .env exec database psql -U admin -d bookit -c "\dt hotel.*"
```
Expected: une ligne listant `hotel | hotel | table | admin`.

- [ ] **Step 5: Vérifier le search_path effectif sur la connexion hotel**

```bash
docker compose -f compose.yaml --env-file .env exec database psql -U admin -d bookit -c "SET search_path = hotel; SHOW search_path;"
```
Expected: `hotel`.

---

### Task 6: Vérifier la mécanique du `search_path` via les tests fonctionnels

**Files:** aucun (tests existants utilisés comme régression)

**Contexte:** `make functional-test` exécute les migrations en base de test avant les tests PHPUnit. Les tests fonctionnels Hotel exercent `POST /hotels`, `GET /hotels/{id}`, `GET /hotels`, `PUT /hotels/{id}/star-rating` via HTTP. S'ils passent, cela confirme que :
1. La migration a bien déplacé la table vers le schéma `hotel`
2. Le `search_path=hotel` de la connexion hotel est correctement appliqué par PDO
3. Le câblage DI pointe vers la bonne connexion

- [ ] **Step 1: Lancer les tests fonctionnels**

```bash
make functional-test
```
Expected: tous les tests du groupe `functional` passent, y compris `RegisterHotelControllerTest`, `ListHotelsControllerTest`, `GetHotelControllerTest`, `ClassifyHotelTest`, `ListHotelsTest`.

- [ ] **Step 2: Si un test échoue avec « relation "hotel" does not exist »**

Le `search_path` n'est pas appliqué via `driverOptions`. Appliquer le fallback : ajouter un event listener DBAL.

Créer `src/Shared/Infrastructure/Doctrine/SearchPathMiddleware.php` :

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final class SearchPathMiddleware implements Middleware
{
    public function __construct(private readonly string $schema) {}

    public function wrap(Driver $driver): Driver
    {
        return new class($driver, $this->schema) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly string $schema) {
                parent::__construct($driver);
            }

            public function connect(array $params): Connection
            {
                $connection = parent::connect($params);
                $connection->exec("SET search_path = {$this->schema}");
                return $connection;
            }
        };
    }
}
```

Enregistrer dans `config/services/hotel.yaml` :

```yaml
    App\Shared\Infrastructure\Doctrine\SearchPathMiddleware:
        arguments:
            $schema: 'hotel'
        tags:
            - {name: doctrine.middleware, connection: hotel}
```

Relancer `make functional-test`.

- [ ] **Step 3: Lancer les tests unitaires et d'intégration**

```bash
make unit-test
```
Expected: tous les tests du groupe `unit` et `integration` passent.

---

### Task 7: Commit et ouverture de la PR

**Files:** aucun

- [ ] **Step 1: Vérifier les changements détectés par GitNexus**

```
gitnexus_detect_changes()
```
Expected: uniquement les symboles attendus — `HotelRepository`, la nouvelle migration, les fichiers de config.

- [ ] **Step 2: Vérifier le lint complet**

```bash
make lint
```
Expected: aucune erreur.

- [ ] **Step 3: Stager et commiter**

```bash
git add config/packages/doctrine.yaml \
        config/services/hotel.yaml \
        src/Hotel/Infrastructure/Persistence/Doctrine/HotelRepository.php \
        migrations/Version*.php
git commit -m "$(cat <<'EOF'
refactor(hotel): isolate Hotel context in its own PostgreSQL schema

Creates the hotel schema, moves the hotel table into it, and wires a
dedicated DBAL connection (search_path=hotel) so repositories use
unqualified table names — future microservice extraction changes only
the connection URL.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 4: Pousser et ouvrir la PR**

```bash
git push -u origin refactor/hotel-schema-isolation
```

Utiliser le skill `superpowers:finishing-a-development-branch` pour ouvrir la PR.

---

## Notes d'implémentation

### Mécanisme `search_path` — ordre de priorité

Le Task 6 tente d'abord l'approche `driverOptions` (clé `1001` = `PDO::PGSQL_ATTR_OPTIONS`). Si elle échoue, le fallback est un `Middleware` DBAL 4 qui exécute `SET search_path` à chaque connexion. Le middleware est la solution de référence pour DBAL 4 et est documentée dans le Task 6 Step 2.

### Tables sans FK cross-contexte

La table `hotel` n'a aucune clé étrangère entrante depuis d'autres tables (les autres contextes référencent l'hotel par `hotel_id` sans contrainte FK). Le `ALTER TABLE hotel SET SCHEMA hotel` est donc safe et ne nécessite aucune action sur les autres tables.

### Rollback

La migration `down()` déplace la table vers `public` et supprime le schéma. La connexion `hotel` dans `doctrine.yaml` peut être retirée et `HotelRepository` revert au câblage par défaut.

### Suite de la migration par contexte

Après validation de ce pilote, reproduire le même pattern pour les contextes suivants (dans l'ordre de la note de design) :
`Booker` → `Room` → `Pricing` → `Availability` → `Reservation`.

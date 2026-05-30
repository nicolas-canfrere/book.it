# Booker Schema Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrer la table `booker` du schéma `public` vers un schéma PostgreSQL dédié `booker`, et connecter le contexte Booker à une connexion DBAL nommée isolée, suivant le pattern établi par les contextes Hotel et Room.

**Architecture:** Même connexion PostgreSQL physique (`BOOKIT_DATABASE_URL`), schéma logique `booker` isolé via `SearchPathMiddleware`. Le repository conserve des noms de table non qualifiés (`booker`) — seule l'URL de connexion changerait lors d'une future extraction en microservice.

**Tech Stack:** PHP 8.4 / Symfony 8.0 / PostgreSQL 16 / Doctrine DBAL (sans ORM) / Doctrine Migrations

---

## Pré-requis

- Être sur une branche de travail : `git checkout -b feat/booker-db-schema`
- Docker up : `make up`

## File Map

| Action | Fichier | Ce qui change |
|--------|---------|---------------|
| Modify | `config/packages/doctrine.yaml` | Ajout de la connexion `booker` |
| Modify | `config/services/booker.yaml` | Enregistrement `SearchPathMiddleware` + DI explicite `BookerRepository` |
| Create | `migrations/Version20260530XXXXXX.php` | Généré par `make generate-migration` puis édité |
| Modify | `src/Booker/Infrastructure/Persistence/Doctrine/BookerRepository.php` | `$bookit` → `$booker` (connexion dédiée) |

---

### Task 1 : Créer la branche de travail

- [ ] **Step 1 : Créer la branche**

```bash
git checkout -b feat/booker-db-schema
```

Expected output : `Switched to a new branch 'feat/booker-db-schema'`

---

### Task 2 : Ajouter la connexion DBAL `booker` dans `doctrine.yaml`

**Files:**
- Modify: `config/packages/doctrine.yaml`

- [ ] **Step 1 : Ajouter la connexion**

Ouvrir `config/packages/doctrine.yaml`. Après le bloc `room:`, ajouter :

```yaml
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
            booker:
                server_version: '16'
                url: '%env(resolve:BOOKIT_DATABASE_URL)%' # same DB, isolated via search_path=booker (set by SearchPathMiddleware)
                profiling_collect_backtrace: '%kernel.debug%'
```

- [ ] **Step 2 : Vérifier que le container compile**

```bash
docker compose exec php bin/console debug:container doctrine.dbal.booker_connection
```

Expected : une ligne décrivant le service `doctrine.dbal.booker_connection`.

---

### Task 3 : Enregistrer le `SearchPathMiddleware` et câbler le repository dans `booker.yaml`

**Files:**
- Modify: `config/services/booker.yaml`

- [ ] **Step 1 : Ajouter le middleware et l'injection explicite**

Remplacer le contenu de `config/services/booker.yaml` par :

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

    App\Booker\Infrastructure\Persistence\Doctrine\BookerRepository:
        arguments:
            $booker: '@doctrine.dbal.booker_connection'

    bookit.doctrine.middleware.search_path.booker:
        class: App\Shared\Infrastructure\Doctrine\SearchPathMiddleware
        arguments:
            $schema: 'booker'
        tags:
            - {name: doctrine.middleware, connection: booker}
```

- [ ] **Step 2 : Vérifier que le container compile sans erreur**

```bash
docker compose exec php bin/console debug:container App\\Booker\\Infrastructure\\Persistence\\Doctrine\\BookerRepository
```

Expected : le service est listé avec l'argument `booker` pointant vers `doctrine.dbal.booker_connection`.

---

### Task 4 : Mettre à jour `BookerRepository` pour utiliser la connexion dédiée

**Files:**
- Modify: `src/Booker/Infrastructure/Persistence/Doctrine/BookerRepository.php`

- [ ] **Step 1 : Renommer le paramètre de connexion**

Remplacer le constructeur et toutes les occurrences de `$this->bookit` par `$this->booker` :

```php
<?php

declare(strict_types=1);

namespace App\Booker\Infrastructure\Persistence\Doctrine;

use App\Booker\Domain\Model\Booker;
use App\Booker\Domain\Port\BookerRepositoryInterface;
use Doctrine\DBAL\Connection;

final readonly class BookerRepository implements BookerRepositoryInterface
{
    public function __construct(
        private Connection $booker,
    ) {
    }

    public function add(Booker $booker): void
    {
        $this->booker->insert('booker', [
            'id' => $booker->id,
            'first_name' => $booker->firstName,
            'last_name' => $booker->lastName,
            'email' => $booker->email,
            'phone' => $booker->phone,
            'date_of_birth' => $booker->dateOfBirth->format('Y-m-d'),
            'registered_at' => $booker->registeredAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $id): ?Booker
    {
        /** @var array{id: string, first_name: string, last_name: string, email: string, phone: string, date_of_birth: string, registered_at: string}|false $row */
        $row = $this->booker->fetchAssociative(
            'SELECT id, first_name, last_name, email, phone, date_of_birth, registered_at FROM booker WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return new Booker(
            $row['id'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['phone'],
            new \DateTimeImmutable($row['date_of_birth']),
            new \DateTimeImmutable($row['registered_at']),
        );
    }

    public function existsByEmail(string $email): bool
    {
        /** @var int|string $count */
        $count = $this->booker->fetchOne(
            'SELECT COUNT(*) FROM booker WHERE LOWER(email) = LOWER(:email)',
            ['email' => $email],
        );

        return (int) $count > 0;
    }
}
```

> **Note :** Le nom de la table reste `booker` (non qualifié) — c'est le `search_path=booker` qui résout la table vers `booker.booker`. Les requêtes SQL ne changent pas.

- [ ] **Step 2 : Vérifier statiquement**

```bash
make static-code-analysis
```

Expected : 0 errors.

---

### Task 5 : Générer et compléter la migration Doctrine

**Files:**
- Create: `migrations/Version20260530XXXXXX.php` (nom généré automatiquement)

- [ ] **Step 1 : Générer le squelette de migration**

```bash
make generate-migration
```

Expected : un nouveau fichier `migrations/Version20260530XXXXXX.php` est créé.

- [ ] **Step 2 : Remplacer le contenu `up()` / `down()`**

Ouvrir le fichier généré et remplacer les méthodes par :

```php
public function getDescription(): string
{
    return 'Move booker table to booker schema, assign dedicated DBAL connection';
}

public function up(Schema $schema): void
{
    $this->addSql('CREATE SCHEMA IF NOT EXISTS booker');
    $this->addSql('ALTER TABLE booker SET SCHEMA booker');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE booker.booker SET SCHEMA public');
    $this->addSql('DROP SCHEMA IF EXISTS booker');
}
```

- [ ] **Step 3 : Appliquer la migration**

```bash
make migrate
```

Expected : `[OK] 1 migration executed` (ou similaire), sans erreur.

- [ ] **Step 4 : Vérifier en base**

```bash
docker compose exec postgres psql -U bookit -d bookit -c "\dt booker.*"
```

Expected : la table `booker.booker` est listée.

---

### Task 6 : Vérifier les tests

- [ ] **Step 1 : Lancer les tests d'intégration et fonctionnels**

```bash
make functional-test
```

Expected : tous les tests passent (verts). Les tests d'intégration Booker utilisent la vraie DB et bénéficient automatiquement de l'isolation via le nouveau `search_path`.

- [ ] **Step 2 : Lancer l'analyse statique et l'architecture**

```bash
make lint
```

Expected : 0 erreurs CS Fixer, PHPStan, Deptrac.

---

### Task 7 : Commit et ouverture de la PR

- [ ] **Step 1 : Détecter les changements**

```bash
make gitnexus-detect  # ou : npx gitnexus detect
```

Vérifier que seuls les symboles attendus sont affectés : `BookerRepository`, config DBAL.

- [ ] **Step 2 : Stager et committer**

```bash
git add config/packages/doctrine.yaml \
        config/services/booker.yaml \
        src/Booker/Infrastructure/Persistence/Doctrine/BookerRepository.php \
        migrations/Version20260530XXXXXX.php

git commit -m "feat(booker): migrate booker table to booker schema"
```

- [ ] **Step 3 : Pousser et ouvrir la PR**

```bash
git push -u origin feat/booker-db-schema
gh pr create --title "feat(booker): migrate booker table to booker schema" \
  --body "Moves the \`booker\` table from the \`public\` schema to a dedicated \`booker\` PostgreSQL schema. Adds a named DBAL connection \`booker\` with \`SearchPathMiddleware\` for schema isolation. Follows the pattern established by Hotel and Room migrations."
```

---

## Checklist de vérification finale

- [ ] `make lint` passe (CS + PHPStan + Deptrac)
- [ ] `make functional-test` passe
- [ ] `docker compose exec postgres psql -U bookit -d bookit -c "\dt booker.*"` montre `booker.booker`
- [ ] La connexion `doctrine.dbal.booker_connection` est résolue dans le container
- [ ] PR ouverte sur GitHub

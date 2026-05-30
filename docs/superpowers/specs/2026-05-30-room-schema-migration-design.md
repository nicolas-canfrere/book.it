# Design : migration du contexte Room vers le schéma PostgreSQL `room`

**Date :** 2026-05-30
**Statut :** Approuvé
**Contexte :** book.it — Symfony 8 / PostgreSQL 16 / Doctrine DBAL (sans ORM)
**Référence :** [DB schema par contexte borné](../../../obsidian_vaults/tech/BookIt/2026-05-28 - DB schema par contexte borné.md) *(voir vault Obsidian)*

---

## Motivation

Même motivation que pour `Hotel` : préparer l'extraction future du contexte `Room` en microservice indépendant. Le schéma PostgreSQL dédié garantit qu'à l'extraction seule l'URL de connexion change — le SQL reste intact.

---

## Tables concernées

| Table actuelle (schéma `public`) | Schéma cible | Nom final |
|---|---|---|
| `room` | `room` | `room` |
| `room_type` | `room` | `room_type` |

Pas de renommage : aucun préfixe contexte redondant à supprimer.

---

## Changements

### 1. Migration Doctrine

```sql
-- up
CREATE SCHEMA IF NOT EXISTS room;
ALTER TABLE room SET SCHEMA room;
ALTER TABLE room_type SET SCHEMA room;

-- down
ALTER TABLE room_type SET SCHEMA public;
ALTER TABLE room SET SCHEMA public;
DROP SCHEMA IF EXISTS room;
```

> Le `down` déplace `room_type` avant `room` pour éviter tout conflit.

### 2. `config/packages/doctrine.yaml` — nouvelle connexion

```yaml
room:
    server_version: '16'
    url: '%env(resolve:BOOKIT_DATABASE_URL)%'
```

Même base de données que `bookit` et `hotel` ; le `search_path` est fixé par `SearchPathMiddleware`.

### 3. `config/services/room.yaml` — SearchPathMiddleware

```yaml
App\Shared\Infrastructure\Doctrine\SearchPathMiddleware:
    arguments:
        $schema: 'room'
    tags:
        - {name: doctrine.middleware, connection: room}
```

### 4. Repositories Infrastructure — paramètre constructeur

Symfony autowire `Connection $roomConnection` → `doctrine.dbal.room_connection`. Tous les fichiers Infrastructure qui injectent `Connection $bookit` sont mis à jour :

| Fichier | Avant | Après |
|---|---|---|
| `RoomRepository` | `$bookit` | `$roomConnection` |
| `RoomTypeRepository` | `$bookit` | `$roomConnection` |
| `RoomTypeHasRoomsChecker` | `$bookit` | `$roomConnection` |
| `RoomCapacityFinder` | `$bookit` | `$roomConnection` |
| `RoomTypeExistenceChecker` | `$bookit` | `$roomConnection` |

`HotelExistenceChecker` utilise le query bus — pas de connexion DBAL, inchangé.

Pas d'injection explicite dans `room.yaml` nécessaire : Symfony résout `$roomConnection` par autowiring sur le nom du paramètre (même pattern que `$hotelConnection` dans `Hotel`).

---

## Impact tests

- **Unitaires** (`#[Group('unit')]`) : aucun impact.
- **Intégration** (`#[Group('integration')]`) : bénéficient automatiquement de l'isolation après migration.
- **Fonctionnels** (`#[Group('functional')]`) : transparents (HTTP).

---

## Critères de succès

1. `make migrate` s'exécute sans erreur
2. `make test` passe (toutes suites)
3. `make lint` passe (CS Fixer + PHPStan + Deptrac)
4. `make openapi` ne produit pas de diff (aucun changement de routes)

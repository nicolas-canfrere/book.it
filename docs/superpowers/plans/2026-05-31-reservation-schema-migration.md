# Reservation Schema Migration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrer les tables `reservation` et `reservation_guest` du schéma `public` vers un schéma PostgreSQL dédié `reservation`, et connecter le contexte Reservation à une connexion DBAL nommée isolée.

**Architecture:** Même connexion PostgreSQL physique (`BOOKIT_DATABASE_URL`), schéma logique `reservation` isolé via `SearchPathMiddleware`. `reservation_guest` est renommé en `guest` (le schéma fournit le namespace). Le repository conserve des noms de table non qualifiés — seule l'URL de connexion changerait lors d'une future extraction en microservice. Le contexte Notification accède aux reservations exclusivement via le query bus (pas de JOIN cross-schéma).

**Tech Stack:** PHP 8.4 / Symfony 8.0 / PostgreSQL 16 / Doctrine DBAL (sans ORM) / Doctrine Migrations

---

## Pré-requis

- Être sur la branche `feat/reservation-db-schema` (déjà créée)
- Docker up : `make up`

## File Map

| Action | Fichier | Ce qui change |
|--------|---------|---------------|
| Modify | `config/packages/doctrine.yaml` | Ajout de la connexion `reservation` |
| Modify | `config/services/reservation.yaml` | Enregistrement `SearchPathMiddleware` + DI explicite `ReservationRepository` |
| Create | `migrations/Version20260531XXXXXX.php` | Généré puis édité |
| Modify | `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php` | `$bookit` → `$reservationConnection`, `reservation_guest` → `guest` |

---

### Task 1 : Ajouter la connexion DBAL `reservation` dans `doctrine.yaml`

**Files:**
- Modify: `config/packages/doctrine.yaml`

- [ ] **Step 1 : Ajouter le bloc `reservation` après le bloc `pricing`**

Ouvrir `config/packages/doctrine.yaml`. Le fichier actuel se termine ainsi (après le bloc `pricing`) :

```yaml
            pricing:
                server_version: '16'
                url: '%env(resolve:BOOKIT_DATABASE_URL)%' # same DB, isolated via search_path=pricing (set by SearchPathMiddleware)
                profiling_collect_backtrace: '%kernel.debug%'
```

Ajouter immédiatement après :

```yaml
            reservation:
                server_version: '16'
                url: '%env(resolve:BOOKIT_DATABASE_URL)%' # same DB, isolated via search_path=reservation (set by SearchPathMiddleware)
                profiling_collect_backtrace: '%kernel.debug%'
```

- [ ] **Step 2 : Vérifier que le container compile**

```bash
docker compose exec php bin/console debug:container doctrine.dbal.reservation_connection
```

Expected : une ligne décrivant le service `doctrine.dbal.reservation_connection`.

---

### Task 2 : Enregistrer le `SearchPathMiddleware` et câbler le repository dans `reservation.yaml`

**Files:**
- Modify: `config/services/reservation.yaml`

- [ ] **Step 1 : Ajouter le middleware et l'injection explicite**

Remplacer le contenu de `config/services/reservation.yaml` par :

```yaml
# config/services/reservation.yaml
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

    App\Reservation\Domain\:
        resource: '../../src/Reservation/Domain/'
        exclude:
            - '../../src/Reservation/Domain/Model/'

    App\Reservation\Application\:
        resource: '../../src/Reservation/Application/'
        exclude:
            - '../../src/Reservation/Application/**/*Command.php'
            - '../../src/Reservation/Application/**/*Query.php'

    App\Reservation\Infrastructure\:
        resource: '../../src/Reservation/Infrastructure/'

    App\Reservation\UI\:
        resource: '../../src/Reservation/UI/'
        exclude:
            - '../../src/Reservation/UI/**/*Request.php'

    App\Reservation\Infrastructure\Persistence\Doctrine\ReservationRepository:
        arguments:
            $reservationConnection: '@doctrine.dbal.reservation_connection'

    bookit.doctrine.middleware.search_path.reservation:
        class: App\Shared\Infrastructure\Doctrine\SearchPathMiddleware
        arguments:
            $schema: 'reservation'
        tags:
            - {name: doctrine.middleware, connection: reservation}
```

- [ ] **Step 2 : Vérifier que le container compile sans erreur**

```bash
docker compose exec php bin/console debug:container App\\Reservation\\Infrastructure\\Persistence\\Doctrine\\ReservationRepository
```

Expected : le service est listé avec l'argument `reservationConnection` pointant vers `doctrine.dbal.reservation_connection`.

---

### Task 3 : Mettre à jour `ReservationRepository` pour utiliser la connexion dédiée

**Files:**
- Modify: `src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php`

- [ ] **Step 1 : Renommer le paramètre de connexion et mettre à jour le nom de table `reservation_guest` → `guest`**

Remplacer le constructeur et toutes les occurrences :

```php
<?php

declare(strict_types=1);

namespace App\Reservation\Infrastructure\Persistence\Doctrine;

use App\Reservation\Domain\Model\Guest;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Model\ReservationPage;
use App\Reservation\Domain\Model\ReservationStatus;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class ReservationRepository implements ReservationRepositoryInterface
{
    public function __construct(private Connection $reservationConnection)
    {
    }

    public function add(Reservation $reservation): void
    {
        $this->reservationConnection->insert('reservation', [
            'id' => $reservation->id,
            'room_id' => $reservation->roomId,
            'booker_id' => $reservation->bookerId,
            'check_in' => $reservation->period->checkIn->format('Y-m-d'),
            'check_out' => $reservation->period->checkOut->format('Y-m-d'),
            'total_price' => $reservation->totalPrice,
            'guest_count' => $reservation->guestCount->value,
            'cancellation_terms_days_threshold' => $reservation->cancellationTerms->daysThreshold,
            'price_breakdown' => json_encode($reservation->priceBreakdown->toArray()) ?: '[]',
            'status' => $reservation->status->value,
            'created_at' => $reservation->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function save(Reservation $reservation): void
    {
        $connection = $this->reservationConnection;
        $connection->transactional(static function () use ($connection, $reservation): void {
            $connection->update('reservation', [
                'status' => $reservation->status->value,
                'actual_departure_date' => $reservation->actualDepartureDate?->format('Y-m-d'),
                'cancelled_at' => $reservation->cancelledAt?->format('Y-m-d'),
                'cancelled_by' => $reservation->cancelledBy,
            ], ['id' => $reservation->id]);

            $connection->delete('guest', ['reservation_id' => $reservation->id]);

            foreach ($reservation->guests as $guest) {
                $connection->insert('guest', [
                    'id' => $guest->id,
                    'reservation_id' => $reservation->id,
                    'first_name' => $guest->firstName,
                    'last_name' => $guest->lastName,
                    'date_of_birth' => $guest->dateOfBirth->format('Y-m-d'),
                ]);
            }
        });
    }

    public function get(string $id): ?Reservation
    {
        /** @var list<array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, guest_count: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string, actual_departure_date: string|null, cancelled_at: string|null, cancelled_by: string|null, g_id: string|null, first_name: string|null, last_name: string|null, date_of_birth: string|null}> $rows */
        $rows = $this->reservationConnection->fetchAllAssociative(
            'SELECT r.id, r.room_id, r.booker_id, r.check_in, r.check_out, r.total_price, r.guest_count,
                    r.cancellation_terms_days_threshold, r.price_breakdown, r.status, r.created_at,
                    r.actual_departure_date, r.cancelled_at, r.cancelled_by,
                    rg.id AS g_id, rg.first_name, rg.last_name, rg.date_of_birth
               FROM reservation r
               LEFT JOIN guest rg ON rg.reservation_id = r.id
              WHERE r.id = :id
              ORDER BY rg.id',
            ['id' => $id],
        );

        if ([] === $rows) {
            return null;
        }

        $reservation = $this->hydrate($rows[0]);

        $reservation->guests = array_values(array_filter(array_map(
            function (array $row): ?Guest {
                if (null === $row['g_id']) {
                    return null;
                }

                return new Guest(
                    id: $row['g_id'],
                    firstName: (string) $row['first_name'],
                    lastName: (string) $row['last_name'],
                    dateOfBirth: new \DateTimeImmutable((string) $row['date_of_birth']),
                );
            },
            $rows,
        )));

        return $reservation;
    }

    public function listByBooker(string $bookerId, int $page, int $limit): ReservationPage
    {
        $count = $this->reservationConnection->fetchOne(
            'SELECT COUNT(*) FROM reservation WHERE booker_id = :bookerId',
            ['bookerId' => $bookerId],
        );
        $total = is_numeric($count) ? (int) $count : 0;

        if (0 === $total) {
            return new ReservationPage([], 0);
        }

        $offset = ($page - 1) * $limit;

        /** @var list<array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, guest_count: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string, actual_departure_date: string|null, cancelled_at: string|null, cancelled_by: string|null, g_id: string|null, first_name: string|null, last_name: string|null, date_of_birth: string|null}> $rows */
        $rows = $this->reservationConnection->fetchAllAssociative(
            'SELECT r.id, r.room_id, r.booker_id, r.check_in, r.check_out, r.total_price, r.guest_count,
                    r.cancellation_terms_days_threshold, r.price_breakdown, r.status, r.created_at,
                    r.actual_departure_date, r.cancelled_at, r.cancelled_by,
                    rg.id AS g_id, rg.first_name, rg.last_name, rg.date_of_birth
               FROM reservation r
               LEFT JOIN guest rg ON rg.reservation_id = r.id
              WHERE r.id IN (
                  SELECT id FROM reservation
                   WHERE booker_id = :bookerId
                   ORDER BY created_at DESC
                   LIMIT :limit OFFSET :offset
              )
              ORDER BY r.created_at DESC, r.id, rg.id',
            ['bookerId' => $bookerId, 'limit' => $limit, 'offset' => $offset],
            ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
        );

        $byId = [];
        $guestsByReservationId = [];
        foreach ($rows as $row) {
            $rid = $row['id'];
            if (!isset($byId[$rid])) {
                $byId[$rid] = $row;
                $guestsByReservationId[$rid] = [];
            }
            if (null !== $row['g_id']) {
                $guestsByReservationId[$rid][] = $row;
            }
        }

        $reservations = [];
        foreach ($byId as $rid => $row) {
            $reservation = $this->hydrate($row);
            $reservation->guests = array_map(
                fn(array $g) => new Guest(
                    id: $g['g_id'],
                    firstName: (string) $g['first_name'],
                    lastName: (string) $g['last_name'],
                    dateOfBirth: new \DateTimeImmutable((string) $g['date_of_birth']),
                ),
                $guestsByReservationId[$rid],
            );
            $reservations[] = $reservation;
        }

        return new ReservationPage($reservations, $total);
    }

    /**
     * @param array{id: string, room_id: string, booker_id: string, check_in: string, check_out: string, total_price: int|string, guest_count: int|string, cancellation_terms_days_threshold: int|string|null, price_breakdown: string, status: string, created_at: string, actual_departure_date: string|null, cancelled_at: string|null, cancelled_by: string|null} $row
     */
    private function hydrate(array $row): Reservation
    {
        $threshold = $row['cancellation_terms_days_threshold'];
        $cancellationTerms = null !== $threshold
            ? CancellationTerms::withThreshold((int) $threshold)
            : CancellationTerms::alwaysRefundable();

        /** @var list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}> $nights */
        $nights = json_decode($row['price_breakdown'], true);
        $priceBreakdown = PriceBreakdown::fromArray($nights);

        $reservation = new Reservation(
            id: $row['id'],
            roomId: $row['room_id'],
            bookerId: $row['booker_id'],
            period: new DatePeriod(
                new \DateTimeImmutable($row['check_in']),
                new \DateTimeImmutable($row['check_out']),
            ),
            totalPrice: (int) $row['total_price'],
            cancellationTerms: $cancellationTerms,
            priceBreakdown: $priceBreakdown,
            guestCount: new GuestCount((int) $row['guest_count']),
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
        $reservation->status = ReservationStatus::from($row['status']);
        $reservation->actualDepartureDate = null !== $row['actual_departure_date']
            ? new \DateTimeImmutable($row['actual_departure_date'])
            : null;
        $reservation->cancelledAt = null !== $row['cancelled_at']
            ? new \DateTimeImmutable($row['cancelled_at'])
            : null;
        $reservation->cancelledBy = $row['cancelled_by'];

        return $reservation;
    }
}
```

> **Note :** `reservation_guest` devient `guest` car le schéma `reservation` fournit le namespace. La table `reservation` garde son nom (pas de préfixe `reservation_` à supprimer). Les JOINs utilisent le nom court `guest` — résolu automatiquement par `search_path=reservation`.

- [ ] **Step 2 : Vérifier statiquement**

```bash
make static-code-analysis
```

Expected : 0 errors.

---

### Task 4 : Générer et compléter la migration Doctrine

**Files:**
- Create: `migrations/Version20260531XXXXXX.php` (nom généré automatiquement)

- [ ] **Step 1 : Générer le squelette de migration**

```bash
make generate-migration
```

Expected : un nouveau fichier `migrations/Version20260531XXXXXX.php` est créé.

- [ ] **Step 2 : Remplacer le contenu `up()` / `down()`**

Ouvrir le fichier généré et remplacer les méthodes par :

```php
public function getDescription(): string
{
    return 'Move reservation tables to reservation schema, rename reservation_guest to guest';
}

public function up(Schema $schema): void
{
    $this->addSql('CREATE SCHEMA IF NOT EXISTS reservation');
    $this->addSql('ALTER TABLE reservation SET SCHEMA reservation');
    $this->addSql('ALTER TABLE reservation_guest SET SCHEMA reservation');
    $this->addSql('ALTER TABLE reservation.reservation_guest RENAME TO guest');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE reservation.guest RENAME TO reservation_guest');
    $this->addSql('ALTER TABLE reservation.reservation_guest SET SCHEMA public');
    $this->addSql('ALTER TABLE reservation.reservation SET SCHEMA public');
    $this->addSql('DROP SCHEMA IF EXISTS reservation');
}
```

- [ ] **Step 3 : Appliquer la migration**

```bash
make migrate
```

Expected : `[OK] 1 migration executed`, sans erreur.

- [ ] **Step 4 : Vérifier en base**

```bash
docker compose exec postgres psql -U bookit -d bookit -c "\dt reservation.*"
```

Expected : deux tables listées — `reservation.reservation` et `reservation.guest`.

---

### Task 5 : Vérifier les tests

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

### Task 6 : Commit et ouverture de la PR

- [ ] **Step 1 : Détecter les changements**

```bash
npx gitnexus detect
```

Vérifier que seuls les symboles attendus sont affectés : `ReservationRepository`, config DBAL.

- [ ] **Step 2 : Stager et committer**

Remplacer `Version20260531XXXXXX` par le nom réel du fichier généré.

```bash
git add config/packages/doctrine.yaml \
        config/services/reservation.yaml \
        src/Reservation/Infrastructure/Persistence/Doctrine/ReservationRepository.php \
        migrations/Version20260531XXXXXX.php

git commit -m "feat(reservation): migrate reservation tables to reservation schema"
```

- [ ] **Step 3 : Pousser et ouvrir la PR**

```bash
git push -u origin feat/reservation-db-schema
gh pr create \
  --title "feat(reservation): migrate reservation tables to reservation schema" \
  --body "Moves \`reservation\` and \`reservation_guest\` tables from the \`public\` schema to a dedicated \`reservation\` PostgreSQL schema. Renames \`reservation_guest\` to \`guest\` (schema provides the namespace). Adds a named DBAL connection \`reservation\` with \`SearchPathMiddleware\` for schema isolation. Follows the pattern established by Hotel, Room, Booker, and Pricing migrations."
```

---

## Checklist de vérification finale

- [ ] `make lint` passe (CS + PHPStan + Deptrac)
- [ ] `make test` passe (unit + integration + functional)
- [ ] `\dt reservation.*` montre `reservation.reservation` et `reservation.guest`
- [ ] La connexion `doctrine.dbal.reservation_connection` est résolue dans le container
- [ ] PR ouverte sur GitHub

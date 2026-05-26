# Plan d'implémentation — Pricing Plan 1 : Configuration tarifaire

## Contexte

Implémentation du bounded context `Pricing` — Plan 1 uniquement (Base Rate + Rate Period).
Spec : `PRICING-SPEC.md` sections "Domain model" (BaseRate, RatePeriod, Money, DatePeriod) + "Plan 1 — Configuration tarifaire".
ADRs : `docs/adr/0006-pricing-as-separate-context.md`, `docs/adr/0007-two-independent-pricing-layers.md`.

## Pré-requis

```bash
git checkout -b feat/pricing-plan1
```

---

## Étape 1 — Value Objects

Créer :
- `src/Pricing/Domain/ValueObject/Money.php` — encapsule `amountCents: int` (> 0), helpers `fromEuros(float): self` et `toEuros(): float`
- `src/Pricing/Domain/ValueObject/DatePeriod.php` — `checkIn: \DateTimeImmutable`, `checkOut: \DateTimeImmutable`, lève une exception si `checkIn >= checkOut`. Namespace Pricing — pas partagé avec Availability.

## Étape 2 — Entités du domaine

Créer :
- `src/Pricing/Domain/Model/BaseRate.php` — propriétés `roomId: string`, `amountCents: int`, `updatedAt: \DateTimeImmutable`. `roomId` est la clé — pas d'`id` séparé. Plain PHP, aucune annotation Doctrine.
- `src/Pricing/Domain/Model/RatePeriod.php` — propriétés `id: string`, `roomId: string`, `checkIn: \DateTimeImmutable`, `checkOut: \DateTimeImmutable`, `amountCents: int`, `createdAt: \DateTimeImmutable`, `updatedAt: \DateTimeImmutable`. Plain PHP, aucune annotation Doctrine.

## Étape 3 — Exceptions du domaine

Créer :
- `src/Pricing/Domain/Exception/RoomNotFoundException.php`
- `src/Pricing/Domain/Exception/BaseRateNotFoundException.php`
- `src/Pricing/Domain/Exception/RatePeriodNotFoundException.php`
- `src/Pricing/Domain/Exception/RatePeriodOverlapException.php`

## Étape 4 — Interfaces (Ports)

Créer :
- `src/Pricing/Domain/Port/RoomExistsInterface.php` — méthode `exists(string $roomId): bool`
- `src/Pricing/Domain/Port/BaseRateRepositoryInterface.php` — méthodes `save(BaseRate): void`, `findByRoomId(string $roomId): ?BaseRate`
- `src/Pricing/Domain/Port/RatePeriodRepositoryInterface.php` — méthodes `save(RatePeriod): void`, `findById(string $id): ?RatePeriod`, `findByRoomId(string $roomId): array`, `hasOverlap(string $roomId, DatePeriod $period, ?string $excludeId = null): bool`, `delete(RatePeriod $ratePeriod): void`

## Étape 5 — Services applicatifs

Créer :
- `src/Pricing/Application/Service/IdGeneratorInterface.php` — méthode `generate(): string`

## Étape 6 — Use Case : SetBaseRate

**PUT** `/api/rooms/{roomId}/base-rate`

Créer :
- `src/Pricing/Application/UseCase/SetBaseRate/SetBaseRateCommand.php` — propriétés `roomId: string`, `amountCents: int`, `updatedAt: \DateTimeImmutable`
- `src/Pricing/Application/UseCase/SetBaseRate/SetBaseRateCommandHandler.php` — implémente `SyncCommandHandlerInterface`. Vérifie room exists (→ `RoomNotFoundException`), crée ou remplace BaseRate via `BaseRateRepositoryInterface::save()`
- `src/Pricing/Application/Service/SetBaseRateCommandFactory.php` — injecte `ClockInterface` (Psr\Clock), produit `SetBaseRateCommand` avec `updatedAt = clock->now()`
- `src/Pricing/UI/Http/Controller/SetBaseRate/SetBaseRateRequest.php` — DTO avec propriété `amount: float` (> 0), validé via `#[MapRequestPayload(acceptFormat: 'json')]`
- `src/Pricing/UI/Http/Controller/SetBaseRate/SetBaseRateController.php` — `final readonly`, route PUT `/api/rooms/{roomId}/base-rate`, `Requirement::UUID_V4` pour roomId. Retourne 200 avec `{ "roomId": "uuid", "amountCents": 12000, "updatedAt": <timestamp Unix> }`

## Étape 7 — Use Case : GetBaseRate

**GET** `/api/rooms/{roomId}/base-rate`

Créer :
- `src/Pricing/Application/UseCase/GetBaseRate/GetBaseRateQuery.php` — propriété `roomId: string`
- `src/Pricing/Application/UseCase/GetBaseRate/GetBaseRateQueryHandler.php` — implémente `SyncQueryHandlerInterface`. Vérifie room exists (→ `RoomNotFoundException`), cherche BaseRate (→ `BaseRateNotFoundException` si absent). Retourne BaseRate.
- `src/Pricing/UI/Http/Controller/GetBaseRate/GetBaseRateController.php` — `final readonly`, route GET. Retourne 200 avec même shape que PUT.

## Étape 8 — Use Case : CreateRatePeriod

**POST** `/api/rooms/{roomId}/rate-periods`

Créer :
- `src/Pricing/Application/UseCase/CreateRatePeriod/CreateRatePeriodCommand.php` — propriétés `id: string`, `roomId: string`, `checkIn: \DateTimeImmutable`, `checkOut: \DateTimeImmutable`, `amountCents: int`, `createdAt: \DateTimeImmutable`, `updatedAt: \DateTimeImmutable`
- `src/Pricing/Application/UseCase/CreateRatePeriod/CreateRatePeriodCommandHandler.php` — implémente `SyncCommandHandlerInterface`. Vérifie room exists (→ `RoomNotFoundException`), vérifie overlap (→ `RatePeriodOverlapException`), crée et sauvegarde RatePeriod.
- `src/Pricing/Application/Service/CreateRatePeriodCommandFactory.php` — injecte `IdGeneratorInterface` + `ClockInterface`
- `src/Pricing/UI/Http/Controller/CreateRatePeriod/CreateRatePeriodRequest.php` — DTO : `checkIn: string`, `checkOut: string`, `amount: float`
- `src/Pricing/UI/Http/Controller/CreateRatePeriod/CreateRatePeriodController.php` — `final readonly`, route POST. Retourne 201 avec `{ "id": "uuid", "roomId": "uuid", "checkIn": "...", "checkOut": "...", "amountCents": 15000, "createdAt": <timestamp Unix> }`

## Étape 9 — Use Case : GetRatePeriods

**GET** `/api/rooms/{roomId}/rate-periods`

Créer :
- `src/Pricing/Application/UseCase/GetRatePeriods/GetRatePeriodsQuery.php` — propriété `roomId: string`
- `src/Pricing/Application/UseCase/GetRatePeriods/GetRatePeriodsQueryHandler.php` — implémente `SyncQueryHandlerInterface`. Retourne la liste triée par `checkIn` ASC.
- `src/Pricing/UI/Http/Controller/GetRatePeriods/GetRatePeriodsController.php` — `final readonly`, route GET. Retourne 200 avec `{ "ratePeriods": [...] }`.

## Étape 10 — Use Case : UpdateRatePeriod

**PUT** `/api/rooms/{roomId}/rate-periods/{ratePeriodId}`

Créer :
- `src/Pricing/Application/UseCase/UpdateRatePeriod/UpdateRatePeriodCommand.php` — propriétés `ratePeriodId: string`, `roomId: string`, `checkIn: \DateTimeImmutable`, `checkOut: \DateTimeImmutable`, `amountCents: int`, `updatedAt: \DateTimeImmutable`
- `src/Pricing/Application/UseCase/UpdateRatePeriod/UpdateRatePeriodCommandHandler.php` — implémente `SyncCommandHandlerInterface`. Cherche RatePeriod (→ `RatePeriodNotFoundException`), vérifie overlap en excluant lui-même, sauvegarde.
- `src/Pricing/Application/Service/UpdateRatePeriodCommandFactory.php` — injecte `ClockInterface`
- `src/Pricing/UI/Http/Controller/UpdateRatePeriod/UpdateRatePeriodRequest.php` — même shape que CreateRatePeriodRequest
- `src/Pricing/UI/Http/Controller/UpdateRatePeriod/UpdateRatePeriodController.php` — `final readonly`, route PUT. Retourne 200 avec la même shape que le POST 201 (incluant `createdAt`).

## Étape 11 — Use Case : DeleteRatePeriod

**DELETE** `/api/rooms/{roomId}/rate-periods/{ratePeriodId}`

Créer :
- `src/Pricing/Application/UseCase/DeleteRatePeriod/DeleteRatePeriodCommand.php` — propriété `ratePeriodId: string`
- `src/Pricing/Application/UseCase/DeleteRatePeriod/DeleteRatePeriodCommandHandler.php` — implémente `SyncCommandHandlerInterface`. Cherche RatePeriod (→ `RatePeriodNotFoundException`), supprime.
- `src/Pricing/UI/Http/Controller/DeleteRatePeriod/DeleteRatePeriodController.php` — `final readonly`, route DELETE. Retourne 204.

## Étape 12 — Infrastructure

Créer :
- `src/Pricing/Infrastructure/Persistence/Doctrine/DoctrineBaseRateRepository.php` — DBAL connection `$bookit`, raw SQL. `room_id` est la PK (pas d'`id` séparé). Implémente `BaseRateRepositoryInterface`.
- `src/Pricing/Infrastructure/Persistence/Doctrine/DoctrineRatePeriodRepository.php` — DBAL connection `$bookit`, raw SQL. Overlap check SQL : `SELECT COUNT(*) FROM pricing_rate_period WHERE room_id = :roomId AND check_in < :checkOut AND check_out > :checkIn [AND id != :excludeId]`. Implémente `RatePeriodRepositoryInterface`.
- `src/Pricing/Infrastructure/Persistence/Doctrine/RoomExistenceChecker.php` — délègue à `RoomRepositoryInterface` du contexte Room. Même pattern que `src/Availability/Infrastructure/Persistence/Doctrine/RoomExistenceChecker.php`. Implémente `RoomExistsInterface`.
- `src/Pricing/Infrastructure/Service/UuidIdGenerator.php` — `return Uuid::v4()->toString()`. Implémente `IdGeneratorInterface`.

## Étape 13 — Migration Doctrine

Générer une nouvelle migration (`make doctrine:migrations:generate`) contenant :

```sql
CREATE TABLE pricing_base_rate (
    room_id UUID NOT NULL,
    amount_cents INT NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (room_id)
);

CREATE TABLE pricing_rate_period (
    id UUID NOT NULL,
    room_id UUID NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    amount_cents INT NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
);
CREATE INDEX idx_pricing_rate_period_room_id ON pricing_rate_period (room_id);
```

## Étape 14 — Configuration DI

Créer `config/services/pricing.yaml` — miroir exact de `config/services/availability.yaml` :
- `_instanceof` pour `SyncCommandHandlerInterface` (tagged `sync.command.bus`) et `SyncQueryHandlerInterface` (tagged `sync.query.bus`)
- Wiring de tous les services : repositories (interface → implémentation), `RoomExistenceChecker` (implémente `RoomExistsInterface`), `UuidIdGenerator` (implémente `IdGeneratorInterface`), toutes les factories (avec leurs dépendances `ClockInterface`, `IdGeneratorInterface`)

Modifier `config/services/exceptions.yaml` — ajouter les 4 mappings :
```yaml
App\Pricing\Domain\Exception\RoomNotFoundException:
    type: 'https://book.it/problems/room-not-found'
    title: 'Room Not Found'
    status: 404
App\Pricing\Domain\Exception\BaseRateNotFoundException:
    type: 'https://book.it/problems/base-rate-not-found'
    title: 'Base Rate Not Found'
    status: 404
App\Pricing\Domain\Exception\RatePeriodNotFoundException:
    type: 'https://book.it/problems/rate-period-not-found'
    title: 'Rate Period Not Found'
    status: 404
App\Pricing\Domain\Exception\RatePeriodOverlapException:
    type: 'https://book.it/problems/rate-period-overlap'
    title: 'Rate Period Overlap'
    status: 409
```

## Étape 15 — Tests unitaires (`#[Group('unit')]`)

Créer les helpers partagés dans `tests/Pricing/Application/` :
- `InMemoryBaseRateRepository.php` — implémente `BaseRateRepositoryInterface`, stocke en tableau
- `InMemoryRatePeriodRepository.php` — implémente `RatePeriodRepositoryInterface`, stocke en tableau. `hasOverlap` calculé en PHP.
- `FakeRoomExistenceChecker.php` — implémente `RoomExistsInterface`, liste blanche configurable

Créer un test par handler (miroir de `tests/Availability/`) :
- `tests/Pricing/Application/UseCase/SetBaseRate/SetBaseRateCommandHandlerTest.php` — cas : crée, remplace, room inexistante (→ exception)
- `tests/Pricing/Application/UseCase/GetBaseRate/GetBaseRateQueryHandlerTest.php` — cas : trouvé, room inexistante, pas de base rate
- `tests/Pricing/Application/UseCase/CreateRatePeriod/CreateRatePeriodCommandHandlerTest.php` — cas : créé, room inexistante, overlap
- `tests/Pricing/Application/UseCase/GetRatePeriods/GetRatePeriodsQueryHandlerTest.php` — cas : liste vide, liste triée par checkIn ASC
- `tests/Pricing/Application/UseCase/UpdateRatePeriod/UpdateRatePeriodCommandHandlerTest.php` — cas : modifié, not found, overlap avec exclusion self
- `tests/Pricing/Application/UseCase/DeleteRatePeriod/DeleteRatePeriodCommandHandlerTest.php` — cas : supprimé, not found

Vérification : `make test-unit` passe.

## Étape 16 — Tests fonctionnels (`#[Group('functional')]`)

Chaque classe de test étend `WebTestCase`, utilise `dama/doctrine-test-bundle`.

Helper requis dans chaque classe :
```php
private function registerRoomAndGetId(KernelBrowser $client): string
```
Copie le pattern de `BlockPeriodControllerTest::registerRoomAndGetId()`.

Tests à créer (golden path + cas d'erreur) :
- `tests/Pricing/UI/Http/Controller/SetBaseRate/SetBaseRateControllerTest.php` — PUT 200, 404 room, 422 validation
- `tests/Pricing/UI/Http/Controller/GetBaseRate/GetBaseRateControllerTest.php` — GET 200, 404 room, 404 no base rate
- `tests/Pricing/UI/Http/Controller/CreateRatePeriod/CreateRatePeriodControllerTest.php` — POST 201, 404 room, 409 overlap, 422 validation
- `tests/Pricing/UI/Http/Controller/GetRatePeriods/GetRatePeriodsControllerTest.php` — GET 200 liste vide, GET 200 liste triée
- `tests/Pricing/UI/Http/Controller/UpdateRatePeriod/UpdateRatePeriodControllerTest.php` — PUT 200, 404 not found, 409 overlap, 422 validation
- `tests/Pricing/UI/Http/Controller/DeleteRatePeriod/DeleteRatePeriodControllerTest.php` — DELETE 204, 404 not found

Vérification : `make test-functional` passe.

## Étape 17 — OpenAPI

```bash
make openapi
```

Vérifier que les 6 endpoints apparaissent dans `openapi.yaml`.

# Booker Shared Contract — Design Spec

**Date:** 2026-05-29
**Scope:** Reservation context only (Notification excluded for now)

## Problem

`Reservation\Infrastructure\Service\BookerExistenceChecker` implémente le port
`Reservation\Domain\Port\BookerExistsInterface` mais le fait en injectant
`SyncQueryBusInterface` et en instanciant `Booker\Application\UseCase\GetBooker\GetBookerQuery`.

Cela crée une dépendance directe de `Reservation\Infrastructure` vers `Booker\Application` :
le contexte Reservation connaît l'existence du contexte Booker et sa structure interne.

## Solution

Introduire un contrat partagé dans `App\Shared\Domain\Port\BookerProviderInterface`,
implémenté par `Booker\Infrastructure` et consommé par `Reservation\Infrastructure`.

Le contexte Reservation n'importe plus aucune classe du contexte Booker.

## Architecture

```
Reservation\Application
    → Reservation\Domain\Port\BookerExistsInterface   (port, inchangé)
              ↑ implements
Reservation\Infrastructure\Service\BookerExistenceChecker
    → App\Shared\Domain\Port\BookerProviderInterface   (contrat partagé)
              ↑ implements
Booker\Infrastructure\Service\BookerProvider
    → SyncQueryBusInterface + GetBookerQuery           (même contexte, autorisé)
              ↑ handled by
Booker\Application\UseCase\GetBooker\GetBookerQueryHandler
```

### Règles deptrac respectées

- `Reservation\Infrastructure` → `Shared\Domain` : autorisé
- `Booker\Infrastructure` → `Booker\Application` : autorisé (même contexte)
- `Reservation\Infrastructure` → `Booker\*` : supprimé

## Fichiers à créer ou modifier

| Fichier | Action |
|---|---|
| `src/Shared/Domain/Port/BookerProviderInterface.php` | Créer |
| `src/Booker/Infrastructure/Service/BookerProvider.php` | Créer |
| `config/services/booker.yaml` | Ajouter alias d'interface |
| `src/Reservation/Infrastructure/Service/BookerExistenceChecker.php` | Modifier |
| `tests/Booker/Infrastructure/Service/BookerProviderTest.php` | Créer |
| `tests/Reservation/Infrastructure/Service/BookerExistenceCheckerTest.php` | Créer |

## Détail des composants

### `BookerProviderInterface`

```php
// App\Shared\Domain\Port\BookerProviderInterface
interface BookerProviderInterface
{
    public function exists(string $bookerId): bool;
}
```

Interface minimale (YAGNI). Si Notification doit un jour en bénéficier, on étend
ou on crée une seconde interface selon le besoin.

### `BookerProvider`

```php
// App\Booker\Infrastructure\Service\BookerProvider
final readonly class BookerProvider implements BookerProviderInterface
{
    public function __construct(private SyncQueryBusInterface $queryBus) {}

    public function exists(string $bookerId): bool
    {
        return null !== $this->queryBus->ask(new GetBookerQuery($bookerId));
    }
}
```

Appel via le query bus (pas le repository directement) pour respecter le layering
interne du contexte Booker.

### `BookerExistenceChecker` (modifié)

```php
// App\Reservation\Infrastructure\Service\BookerExistenceChecker
final readonly class BookerExistenceChecker implements BookerExistsInterface
{
    public function __construct(private BookerProviderInterface $bookerProvider) {}

    public function exists(string $bookerId): bool
    {
        return $this->bookerProvider->exists($bookerId);
    }
}
```

### Câblage DI (`booker.yaml`)

```yaml
App\Shared\Domain\Port\BookerProviderInterface:
    '@App\Booker\Infrastructure\Service\BookerProvider'
```

Déclaré dans `booker.yaml` car c'est le contexte Booker qui fournit l'implémentation.
Aucun changement dans `reservation.yaml`.

## Tests

### Ce qui ne change pas

- `FakeBookerExistenceChecker` — mocke `BookerExistsInterface`, inchangé
- `CreateReservationCommandHandlerTest` — utilise le fake ci-dessus, inchangé

### Nouveaux tests unitaires

**`BookerProviderTest`** (`tests/Booker/Infrastructure/Service/`) :
- mocke `SyncQueryBusInterface`
- vérifie `exists()` retourne `true` quand le bus retourne un `Booker`
- vérifie `exists()` retourne `false` quand le bus retourne `null`

**`BookerExistenceCheckerTest`** (`tests/Reservation/Infrastructure/Service/`) :
- mocke `BookerProviderInterface`
- vérifie que `exists()` délègue au provider et retourne son résultat

## Ce qui n'est pas traité

- `Notification\Infrastructure\Service\BookerContactFetcher` — même problème,
  exclu du périmètre de ce spec. À traiter dans un spec séparé si nécessaire.

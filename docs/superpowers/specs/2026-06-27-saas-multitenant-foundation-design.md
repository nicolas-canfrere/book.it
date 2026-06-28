# SaaS — Fondation multi-tenant (Sous-projet 1/4)

**Date :** 2026-06-27
**Statut :** Approuvé
**Auteur :** Nicolas Canfrere

## Contexte & objectif

book.it est actuellement une application mono-tenant : tous les Hotels, Operators et Reservations partagent le même espace de données sans isolation. L'objectif est de transformer l'application en **marketplace hôtelière** (modèle Booking.com) où chaque hôtel ou chaîne hôtelière est un tenant isolé.

Ce document couvre le **sous-projet 1 : fondation multi-tenant**. Les sous-projets suivants (onboarding self-service, flux booker public, commission & facturation) en dépendent et feront l'objet de specs séparées.

### Modèle économique retenu

- Marketplace : la plateforme est intermédiaire entre Hotels (fournisseurs) et Bookers (voyageurs)
- Revenu : commission sur réservation — les Hotels collectent le paiement des Bookers directement, la plateforme facture sa commission mensuellement
- Onboarding : self-service (un hôtel s'inscrit lui-même)

---

## Architecture générale

### Nouveau bounded context : `Organization`

`Organization` est le **tenant root**. Une Organization possède N Hotels. Un hôtel indépendant qui s'inscrit crée automatiquement une Organization avec 1 Hotel.

```
Organization
  └── OrganizationId          ← devient le TenantId partagé dans tout le système
  └── OrganizationName        ← Value Object
  └── OrganizationEmail       ← Value Object
  └── OrganizationStatus      ← enum: pending | active | suspended
  └── registeredAt
```

`Hotel` et `Operator` reçoivent une référence à `OrganizationId` :

```
Hotel    + organizationId : OrganizationId
Operator + organizationId : OrganizationId
         + role : OperatorRole (owner | manager | staff)
```

### Contextes cross-tenant (délibérément non-scoped)

| Contexte | Raison |
|----------|--------|
| `Booker` | Un voyageur peut réserver dans n'importe quel hôtel |
| `Geo` | Les lieux géographiques sont partagés |
| `Search` | La recherche publique traverse tous les tenants |
| `Notification` | Envoyées par la plateforme, pas par un tenant |

`Reservation` est indirectement scoped via sa référence à `RoomId` (qui appartient à un Hotel scoped). Pas de colonne `organization_id` directe en V1 — la jointure avec `rooms` suffit.

---

## Isolation des données

### Approche : trait `TenantScopeAware`

L'application utilise **Doctrine DBAL** (pas l'ORM). Les SQL Filters ORM ne sont pas disponibles. L'isolation est donc **explicite au niveau de chaque repository** via un trait partagé.

```php
// Shared\Infrastructure\Persistence\TenantScopeAware
trait TenantScopeAware
{
    private TenantContext $tenantContext;

    private function applyTenantScope(
        QueryBuilder $qb,
        string $tableAlias = 't'
    ): QueryBuilder {
        return $qb
            ->andWhere("{$tableAlias}.organization_id = :tenant_id")
            ->setParameter('tenant_id', $this->tenantContext->getOrganizationId()->value);
    }
}
```

Chaque repository Infrastructure scoped l'utilise explicitement :

```php
// Exemple : Hotel\Infrastructure\Persistence\DoctrineHotelRepository
public function findById(HotelId $id): ?Hotel
{
    $qb = $this->connection->createQueryBuilder()
        ->select('h.*')->from('hotels', 'h')
        ->where('h.id = :id')->setParameter('id', $id->value);

    $this->applyTenantScope($qb, 'h');
    // ...
}
```

L'appel à `applyTenantScope()` est **visible dans chaque méthode** — aucune magie, auditables en relecture, oublis immédiatement détectables.

Les repositories cross-tenant (`DoctrineBookerRepository`, `DoctrineGeoRepository`, etc.) n'utilisent pas le trait — c'est la marque architecturale qu'ils sont délibérément hors isolation.

---

## TenantContext

```php
// Shared\Application\TenantContext
final class TenantContext
{
    private ?OrganizationId $organizationId = null;

    public function set(OrganizationId $id): void
    {
        $this->organizationId = $id;
    }

    public function getOrganizationId(): OrganizationId
    {
        if (null === $this->organizationId) {
            throw new TenantContextNotInitializedException();
        }
        return $this->organizationId;
    }

    public function isInitialized(): bool
    {
        return null !== $this->organizationId;
    }
}
```

Enregistré comme service Symfony normal (singleton par requête via le cycle de vie de Symfony).

### TenantContextMiddleware

`EventSubscriber` sur `KernelRequest` :

```php
// Shared\Infrastructure\Http\TenantContextMiddleware
public function onKernelRequest(RequestEvent $event): void
{
    $token = $this->tokenStorage->getToken();
    if (null === $token || !$token->getUser() instanceof OperatorUser) {
        return; // routes publiques — TenantContext reste vide
    }

    $organizationId = $token->getAttribute('organization_id');
    if (null !== $organizationId) {
        $this->tenantContext->set(new OrganizationId($organizationId));
    }
}
```

### Defense in depth

Le trait DBAL est la ligne de défense principale. Les use cases critiques (modifier un Hotel, une Room, un tarif) ajoutent une assertion explicite :

```php
if (!$hotel->organizationId->equals($this->tenantContext->getOrganizationId())) {
    throw new AccessDeniedException();
}
```

---

## JWT & Keycloak

Les JWTs sont émis par **Keycloak**. Le claim `organization_id` est injecté par Keycloak via un **User Attribute + Protocol Mapper**.

### Configuration Keycloak

```
Client → Client Scopes → book-it-scope → Mappers → Add Mapper
  Type:                User Attribute
  User Attribute:      organization_id
  Token Claim Name:    organization_id
  Claim JSON Type:     String
  Add to access token: ON
  Add to ID token:     ON
```

### Écriture de l'attribut Keycloak

Le contexte `Security` écrit l'attribut lors de la création du compte Operator, via l'Admin REST API Keycloak :

```php
// Security\Infrastructure\Keycloak\KeycloakUserManager
public function register(string $email, OrganizationId $organizationId): void
{
    $userId = $this->keycloakAdminClient->createUser($email);
    $this->keycloakAdminClient->setUserAttribute(
        userId: $userId,
        attribute: 'organization_id',
        value: $organizationId->value,
    );
}
```

Déclenché par l'événement `OrganizationRegistered` (écouté par `Security`).

### Suspension d'une Organization

Quand `OrganizationSuspended` est publié, `Security` :
1. Désactive l'utilisateur Keycloak (`enabled: false`) — Keycloak refuse les nouveaux logins
2. Révoque les sessions actives via `DELETE /admin/realms/{realm}/users/{userId}/sessions`

Les tokens déjà émis restent valides jusqu'à expiration (TTL court recommandé : 15 min). Pour une révocation immédiate, activer la Token Introspection Keycloak côté Symfony.

### Flux complet

```
Request → Symfony Firewall (JWT auth via Keycloak)
        → TenantContextMiddleware (set OrganizationId depuis claim)
        → Controller → UseCase → Repository
                                  └── applyTenantScope() — filtre SQL
```

---

## Contexte `Organization` — structure

```
src/Organization/
  Domain/
    Model/
      Organization.php
      OrganizationStatus.php        # enum: pending | active | suspended
    Port/
      OrganizationRepositoryInterface.php
    Exception/
      OrganizationNotFoundException.php
      OrganizationAlreadyExistsException.php
  Application/
    UseCase/
      RegisterOrganization/
        RegisterOrganizationCommand.php
        RegisterOrganizationHandler.php
      ActivateOrganization/
        ActivateOrganizationCommand.php
        ActivateOrganizationHandler.php
      SuspendOrganization/
        SuspendOrganizationCommand.php
        SuspendOrganizationHandler.php
    Contract/
      OrganizationCheckerInterface.php
      OrganizationView.php
  Infrastructure/
    Persistence/
      DoctrineOrganizationRepository.php
    Contract/
      DoctrineOrganizationChecker.php
  UI/
    Http/
      RegisterOrganizationController.php
```

### Value Objects (dans `Shared\Domain\ValueObject\`)

| Value Object | Validation |
|-------------|------------|
| `OrganizationId` | UUID v4 |
| `OrganizationName` | non vide, max 255 chars |
| `OrganizationEmail` | format email valide |

### Événements de domaine (dans `Shared\Domain\Event\`)

```
OrganizationRegistered { organizationId, contactEmail, registeredAt }
OrganizationSuspended  { organizationId, suspendedAt }
```

`OrganizationRegistered` → `Security` crée le compte Operator+Keycloak.
`OrganizationSuspended` → `Security` désactive le compte Keycloak.

---

## Schéma & migration

### Nouvelle table

```sql
CREATE TABLE organizations (
    id            UUID         PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    status        VARCHAR(20)  NOT NULL DEFAULT 'pending',
    registered_at TIMESTAMPTZ  NOT NULL
);
```

### Colonnes ajoutées

```sql
ALTER TABLE hotels
    ADD COLUMN organization_id UUID NOT NULL REFERENCES organizations(id);

ALTER TABLE operators
    ADD COLUMN organization_id UUID NOT NULL REFERENCES organizations(id),
    ADD COLUMN role            VARCHAR(20) NOT NULL DEFAULT 'owner';
```

### Migration des données existantes

La migration Doctrine crée une Organization de migration et assigne toutes les données existantes de façon atomique :

```sql
INSERT INTO organizations (id, name, contact_email, status, registered_at)
VALUES (
    '00000000-0000-0000-0000-000000000001',
    'Default Organization',
    'admin@book.it',
    'active',
    NOW()
);

UPDATE hotels    SET organization_id = '00000000-0000-0000-0000-000000000001';
UPDATE operators SET organization_id = '00000000-0000-0000-0000-000000000001';
```

Pas de nullable temporaire — colonnes `NOT NULL` dès le départ, migration atomique.

---

## Deptrac

### `deptrac-contexts.yaml` — ajouts

```yaml
layers:
  - name: Organization
    collectors:
      - type: directory
        value: src/Organization/

  - name: OrganizationContract
    collectors:
      - type: directory
        value: src/Organization/Application/Contract/

ruleset:
  Security:
    - OrganizationContract
  Hotel:
    - Shared
  Operator:
    - Shared
```

Aucun contexte n'importe directement `Organization` — le couplage passe uniquement par `Shared\Domain\Event\` et `Shared\Domain\ValueObject\OrganizationId`.

---

## Tests

### Priorité absolue : test d'isolation en intégration

```php
#[Group('integration')]
final class HotelTenantIsolationTest extends KernelTestCase
{
    public function test_repository_only_returns_own_organization_hotels(): void
    {
        $org1 = OrganizationId::fromString('org-aaa');
        $org2 = OrganizationId::fromString('org-bbb');

        // fixtures : 1 hotel par org en base

        $this->tenantContext->set($org1);
        $hotels = $this->hotelRepository->findAll();

        self::assertCount(1, $hotels);
        self::assertTrue($hotels[0]->organizationId->equals($org1));
    }
}
```

### Couverture complète

| Niveau | Ce qui est testé |
|--------|-----------------|
| Unitaire | `TenantScopeAware` trait avec QueryBuilder mocké |
| Unitaire | `Organization` domain : `activate()`, `suspend()` |
| Unitaire | Value Objects : `OrganizationName`, `OrganizationEmail` (validation) |
| Intégration | Isolation : org A ne voit pas les données de org B |
| Intégration | `TenantContextNotInitializedException` sur endpoint scoped sans JWT |
| Fonctionnel | JWT sans `organization_id` → 403 sur endpoint scoped |
| Fonctionnel | JWT org A + UUID hôtel org B → 403 ou 404 |

---

## Ce qui n'est pas couvert ici (sous-projets suivants)

| Sujet | Sous-projet |
|-------|-------------|
| Inscription self-service hôtel (UI + flow complet) | 2 — Onboarding |
| Recherche publique cross-tenant pour bookers | 3 — Flux booker public |
| Calcul et facturation des commissions | 4 — Commission & facturation |
| Plans tarifaires / feature flags par tenant | Hors périmètre V1 |

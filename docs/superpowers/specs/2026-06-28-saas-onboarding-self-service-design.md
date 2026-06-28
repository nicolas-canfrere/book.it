# SaaS — Onboarding self-service (Sous-projet 2/4)

**Date :** 2026-06-28
**Statut :** Approuvé
**Auteur :** Nicolas Canfrere
**Dépend de :** `docs/superpowers/specs/2026-06-27-saas-multitenant-foundation-design.md`

## Contexte & objectif

Le sous-projet 1 a posé la fondation multi-tenant : contexte `Organization`, `TenantContext`, isolation DBAL. Le sous-projet 2 construit le **flow d'inscription self-service** : un hôtelier remplit un formulaire et obtient une organisation + un compte owner en une seule requête HTTP publique (sans JWT).

L'organisation démarre en statut `pending` ; un admin l'active manuellement via le `ActivateOrganizationHandler` existant.

---

## Endpoint public

```
POST /api/v1/onboarding
Content-Type: application/json
Authorization: aucune (route PUBLIC_ACCESS)
```

**Body :**
```json
{
  "organizationName": "Hôtel Bellevue",
  "contactEmail": "owner@bellevue.com",
  "ownerFirstName": "Alice",
  "ownerLastName": "Martin",
  "ownerPhone": "+33612345678",
  "password": "MySecurePassword123!"
}
```

**Réponses :**

| Code | Description |
|------|-------------|
| 201 | `{ "organizationId": "uuid", "operatorId": "uuid" }` |
| 409 | Email déjà utilisé (Organization ou Operator) |
| 422 | Erreur de validation (RFC 7807 + `violations`) |

---

## Architecture — nouveau contexte `Onboarding`

`Onboarding` est un contexte d'orchestration pur : il n'a pas de modèle de domaine propre. Il utilise deux contrats publiés (Organization + Operator) via ses ports d'application.

```
src/Onboarding/
  Application/
    Port/
      OrganizationRegistrarInterface.php
      OwnerOperatorRegistrarInterface.php
    UseCase/
      OnboardOrganization/
        OnboardOrganizationCommand.php
        OnboardOrganizationHandler.php
  Infrastructure/
    Adapter/
      OrganizationRegistrarAdapter.php
      OwnerOperatorRegistrarAdapter.php
  UI/
    Http/
      OnboardOrganizationController.php
      OnboardOrganizationRequest.php
```

### Flux d'exécution

```
POST /api/v1/onboarding
  → OnboardOrganizationHandler
      1. OrganizationRegistrarInterface.register(orgId, name, email, now)
         └── crée Organization (status: pending) + dispatche OrganizationRegistered
      2. OwnerOperatorRegistrarInterface.registerOwner(operatorId, firstName, lastName, email, phone, password, orgId, now)
         ├── AccountRegistrar.register(operatorId, 'operator', email, password) ← Keycloak
         ├── AccountRegistrar.setOrganizationId(operatorId, 'operator', orgId)  ← claim JWT
         └── OperatorRepository.add(Operator(..., orgId, role: Owner))
```

**Compensation :** si l'étape 2 échoue après la création Keycloak mais avant la persistance Operator → `AccountRegistrar.unregister(operatorId, 'operator')`.

---

## Ports d'application (`Onboarding\Application\Port\`)

### `OrganizationRegistrarInterface`

```php
interface OrganizationRegistrarInterface
{
    public function register(
        string $organizationId,
        string $name,
        string $contactEmail,
        \DateTimeImmutable $registeredAt,
    ): void;
}
```

### `OwnerOperatorRegistrarInterface`

```php
interface OwnerOperatorRegistrarInterface
{
    public function registerOwner(
        string $operatorId,
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $password,
        string $organizationId,
        \DateTimeImmutable $registeredAt,
    ): void;
}
```

---

## Contrats publiés des contextes producteurs

### `Organization\Application\Contract\OrganizationRegistrarInterface` (nouveau)

Même signature que le port Onboarding. Implémenté par `Organization\Infrastructure\Contract\DoctrineOrganizationRegistrar` (délègue à `OrganizationRepositoryInterface` + `EventDispatcherInterface`).

### `Operator\Application\Contract\OwnerOperatorRegistrarInterface` (nouveau)

Même signature que le port Onboarding. Implémenté par `Operator\Infrastructure\Contract\DoctrineOwnerOperatorRegistrar` qui :
1. Appelle `AccountRegistrarInterface.register(operatorId, 'operator', email, password)` → Keycloak
2. Appelle `AccountRegistrarInterface.setOrganizationId(operatorId, 'operator', orgId)` → User Attribute Keycloak
3. Sauvegarde l'`Operator` en base (`role: Owner`, `organizationId` explicite — pas de TenantContext)
4. Compensation si (3) échoue : `AccountRegistrarInterface.unregister(operatorId, 'operator')`

---

## Extension de `Security\Application\Contract\AccountRegistrarInterface`

Ajout d'une méthode :

```php
public function setOrganizationId(string $internalId, string $context, string $organizationId): void;
```

`KeycloakAccountRegistrar` l'implémente :
1. Récupère le `keycloakId` via `IdentityMappingRepository.findExternalId($internalId, $context)`
2. Appelle `KeycloakHttpClient.setUserAttribute($keycloakId, 'organization_id', $organizationId)`

---

## Adapters Onboarding (Infrastructure)

### `OrganizationRegistrarAdapter`

Implémente `Onboarding\Application\Port\OrganizationRegistrarInterface` en déléguant à `Organization\Application\Contract\OrganizationRegistrarInterface`.

### `OwnerOperatorRegistrarAdapter`

Implémente `Onboarding\Application\Port\OwnerOperatorRegistrarInterface` en déléguant à `Operator\Application\Contract\OwnerOperatorRegistrarInterface`.

---

## `OnboardOrganizationHandler`

```php
final readonly class OnboardOrganizationHandler implements SyncCommandHandlerInterface
{
    public function __construct(
        private OrganizationRegistrarInterface $organizationRegistrar,
        private OwnerOperatorRegistrarInterface $ownerRegistrar,
    ) {}

    public function __invoke(OnboardOrganizationCommand $command): void
    {
        $this->organizationRegistrar->register(
            $command->organizationId,
            $command->organizationName,
            $command->contactEmail,
            $command->registeredAt,
        );

        $this->ownerRegistrar->registerOwner(
            $command->operatorId,
            $command->ownerFirstName,
            $command->ownerLastName,
            $command->contactEmail,
            $command->ownerPhone,
            $command->password,
            $command->organizationId,
            $command->registeredAt,
        );
    }
}
```

---

## Exceptions et mappings HTTP

À ajouter dans `config/services/exceptions.yaml` :

| Exception | type | status |
|-----------|------|--------|
| `App\Onboarding\Application\Exception\OnboardingConflictException` | `https://book.it/problems/onboarding-conflict` | 409 |

`OnboardingConflictException` est lancée par le handler si l'email est déjà utilisé (vérifié dans `DoctrineOrganizationRegistrar` et `DoctrineOwnerOperatorRegistrar`).

---

## Deptrac

### Ajouts dans `deptrac-contexts.yaml`

```yaml
layers:
  - name: Onboarding
    collectors:
      - type: bool
        must:
          - type: classLike
            value: 'App\\Onboarding\\.*'

  - name: OrganizationContract
    collectors:
      - type: classLike
        value: 'App\\Organization\\Application\\Contract\\.*'

  - name: OperatorContract
    collectors:
      - type: classLike
        value: 'App\\Operator\\Application\\Contract\\.*'

ruleset:
  Onboarding:
    - OrganizationContract
    - OperatorContract
    - Shared
```

Note : `OrganizationContract` et `OperatorContract` existent déjà partiellement ; vérifier qu'ils ne doublonnent pas.

---

## Configuration Symfony

Nouveau fichier `config/services/onboarding.yaml` :

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true

  _instanceof:
    App\Shared\Application\Bus\SyncCommandHandlerInterface:
      tags:
        - { name: messenger.message_handler, bus: sync.bus }

  App\Onboarding\:
    resource: '../src/Onboarding/'
```

---

## Tests

| Niveau | Scénario |
|--------|----------|
| Unitaire | `OnboardOrganizationHandler` : stub des deux ports, vérifie les appels dans l'ordre |
| Unitaire | `DoctrineOwnerOperatorRegistrar` : Keycloak mock, compensation si DB échoue |
| Fonctionnel | `POST /api/v1/onboarding` → 201, Organization en `pending`, Operator en base |
| Fonctionnel | Email déjà utilisé → 409 |
| Fonctionnel | Champs manquants → 422 + `violations` |

---

## Ce qui n'est pas couvert ici

| Sujet | Sous-projet |
|-------|-------------|
| Recherche publique cross-tenant | 3 — Flux booker public |
| Activation automatique par email | Hors V1 |
| Invitation de collaborateurs (manager/staff) | Hors V1 |
| Commission & facturation | 4 |

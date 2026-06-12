# Operator JWT Auth — BearerTokenAuthenticator (book.it)

## Contexte

Toutes les routes back office de `book.it` doivent exiger un JWT Keycloak valide transmis par `bo.book.it` en `Authorization: Bearer`. Le JWT est émis par Keycloak en RS256 et validé côté Symfony sans état de session.

## Périmètre

- Toutes les routes sont sécurisées (un seul firewall `api`, stateless)
- Tous les opérateurs authentifiés obtiennent `ROLE_OPERATOR` — pas de rôle fin
- Aucune modification des bounded contexts métier (Hotel, Room, Availability…)
- La couche Domain reste inchangée

## Approche retenue

**Custom `BearerTokenAuthenticator`** + `firebase/php-jwt` pour la validation RS256.

Alternatives écartées :
- `lexik/jwt-authentication-bundle` : conçu pour des tokens qu'il génère lui-même ; utilisation avec Keycloak nécessite un contournement non naturel et ne gère pas le JWKS dynamique
- Bundle Keycloak tiers : sur-ingénierie, dépendance externe non maîtrisée

## Architecture

```
[Requête HTTP]
    │  Authorization: Bearer <jwt>
    ▼
[BearerTokenAuthenticator]
    │  supports() : header présent ?
    │  authenticate() :
    │    1. Extrait le token
    │    2. KeycloakJwksProvider → clé publique RSA (cache 1h)
    │    3. firebase/php-jwt → valide RS256, expiry, issuer
    │    4. SelfValidatingPassport(UserBadge) → ROLE_OPERATOR
    ▼
[Firewall Symfony] → accès autorisé → Controller
```

## Fichiers

### Nouveaux

| Fichier | Rôle |
|---------|------|
| `src/Security/Infrastructure/Keycloak/KeycloakJwksProvider.php` | Fetch JWKS Keycloak, parse clé publique RSA, met en cache |
| `src/Security/Infrastructure/Keycloak/BearerTokenAuthenticator.php` | Authenticator Symfony Security |

### Modifiés

| Fichier | Modification |
|---------|-------------|
| `config/packages/security.yaml` | Firewall `api` stateless avec `BearerTokenAuthenticator`, `access_control` |
| `config/services/security.yaml` | Wiring `KeycloakJwksProvider` (env vars, cache) |

## KeycloakJwksProvider

```
Endpoint JWKS : {KEYCLOAK_BASE_URL}/realms/{KEYCLOAK_REALM}/protocol/openid-connect/certs
Cache         : CacheInterface (PSR-6), clé = "keycloak_jwks", TTL = 3600s
Retour        : clé publique RSA prête pour firebase/php-jwt
```

- Appel HTTP via `HttpClientInterface` de Symfony
- En cas d'échec JWKS (Keycloak indisponible) → `AuthenticationException` → 401
- Le cache évite un appel réseau à chaque requête

## BearerTokenAuthenticator

Implémente `AuthenticatorInterface` de Symfony Security :

```
supports()     : Authorization header commence par "Bearer "
authenticate() :
  - Extrait le token brut
  - Appelle KeycloakJwksProvider pour obtenir la clé publique
  - firebase/php-jwt::decode(token, key, ['RS256'])
  - Vérifie issuer = "{KEYCLOAK_BASE_URL}/realms/{KEYCLOAK_REALM}"
  - Retourne SelfValidatingPassport(new UserBadge($sub))
onSuccess()    : null (stateless, pas de redirection)
onFailure()    : JsonResponse 401 {"error": "Unauthorized"}
```

L'identité Symfony est un `InMemoryUser` avec `ROLE_OPERATOR`. Aucun User en base de données n'est consulté.

## security.yaml

```yaml
security:
  providers:
    operators:
      memory: ~

  firewalls:
    dev:
      pattern: ^/(_profiler|_wdt|assets|build)/
      security: false
    api:
      pattern: ^/
      stateless: true
      provider: operators
      custom_authenticators:
        - App\Security\Infrastructure\Keycloak\BearerTokenAuthenticator

  access_control:
    - { path: ^/, roles: ROLE_OPERATOR }
```

## services/security.yaml — ajouts

```yaml
App\Security\Infrastructure\Keycloak\KeycloakJwksProvider:
    arguments:
        $keycloakBaseUrl: '%env(KEYCLOAK_BASE_URL)%'
        $keycloakRealm: '%env(KEYCLOAK_REALM)%'
        $cache: '@cache.app'

App\Security\Infrastructure\Keycloak\BearerTokenAuthenticator:
    arguments:
        $jwksProvider: '@App\Security\Infrastructure\Keycloak\KeycloakJwksProvider'
        $keycloakIssuer: '%env(KEYCLOAK_BASE_URL)%/realms/%env(KEYCLOAK_REALM)%'
```

## Variables d'environnement

Les vars suivantes existent déjà dans le projet :

```env
KEYCLOAK_BASE_URL=http://keycloak:8080   # déjà présente
KEYCLOAK_REALM=bookit                      # à ajouter
```

## Dépendance à ajouter

```bash
docker compose exec php composer require firebase/php-jwt
```

## Architecture layer check

`BearerTokenAuthenticator` et `KeycloakJwksProvider` vivent dans `Security\Infrastructure\Keycloak\` — cohérent avec `KeycloakAccountRegistrar` et `KeycloakHttpClient` déjà présents. Aucune violation deptrac.

## Tests

### Unitaire — BearerTokenAuthenticator (`#[Group('unit')]`)

- JWT valide (forgé avec une clé RSA de test) → `SelfValidatingPassport` retourné
- JWT expiré → `AuthenticationException`
- JWT avec mauvais issuer → `AuthenticationException`
- Header absent → `supports()` retourne `false`
- `KeycloakJwksProvider` stubbé : `private KeycloakJwksProviderInterface&Stub`

### Intégration — KeycloakJwksProvider (`#[Group('integration')]`)

- Premier appel → `MockHttpClient` retourne un JWKS, clé parsée et mise en cache
- Deuxième appel → cache hit, `MockHttpClient` n'est pas rappelé

### Fonctionnel — Route existante (`#[Group('functional')]`)

- `GET /hotels` sans header → 401
- `GET /hotels` avec JWT valide forgé → 200
- `GET /hotels` avec JWT expiré → 401

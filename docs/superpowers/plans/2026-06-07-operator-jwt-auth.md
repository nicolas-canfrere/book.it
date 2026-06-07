# Operator JWT Auth — BearerTokenAuthenticator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sécuriser toutes les routes de `book.it` avec un JWT Keycloak RS256 validé via un `BearerTokenAuthenticator` Symfony stateless.

**Architecture:** Un `KeycloakJwksProvider` récupère et met en cache la clé publique RSA depuis le JWKS endpoint de Keycloak. Un `BearerTokenAuthenticator` extrait le Bearer token, le valide avec `firebase/php-jwt`, et retourne un `SelfValidatingPassport` créant un `InMemoryUser` avec `ROLE_OPERATOR`. Le firewall Symfony est configuré en stateless sur toutes les routes.

**Tech Stack:** PHP 8.4 / Symfony 8.0 / `firebase/php-jwt` / `symfony/security-bundle` / `symfony/http-client`

**Spec:** `docs/superpowers/specs/2026-06-07-operator-jwt-auth-design.md`

---

## Fichiers

| Action | Fichier | Rôle |
|--------|---------|------|
| Créer | `src/Security/Infrastructure/Keycloak/KeycloakJwksProviderInterface.php` | Interface pour testabilité |
| Créer | `src/Security/Infrastructure/Keycloak/KeycloakJwksProvider.php` | Fetch JWKS + cache |
| Créer | `src/Security/Infrastructure/Keycloak/BearerTokenAuthenticator.php` | Authenticator Symfony Security |
| Modifier | `config/packages/security.yaml` | Firewall stateless + access_control |
| Modifier | `config/services/security.yaml` | Wiring KeycloakJwksProvider + BearerTokenAuthenticator |
| Modifier | `.env` / `.env.test` | Ajouter KEYCLOAK_REALM |
| Créer | `tests/Security/Infrastructure/Keycloak/KeycloakJwksProviderTest.php` | Test intégration |
| Créer | `tests/Security/Infrastructure/Keycloak/BearerTokenAuthenticatorTest.php` | Test unitaire |
| Créer | `tests/Security/Functional/BearerAuthTest.php` | Test fonctionnel |

---

## Task 1 : Installer firebase/php-jwt

**Files:**
- Modify: `composer.json` (via composer)

- [ ] **Installer la dépendance**

```bash
docker compose exec php composer require firebase/php-jwt
```

Expected output: `Package firebase/php-jwt is installed`

- [ ] **Vérifier les classes disponibles**

```bash
docker compose exec php php -r "use Firebase\JWT\JWT; use Firebase\JWT\JWK; echo 'ok';"
```

Expected output: `ok`

- [ ] **Commit**

```bash
git add composer.json composer.lock
git commit -m "chore(security): add firebase/php-jwt dependency"
```

---

## Task 2 : KeycloakJwksProviderInterface

**Files:**
- Create: `src/Security/Infrastructure/Keycloak/KeycloakJwksProviderInterface.php`

- [ ] **Créer l'interface**

```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Firebase\JWT\Key;

interface KeycloakJwksProviderInterface
{
    /** @return array<string, Key> */
    public function getPublicKeys(): array;
}
```

- [ ] **Commit**

```bash
git add src/Security/Infrastructure/Keycloak/KeycloakJwksProviderInterface.php
git commit -m "feat(security): add KeycloakJwksProviderInterface"
```

---

## Task 3 : KeycloakJwksProvider — test d'intégration

**Files:**
- Create: `tests/Security/Infrastructure/Keycloak/KeycloakJwksProviderTest.php`
- Create: `src/Security/Infrastructure/Keycloak/KeycloakJwksProvider.php` (stub vide pour faire compiler)

- [ ] **Créer le stub vide pour faire compiler**

```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Firebase\JWT\Key;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class KeycloakJwksProvider implements KeycloakJwksProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly string $keycloakBaseUrl,
        private readonly string $keycloakRealm,
    ) {
    }

    public function getPublicKeys(): array
    {
        throw new \RuntimeException('Not implemented');
    }
}
```

- [ ] **Créer le test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Security\Infrastructure\Keycloak;

use App\Security\Infrastructure\Keycloak\KeycloakJwksProvider;
use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[Group('integration')]
final class KeycloakJwksProviderTest extends KernelTestCase
{
    private const JWKS_FIXTURE = [
        'keys' => [
            [
                'kid' => 'test-key-id',
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'n' => 'sGPOWRIBn0KkLOIBnxHSmUPIFGTFgFdMM3l5D38TtajRMhsj1jCRb4WL7E4JjJuXi5fJZEZ56-g_wPR89_jD8GXy3f3KMTkH3DqxVyGcQ4yGNJFMidLmvPxNEWkYgcHkz0fQoFu0Vu7KPNfXBsocT50N0HFTgq0jkHhcxk7pMH3oqb4RRMvqVP7MKFJQtTlxRBx5UhVlVHvT1e2p8zV4N1u6L2a8r5-m5Kq4z-wYrX3j1V9zIj7qKFJQjkVwuQ',
                'e' => 'AQAB',
            ],
        ],
    ];

    #[Test]
    public function itParsesPublicKeysFromJwks(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse((string) json_encode(self::JWKS_FIXTURE)),
        ]);
        $cache = new ArrayAdapter();

        $provider = new KeycloakJwksProvider($httpClient, $cache, 'http://keycloak:8080', 'bookit');

        $keys = $provider->getPublicKeys();

        self::assertNotEmpty($keys);
        self::assertContainsOnlyInstancesOf(Key::class, $keys);
    }

    #[Test]
    public function itCachesJwksAndDoesNotCallHttpClientTwice(): void
    {
        $callCount = 0;
        $httpClient = new MockHttpClient(function () use (&$callCount): MockResponse {
            ++$callCount;

            return new MockResponse((string) json_encode(self::JWKS_FIXTURE));
        });
        $cache = new ArrayAdapter();

        $provider = new KeycloakJwksProvider($httpClient, $cache, 'http://keycloak:8080', 'bookit');

        $provider->getPublicKeys();
        $provider->getPublicKeys();

        self::assertSame(1, $callCount, 'JWKS endpoint should be called only once due to cache');
    }
}
```

- [ ] **Lancer le test — vérifier qu'il échoue**

```bash
make unit-test -- --filter KeycloakJwksProviderTest
```

Expected: FAIL `RuntimeException: Not implemented`

- [ ] **Commit**

```bash
git add src/Security/Infrastructure/Keycloak/KeycloakJwksProvider.php tests/Security/Infrastructure/Keycloak/KeycloakJwksProviderTest.php
git commit -m "test(security): add KeycloakJwksProvider integration tests"
```

---

## Task 4 : Implémenter KeycloakJwksProvider

**Files:**
- Modify: `src/Security/Infrastructure/Keycloak/KeycloakJwksProvider.php`

- [ ] **Implémenter getPublicKeys()**

```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class KeycloakJwksProvider implements KeycloakJwksProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly string $keycloakBaseUrl,
        private readonly string $keycloakRealm,
    ) {
    }

    public function getPublicKeys(): array
    {
        /** @var array<string, Key> */
        return $this->cache->get('keycloak_jwks', function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            $url = "{$this->keycloakBaseUrl}/realms/{$this->keycloakRealm}/protocol/openid-connect/certs";
            $response = $this->httpClient->request('GET', $url);

            /** @var array{keys: list<array<string, string>>} $jwks */
            $jwks = $response->toArray();

            return JWK::parseKeySet($jwks);
        });
    }
}
```

- [ ] **Lancer les tests**

```bash
make unit-test -- --filter KeycloakJwksProviderTest
```

Expected: PASS (2 tests)

- [ ] **Commit**

```bash
git add src/Security/Infrastructure/Keycloak/KeycloakJwksProvider.php
git commit -m "feat(security): implement KeycloakJwksProvider with JWKS cache"
```

---

## Task 5 : BearerTokenAuthenticator — test unitaire

**Files:**
- Create: `tests/Security/Infrastructure/Keycloak/BearerTokenAuthenticatorTest.php`
- Create: `src/Security/Infrastructure/Keycloak/BearerTokenAuthenticator.php` (stub vide)

Pour générer une paire RSA de test et signer un JWT, on utilise `firebase/php-jwt` directement dans le test.

- [ ] **Créer le stub vide**

```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final class BearerTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly KeycloakJwksProviderInterface $jwksProvider,
        private readonly string $keycloakIssuer,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        throw new \RuntimeException('Not implemented');
    }

    public function authenticate(Request $request): Passport
    {
        throw new \RuntimeException('Not implemented');
    }

    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
    }
}
```

- [ ] **Créer le test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Security\Infrastructure\Keycloak;

use App\Security\Infrastructure\Keycloak\BearerTokenAuthenticator;
use App\Security\Infrastructure\Keycloak\KeycloakJwksProviderInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

#[Group('unit')]
final class BearerTokenAuthenticatorTest extends TestCase
{
    private string $privateKey;
    private string $publicKey;
    private string $issuer = 'http://keycloak:8080/realms/bookit';

    protected function setUp(): void
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $this->privateKey);
        $this->publicKey = openssl_pkey_get_details($resource)['key'];
    }

    private function makeToken(array $overrides = []): string
    {
        $payload = array_merge([
            'sub' => 'user-uuid-123',
            'iss' => $this->issuer,
            'iat' => time(),
            'exp' => time() + 3600,
        ], $overrides);

        return JWT::encode($payload, $this->privateKey, 'RS256');
    }

    private function makeAuthenticator(): BearerTokenAuthenticator
    {
        $publicKey = $this->publicKey;
        $jwksProvider = $this->createStub(KeycloakJwksProviderInterface::class);
        $jwksProvider->method('getPublicKeys')->willReturn(['default' => new Key($publicKey, 'RS256')]);

        return new BearerTokenAuthenticator($jwksProvider, $this->issuer);
    }

    #[Test]
    public function itReturnsFalseWhenNoBearerHeader(): void
    {
        $request = Request::create('/api/v1/hotels');
        self::assertFalse($this->makeAuthenticator()->supports($request));
    }

    #[Test]
    public function itReturnsTrueWhenBearerHeaderPresent(): void
    {
        $request = Request::create('/api/v1/hotels');
        $request->headers->set('Authorization', 'Bearer ' . $this->makeToken());
        self::assertTrue($this->makeAuthenticator()->supports($request));
    }

    #[Test]
    public function itAuthenticatesWithValidToken(): void
    {
        $token = $this->makeToken();
        $request = Request::create('/api/v1/hotels');
        $request->headers->set('Authorization', "Bearer {$token}");

        $passport = $this->makeAuthenticator()->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        self::assertSame('user-uuid-123', $passport->getUser()->getUserIdentifier());
    }

    #[Test]
    public function itRejectsExpiredToken(): void
    {
        $token = $this->makeToken(['exp' => time() - 10]);
        $request = Request::create('/api/v1/hotels');
        $request->headers->set('Authorization', "Bearer {$token}");

        $this->expectException(AuthenticationException::class);
        $this->makeAuthenticator()->authenticate($request);
    }

    #[Test]
    public function itRejectsTokenWithWrongIssuer(): void
    {
        $token = $this->makeToken(['iss' => 'http://evil.com/realms/hacked']);
        $request = Request::create('/api/v1/hotels');
        $request->headers->set('Authorization', "Bearer {$token}");

        $this->expectException(AuthenticationException::class);
        $this->makeAuthenticator()->authenticate($request);
    }

    #[Test]
    public function itRejectsMalformedToken(): void
    {
        $request = Request::create('/api/v1/hotels');
        $request->headers->set('Authorization', 'Bearer not.a.valid.jwt');

        $this->expectException(AuthenticationException::class);
        $this->makeAuthenticator()->authenticate($request);
    }
}
```

- [ ] **Lancer le test — vérifier qu'il échoue**

```bash
make unit-test -- --filter BearerTokenAuthenticatorTest
```

Expected: FAIL `RuntimeException: Not implemented`

- [ ] **Commit**

```bash
git add src/Security/Infrastructure/Keycloak/BearerTokenAuthenticator.php tests/Security/Infrastructure/Keycloak/BearerTokenAuthenticatorTest.php
git commit -m "test(security): add BearerTokenAuthenticator unit tests"
```

---

## Task 6 : Implémenter BearerTokenAuthenticator

**Files:**
- Modify: `src/Security/Infrastructure/Keycloak/BearerTokenAuthenticator.php`

- [ ] **Implémenter supports() et authenticate()**

```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class BearerTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly KeycloakJwksProviderInterface $jwksProvider,
        private readonly string $keycloakIssuer,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $header = $request->headers->get('Authorization', '');

        return str_starts_with($header, 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $token = substr((string) $request->headers->get('Authorization'), 7);

        try {
            $keys = $this->jwksProvider->getPublicKeys();
            $payload = JWT::decode($token, $keys);
        } catch (\Throwable $e) {
            throw new AuthenticationException('Invalid token: ' . $e->getMessage(), 0, $e);
        }

        if (($payload->iss ?? '') !== $this->keycloakIssuer) {
            throw new AuthenticationException('Invalid token issuer');
        }

        $sub = (string) ($payload->sub ?? '');

        return new SelfValidatingPassport(
            new UserBadge($sub, static fn (string $id) => new InMemoryUser($id, null, ['ROLE_OPERATOR']))
        );
    }

    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
    }
}
```

- [ ] **Lancer les tests unitaires**

```bash
make unit-test -- --filter BearerTokenAuthenticatorTest
```

Expected: PASS (6 tests)

- [ ] **Commit**

```bash
git add src/Security/Infrastructure/Keycloak/BearerTokenAuthenticator.php
git commit -m "feat(security): implement BearerTokenAuthenticator with RS256 validation"
```

---

## Task 7 : Configurer security.yaml et les services

**Files:**
- Modify: `config/packages/security.yaml`
- Modify: `config/services/security.yaml`
- Modify: `.env` (ajouter KEYCLOAK_REALM)

- [ ] **Mettre à jour security.yaml**

Remplacer tout le contenu par :

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

when@test:
    security:
        password_hashers:
            Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface:
                algorithm: auto
                cost: 4
                time_cost: 3
                memory_cost: 10
```

- [ ] **Ajouter le wiring dans config/services/security.yaml**

Ajouter à la fin du fichier existant :

```yaml
    App\Security\Infrastructure\Keycloak\KeycloakJwksProvider:
        arguments:
            $httpClient: '@http_client'
            $cache: '@cache.app'
            $keycloakBaseUrl: '%env(KEYCLOAK_BASE_URL)%'
            $keycloakRealm: '%env(KEYCLOAK_REALM)%'

    App\Security\Infrastructure\Keycloak\KeycloakJwksProviderInterface: '@App\Security\Infrastructure\Keycloak\KeycloakJwksProvider'

    App\Security\Infrastructure\Keycloak\BearerTokenAuthenticator:
        arguments:
            $jwksProvider: '@App\Security\Infrastructure\Keycloak\KeycloakJwksProviderInterface'
            $keycloakIssuer: '%env(KEYCLOAK_BASE_URL)%/realms/%env(KEYCLOAK_REALM)%'
```

- [ ] **Ajouter KEYCLOAK_REALM dans .env**

Ajouter après `KEYCLOAK_BASE_URL` :

```
KEYCLOAK_REALM=bookit
```

- [ ] **Vérifier que le container compile**

```bash
docker compose exec php bin/console debug:container BearerTokenAuthenticator
```

Expected: affiche le service avec ses dépendances

- [ ] **Commit**

```bash
git add config/packages/security.yaml config/services/security.yaml .env
git commit -m "feat(security): configure stateless JWT firewall for all API routes"
```

---

## Task 8 : Test fonctionnel — protection des routes

**Files:**
- Create: `tests/Security/Functional/BearerAuthTest.php`

Pour les tests fonctionnels, on génère un JWT signé avec une clé RSA de test et on configure un `KeycloakJwksProvider` mocké via le kernel de test.

- [ ] **Créer le test fonctionnel**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Security\Functional;

use App\Security\Infrastructure\Keycloak\KeycloakJwksProviderInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class BearerAuthTest extends WebTestCase
{
    private string $privateKey;
    private string $publicKey;

    protected function setUp(): void
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $this->privateKey);
        $this->publicKey = openssl_pkey_get_details($resource)['key'];
    }

    private function makeToken(array $overrides = []): string
    {
        $payload = array_merge([
            'sub' => 'operator-uuid-test',
            'iss' => 'http://keycloak:8080/realms/bookit',
            'iat' => time(),
            'exp' => time() + 3600,
        ], $overrides);

        return JWT::encode($payload, $this->privateKey, 'RS256');
    }

    #[Test]
    public function itReturns401WhenNoTokenProvided(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/hotels');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itAllowsAccessWithValidToken(): void
    {
        $client = static::createClient();

        $publicKey = $this->publicKey;
        static::getContainer()->set(
            KeycloakJwksProviderInterface::class,
            new class ($publicKey) implements KeycloakJwksProviderInterface {
                public function __construct(private string $key) {}
                public function getPublicKeys(): array
                {
                    return ['default' => new Key($this->key, 'RS256')];
                }
            }
        );

        $client->request('GET', '/api/v1/hotels', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->makeToken(),
        ]);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns401WithExpiredToken(): void
    {
        $client = static::createClient();

        $publicKey = $this->publicKey;
        static::getContainer()->set(
            KeycloakJwksProviderInterface::class,
            new class ($publicKey) implements KeycloakJwksProviderInterface {
                public function __construct(private string $key) {}
                public function getPublicKeys(): array
                {
                    return ['default' => new Key($this->key, 'RS256')];
                }
            }
        );

        $client->request('GET', '/api/v1/hotels', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->makeToken(['exp' => time() - 10]),
        ]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }
}
```

- [ ] **Lancer les tests fonctionnels**

```bash
make functional-test -- --filter BearerAuthTest
```

Expected: PASS (3 tests)

- [ ] **Lancer toute la suite pour vérifier les régressions**

```bash
make test
```

Expected: tous les tests passent. Note : les tests fonctionnels existants (ListHotelsTest, etc.) devront maintenant inclure un Bearer token valide — voir la note ci-dessous.

> **Note :** Après l'activation du firewall, tous les tests fonctionnels existants échoueront avec 401. Il faut créer un helper partagé `AuthenticatedWebTestCase` (ou un trait) qui injecte un JWT de test valide dans chaque requête. Ajouter une tâche si nécessaire.

- [ ] **Commit**

```bash
git add tests/Security/Functional/BearerAuthTest.php
git commit -m "test(security): add functional tests for Bearer JWT authentication"
```

---

## Task 9 : Adapter les tests fonctionnels existants

**Files:**
- Create: `tests/Shared/AuthenticatedWebTestCase.php`
- Modify: tous les fichiers `tests/**/*Test.php` étendant `WebTestCase`

- [ ] **Créer AuthenticatedWebTestCase**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Security\Infrastructure\Keycloak\KeycloakJwksProviderInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AuthenticatedWebTestCase extends WebTestCase
{
    private static string $privateKey = '';
    private static string $publicKey = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, self::$privateKey);
        self::$publicKey = openssl_pkey_get_details($resource)['key'];
    }

    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = static::createClient();

        $publicKey = self::$publicKey;
        static::getContainer()->set(
            KeycloakJwksProviderInterface::class,
            new class ($publicKey) implements KeycloakJwksProviderInterface {
                public function __construct(private string $key) {}
                public function getPublicKeys(): array
                {
                    return ['default' => new Key($this->key, 'RS256')];
                }
            }
        );

        $token = JWT::encode([
            'sub' => 'test-operator',
            'iss' => 'http://keycloak:8080/realms/bookit',
            'iat' => time(),
            'exp' => time() + 3600,
        ], self::$privateKey, 'RS256');

        $client->setServerParameter('HTTP_AUTHORIZATION', "Bearer {$token}");

        return $client;
    }
}
```

- [ ] **Remplacer WebTestCase par AuthenticatedWebTestCase dans tous les tests fonctionnels existants**

Rechercher tous les fichiers concernés :

```bash
grep -rl "extends WebTestCase" tests/ --include="*.php"
```

Pour chaque fichier listé (sauf `BearerAuthTest.php`) :
1. Remplacer `use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;` par `use App\Tests\Shared\AuthenticatedWebTestCase;`
2. Remplacer `extends WebTestCase` par `extends AuthenticatedWebTestCase`
3. Remplacer `static::createClient()` par `static::createAuthenticatedClient()`

- [ ] **Lancer la suite complète**

```bash
make functional-test
```

Expected: PASS — tous les tests fonctionnels existants passent avec le nouveau firewall.

- [ ] **Lancer lint**

```bash
make lint
```

Expected: no errors

- [ ] **Commit**

```bash
git add tests/Shared/AuthenticatedWebTestCase.php tests/
git commit -m "test(security): add AuthenticatedWebTestCase helper and update existing functional tests"
```

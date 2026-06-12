# OperatorUser Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer l'`InMemoryUser` anonyme dans `BearerTokenAuthenticator` par un `OperatorUser` typé exposant l'email comme identifiant, résolu depuis la table `operator` via un contrat cross-context.

**Architecture:** Le `JWT.sub` est un UUID Keycloak (≠ `Operator.id`). L'authenticateur résout d'abord `sub → internal_id` via `IdentityMappingRepository.findInternalId()`, puis charge `id + email` via le contrat publié `OperatorFinderInterface` (ADR 0015). `OperatorUser` est un value object Symfony Security (`UserInterface`) avec `id`, `email`, `getUserIdentifier()` = email. `Operator` n'étant pas encore dans `deptrac-contexts.yaml`, on crée les layers `Operator` + `OperatorContract` et on autorise `Security → OperatorContract`.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL, Firebase JWT, PHPUnit 11, `make unit-test` / `make functional-test` / `make deptrac`

---

## File Map

| Action | Path | Role |
|--------|------|------|
| Modify | `src/Security/Infrastructure/Persistence/IdentityMappingRepository.php` | Add `findInternalId()` |
| Create | `src/Operator/Application/Contract/OperatorFinderInterface.php` | Contrat publié (lecture cross-context) |
| Create | `src/Operator/Application/Contract/OperatorView.php` | DTO publié (id + email) |
| Create | `src/Operator/Infrastructure/Contract/DoctrineOperatorFinder.php` | Implémentation DBAL du contrat |
| Create | `src/Security/Infrastructure/Keycloak/OperatorUser.php` | `UserInterface` avec id + email |
| Modify | `src/Security/Infrastructure/Keycloak/BearerTokenAuthenticator.php` | Injecte repo + finder, construit `OperatorUser` |
| Modify | `config/packages/security.yaml` | Supprime provider `operators` inutile |
| Modify | `deptrac-contexts.yaml` | Ajoute layers `Operator`/`OperatorContract`, autorise `Security → OperatorContract` |
| Modify | `tests/Security/Infrastructure/Persistence/IdentityMappingRepositoryTest.php` | Tests `findInternalId` |
| Create | `tests/Operator/Infrastructure/Contract/DoctrineOperatorFinderTest.php` | Test unitaire du finder |
| Modify | `tests/Security/Infrastructure/Keycloak/BearerTokenAuthenticatorTest.php` | Stub finder + mapping, assert `OperatorUser` |
| Modify | `tests/Shared/AuthenticatedWebTestCase.php` | Stub `IdentityMappingRepository` + `OperatorFinderInterface` |
| Modify | `tests/Security/Functional/BearerAuthTest.php` | Stub `IdentityMappingRepository` + `OperatorFinderInterface` |

---

### Task 1: Create branch

- [ ] **Step 1: Create and switch to feature branch**

```bash
git checkout -b feat/operator-user
```

---

### Task 2: Add `findInternalId()` to `IdentityMappingRepository`

**Files:**
- Modify: `src/Security/Infrastructure/Persistence/IdentityMappingRepository.php`

- [ ] **Step 1: Add the method after `findExternalId()`**

```php
public function findInternalId(string $externalId, string $context): ?string
{
    $result = $this->connection->fetchOne(
        'SELECT internal_id FROM security.identity_mapping WHERE external_id = ? AND context = ?',
        [$externalId, $context],
    );

    return \is_string($result) ? $result : null;
}
```

---

### Task 3: Unit-test `findInternalId()`

**Files:**
- Modify: `tests/Security/Infrastructure/Persistence/IdentityMappingRepositoryTest.php`

- [ ] **Step 1: Add two tests at the end of the class (before closing `}`)**

```php
#[Test]
public function itFindsInternalId(): void
{
    $this->connection->expects(self::once())
        ->method('fetchOne')
        ->with(
            'SELECT internal_id FROM security.identity_mapping WHERE external_id = ? AND context = ?',
            ['keycloak-uuid', 'operator'],
        )
        ->willReturn('operator-internal-uuid');

    self::assertSame('operator-internal-uuid', $this->repository->findInternalId('keycloak-uuid', 'operator'));
}

#[Test]
public function itReturnsNullWhenNoInternalIdFound(): void
{
    $this->connection->method('fetchOne')->willReturn(false);

    self::assertNull($this->repository->findInternalId('unknown-uuid', 'operator'));
}
```

- [ ] **Step 2: Run unit tests**

```bash
make unit-test
```

Expected: all pass.

- [ ] **Step 3: Commit**

```bash
git add src/Security/Infrastructure/Persistence/IdentityMappingRepository.php \
        tests/Security/Infrastructure/Persistence/IdentityMappingRepositoryTest.php
git commit -m "feat(security): add findInternalId() to IdentityMappingRepository"
```

---

### Task 4: Publish the Operator contract

**Files:**
- Create: `src/Operator/Application/Contract/OperatorFinderInterface.php`
- Create: `src/Operator/Application/Contract/OperatorView.php`

- [ ] **Step 1: Create `OperatorView`**

```php
<?php

declare(strict_types=1);

namespace App\Operator\Application\Contract;

final readonly class OperatorView
{
    public function __construct(
        public string $id,
        public string $email,
    ) {
    }
}
```

- [ ] **Step 2: Create `OperatorFinderInterface`**

```php
<?php

declare(strict_types=1);

namespace App\Operator\Application\Contract;

interface OperatorFinderInterface
{
    public function find(string $operatorId): ?OperatorView;
}
```

---

### Task 5: Implement `DoctrineOperatorFinder`

**Files:**
- Create: `src/Operator/Infrastructure/Contract/DoctrineOperatorFinder.php`

La connexion DBAL s'autowire via le nom `$bookit` (voir `OperatorRepository` qui utilise `$operator` — même pattern, même connexion `bookit`).

- [ ] **Step 1: Create the class**

```php
<?php

declare(strict_types=1);

namespace App\Operator\Infrastructure\Contract;

use App\Operator\Application\Contract\OperatorFinderInterface;
use App\Operator\Application\Contract\OperatorView;
use Doctrine\DBAL\Connection;

final readonly class DoctrineOperatorFinder implements OperatorFinderInterface
{
    public function __construct(
        private Connection $bookit,
    ) {
    }

    public function find(string $operatorId): ?OperatorView
    {
        $row = $this->bookit->fetchAssociative(
            'SELECT id, email FROM operator WHERE id = ?',
            [$operatorId],
        );

        if (!\is_array($row)) {
            return null;
        }

        return new OperatorView(
            id: (string) $row['id'],
            email: (string) $row['email'],
        );
    }
}
```

---

### Task 6: Unit-test `DoctrineOperatorFinder`

**Files:**
- Create: `tests/Operator/Infrastructure/Contract/DoctrineOperatorFinderTest.php`

- [ ] **Step 1: Create the test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Operator\Infrastructure\Contract;

use App\Operator\Application\Contract\OperatorView;
use App\Operator\Infrastructure\Contract\DoctrineOperatorFinder;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineOperatorFinderTest extends TestCase
{
    private Connection&MockObject $connection;
    private DoctrineOperatorFinder $finder;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->finder = new DoctrineOperatorFinder($this->connection);
    }

    #[Test]
    public function itReturnsOperatorViewWhenFound(): void
    {
        $this->connection->method('fetchAssociative')
            ->with('SELECT id, email FROM operator WHERE id = ?', ['op-uuid'])
            ->willReturn(['id' => 'op-uuid', 'email' => 'op@example.com']);

        $view = $this->finder->find('op-uuid');

        self::assertInstanceOf(OperatorView::class, $view);
        self::assertSame('op-uuid', $view->id);
        self::assertSame('op@example.com', $view->email);
    }

    #[Test]
    public function itReturnsNullWhenNotFound(): void
    {
        $this->connection->method('fetchAssociative')->willReturn(false);

        self::assertNull($this->finder->find('op-uuid'));
    }
}
```

- [ ] **Step 2: Run unit tests**

```bash
make unit-test
```

Expected: all pass.

- [ ] **Step 3: Commit**

```bash
git add src/Operator/Application/Contract/OperatorFinderInterface.php \
        src/Operator/Application/Contract/OperatorView.php \
        src/Operator/Infrastructure/Contract/DoctrineOperatorFinder.php \
        tests/Operator/Infrastructure/Contract/DoctrineOperatorFinderTest.php
git commit -m "feat(operator): publish OperatorFinderInterface contract with DoctrineOperatorFinder"
```

---

### Task 7: Create `OperatorUser`

**Files:**
- Create: `src/Security/Infrastructure/Keycloak/OperatorUser.php`

- [ ] **Step 1: Create the class**

```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class OperatorUser implements UserInterface
{
    public function __construct(
        public string $id,
        public string $email,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_OPERATOR'];
    }

    public function eraseCredentials(): void {}
}
```

---

### Task 8: Update `BearerTokenAuthenticator`

**Files:**
- Modify: `src/Security/Infrastructure/Keycloak/BearerTokenAuthenticator.php`

- [ ] **Step 1: Rewrite the class**

```php
<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Keycloak;

use App\Operator\Application\Contract\OperatorFinderInterface;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class BearerTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly KeycloakJwksProviderInterface $jwksProvider,
        private readonly string $keycloakIssuer,
        private readonly IdentityMappingRepository $identityMapping,
        private readonly OperatorFinderInterface $operatorFinder,
    ) {
    }

    public function supports(Request $request): bool
    {
        $header = $request->headers->get('Authorization', '');

        return str_starts_with($header, 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $token = substr((string) $request->headers->get('Authorization'), 7);

        try {
            $keys = $this->jwksProvider->getPublicKeys();
            if (1 === count($keys)) {
                $payload = JWT::decode($token, reset($keys));
            } else {
                $payload = JWT::decode($token, $keys);
            }
        } catch (\Throwable $e) {
            throw new AuthenticationException('Invalid token: ' . $e->getMessage(), 0, $e);
        }

        if (($payload->iss ?? '') !== $this->keycloakIssuer) {
            throw new AuthenticationException('Invalid token issuer');
        }

        $rawSub = $payload->sub ?? '';
        $externalId = \is_scalar($rawSub) ? (string) $rawSub : '';

        $identityMapping = $this->identityMapping;
        $operatorFinder = $this->operatorFinder;

        return new SelfValidatingPassport(
            new UserBadge($externalId, static function (string $externalId) use ($identityMapping, $operatorFinder): OperatorUser {
                $internalId = $identityMapping->findInternalId($externalId, 'operator');
                if (null === $internalId) {
                    throw new AuthenticationException('No operator found for this token');
                }

                $operator = $operatorFinder->find($internalId);
                if (null === $operator) {
                    throw new AuthenticationException('Operator not found');
                }

                return new OperatorUser($operator->id, $operator->email);
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
    }
}
```

---

### Task 9: Update `BearerTokenAuthenticatorTest`

**Files:**
- Modify: `tests/Security/Infrastructure/Keycloak/BearerTokenAuthenticatorTest.php`

- [ ] **Step 1: Rewrite the test file**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Security\Infrastructure\Keycloak;

use App\Operator\Application\Contract\OperatorFinderInterface;
use App\Operator\Application\Contract\OperatorView;
use App\Security\Infrastructure\Keycloak\BearerTokenAuthenticator;
use App\Security\Infrastructure\Keycloak\KeycloakJwksProviderInterface;
use App\Security\Infrastructure\Keycloak\OperatorUser;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
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
    private string $privateKey = '';
    private string $publicKey = '';
    private string $issuer = 'http://keycloak:8080/realms/bookit';

    protected function setUp(): void
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        \assert($resource instanceof \OpenSSLAsymmetricKey);
        $privateKey = '';
        openssl_pkey_export($resource, $privateKey);
        \assert(\is_string($privateKey));
        $this->privateKey = $privateKey;
        $details = openssl_pkey_get_details($resource);
        \assert(\is_array($details) && \is_string($details['key']));
        $this->publicKey = $details['key'];
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
        $token = $this->makeToken(['sub' => 'keycloak-uuid-123']);
        $request = Request::create('/api/v1/hotels');
        $request->headers->set('Authorization', "Bearer {$token}");

        $passport = $this->makeAuthenticator(
            mappedInternalId: 'operator-internal-uuid',
            operatorView: new OperatorView('operator-internal-uuid', 'op@example.com'),
        )->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        $user = $passport->getUser();
        self::assertInstanceOf(OperatorUser::class, $user);
        self::assertSame('op@example.com', $user->getUserIdentifier());
        self::assertSame('operator-internal-uuid', $user->id);
        self::assertSame(['ROLE_OPERATOR'], $user->getRoles());
    }

    #[Test]
    public function itThrowsWhenNoIdentityMappingFound(): void
    {
        $token = $this->makeToken();
        $request = Request::create('/api/v1/hotels');
        $request->headers->set('Authorization', "Bearer {$token}");

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No operator found for this token');

        $this->makeAuthenticator(mappedInternalId: null)->authenticate($request);
    }

    #[Test]
    public function itThrowsWhenOperatorNotFoundInDatabase(): void
    {
        $token = $this->makeToken();
        $request = Request::create('/api/v1/hotels');
        $request->headers->set('Authorization', "Bearer {$token}");

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Operator not found');

        $this->makeAuthenticator(mappedInternalId: 'some-id', operatorView: null)->authenticate($request);
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

    /** @param array<string, mixed> $overrides */
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

    private function makeAuthenticator(
        ?string $mappedInternalId = 'operator-internal-uuid',
        ?OperatorView $operatorView = null,
    ): BearerTokenAuthenticator {
        $publicKey = $this->publicKey;
        $jwksProvider = $this->createStub(KeycloakJwksProviderInterface::class);
        $jwksProvider->method('getPublicKeys')->willReturn(['default' => new Key($publicKey, 'RS256')]);

        $identityMapping = $this->createStub(IdentityMappingRepository::class);
        $identityMapping->method('findInternalId')->willReturn($mappedInternalId);

        $operatorFinder = $this->createStub(OperatorFinderInterface::class);
        $operatorFinder->method('find')->willReturn(
            $operatorView ?? new OperatorView('operator-internal-uuid', 'op@example.com')
        );

        return new BearerTokenAuthenticator($jwksProvider, $this->issuer, $identityMapping, $operatorFinder);
    }
}
```

- [ ] **Step 2: Run unit tests**

```bash
make unit-test
```

Expected: all pass.

- [ ] **Step 3: Commit**

```bash
git add src/Security/Infrastructure/Keycloak/OperatorUser.php \
        src/Security/Infrastructure/Keycloak/BearerTokenAuthenticator.php \
        tests/Security/Infrastructure/Keycloak/BearerTokenAuthenticatorTest.php
git commit -m "feat(security): introduce OperatorUser with email identifier"
```

---

### Task 10: Update `deptrac-contexts.yaml`

**Files:**
- Modify: `deptrac-contexts.yaml`

Le contexte `Operator` n'existe pas encore dans ce fichier. On ajoute les deux layers et on met à jour le ruleset.

- [ ] **Step 1: Add `Operator` and `OperatorContract` layers**

Dans la section `layers:`, entre les blocks `Notification` et `Payment`, ajouter :

```yaml
        -
            name: Operator
            collectors:
                -
                    type: bool
                    must:
                        -
                            type: classLike
                            value: 'App\\Operator\\.*'
                    must_not:
                        -
                            type: classLike
                            value: 'App\\Operator\\Application\\Contract\\.*'
        -
            name: OperatorContract
            collectors:
                -
                    type: classLike
                    value: 'App\\Operator\\Application\\Contract\\.*'
```

- [ ] **Step 2: Update the ruleset**

Dans la section `ruleset:`, ajouter après `Notification:` :

```yaml
        Operator:
            - OperatorContract
            - SecurityContract
            - Shared
            - Vendor
```

Et modifier `Security:` pour autoriser `OperatorContract` :

```yaml
        Security:
            - SecurityContract
            - OperatorContract
            - Shared
            - Vendor
```

Et ajouter `OperatorContract: ~` dans la liste des contrats purs (après `NotificationContract` s'il existe, sinon entre `HotelContract` et `PricingContract`) :

```yaml
        OperatorContract: ~
```

- [ ] **Step 3: Run deptrac**

```bash
make deptrac
```

Expected: no violations.

- [ ] **Step 4: Commit**

```bash
git add deptrac-contexts.yaml
git commit -m "chore(arch): add Operator/OperatorContract layers to deptrac-contexts"
```

---

### Task 11: Clean up `security.yaml`

**Files:**
- Modify: `config/packages/security.yaml`

- [ ] **Step 1: Remove the `operators` provider and the `provider:` key on the firewall**

Remplacer le contenu par :

```yaml
security:
    firewalls:
        dev:
            pattern: ^/(_profiler|_wdt|assets|build)/
            security: false
        api:
            pattern: ^/
            stateless: true
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

- [ ] **Step 2: Commit**

```bash
git add config/packages/security.yaml
git commit -m "chore(security): remove unused operators memory provider"
```

---

### Task 12: Update functional test helpers

**Files:**
- Modify: `tests/Shared/AuthenticatedWebTestCase.php`
- Modify: `tests/Security/Functional/BearerAuthTest.php`

Les tests fonctionnels doivent maintenant stubber à la fois `IdentityMappingRepository` et `OperatorFinderInterface` dans le container de test.

Pour `IdentityMappingRepository` (classe `readonly`) : on l'étend dans une classe anonyme sans appeler `parent::__construct()`. PHP 8.4 autorise d'étendre une `readonly class` ; la propriété `private readonly Connection $connection` du parent n'est jamais accédée puisqu'on surcharge la seule méthode appelée.

- [ ] **Step 1: Update `AuthenticatedWebTestCase`**

Remplacer `createAuthenticatedClient()` par :

```php
protected static function createAuthenticatedClient(): KernelBrowser
{
    $client = static::createClient();
    $client->disableReboot();

    $publicKey = self::$publicKey;
    static::getContainer()->set(
        KeycloakJwksProviderInterface::class,
        new class($publicKey) implements KeycloakJwksProviderInterface {
            public function __construct(private string $key) {}

            public function getPublicKeys(): array
            {
                return ['default' => new Key($this->key, 'RS256')];
            }
        }
    );

    static::getContainer()->set(
        IdentityMappingRepository::class,
        new class extends IdentityMappingRepository {
            public function __construct() {}

            public function findInternalId(string $externalId, string $context): ?string
            {
                return 'test-internal-operator-id';
            }
        }
    );

    static::getContainer()->set(
        OperatorFinderInterface::class,
        new class implements OperatorFinderInterface {
            public function find(string $operatorId): ?OperatorView
            {
                return new OperatorView($operatorId, 'test-operator@example.com');
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
```

Ajouter les imports manquants en haut du fichier :

```php
use App\Operator\Application\Contract\OperatorFinderInterface;
use App\Operator\Application\Contract\OperatorView;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
```

- [ ] **Step 2: Update `BearerAuthTest`**

Dans `itAllowsAccessWithValidToken()`, après le `static::getContainer()->set(KeycloakJwksProviderInterface::class, ...)`, ajouter :

```php
static::getContainer()->set(
    IdentityMappingRepository::class,
    new class extends IdentityMappingRepository {
        public function __construct() {}

        public function findInternalId(string $externalId, string $context): ?string
        {
            return 'test-internal-operator-id';
        }
    }
);

static::getContainer()->set(
    OperatorFinderInterface::class,
    new class implements OperatorFinderInterface {
        public function find(string $operatorId): ?OperatorView
        {
            return new OperatorView($operatorId, 'test-operator@example.com');
        }
    }
);
```

Ajouter les imports :

```php
use App\Operator\Application\Contract\OperatorFinderInterface;
use App\Operator\Application\Contract\OperatorView;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
```

Note : `itReturns401WithExpiredToken` n'a pas besoin des stubs mapping/finder — le token expiré échoue avant que l'identity mapping soit consulté.

- [ ] **Step 3: Run functional tests**

```bash
make functional-test
```

Expected: all pass.

- [ ] **Step 4: Run full suite + lint**

```bash
make test && make lint
```

Expected: all pass, aucune violation PHPStan ni deptrac.

- [ ] **Step 5: Commit**

```bash
git add tests/Shared/AuthenticatedWebTestCase.php \
        tests/Security/Functional/BearerAuthTest.php
git commit -m "test(security): stub identity mapping and operator finder in functional test helpers"
```

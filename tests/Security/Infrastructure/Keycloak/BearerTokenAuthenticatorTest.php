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
    private string $privateKey = '';
    private string $publicKey = '';
    private string $issuer = 'http://keycloak:8080/realms/bookit';

    protected function setUp(): void
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $this->privateKey);
        $this->publicKey = openssl_pkey_get_details($resource)['key'];
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
}

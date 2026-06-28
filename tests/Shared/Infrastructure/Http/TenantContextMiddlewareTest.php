<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Http;

use App\Shared\Application\TenantContext;
use App\Shared\Domain\Security\TenantCarrierInterface;
use App\Shared\Infrastructure\Http\TenantContextMiddleware;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[Group('unit')]
final class TenantContextMiddlewareTest extends TestCase
{
    #[Test]
    public function itSetsTenantContextWhenUserCarriesOrganizationId(): void
    {
        $carrier = new class implements UserInterface, TenantCarrierInterface {
            public function getOrganizationId(): string
            {
                return '550e8400-e29b-41d4-a716-446655440000';
            }

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'test@example.com';
            }
        };

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($carrier);

        $storage = $this->createStub(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn($token);

        $ctx = new TenantContext();
        $middleware = new TenantContextMiddleware($storage, $ctx);

        $middleware->onKernelRequest($this->mainRequestEvent());

        self::assertTrue($ctx->isInitialized());
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $ctx->getOrganizationId()->value);
    }

    #[Test]
    public function itDoesNothingWhenUserDoesNotCarryOrganizationId(): void
    {
        $carrier = new class implements UserInterface, TenantCarrierInterface {
            public function getOrganizationId(): ?string
            {
                return null;
            }

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'test@example.com';
            }
        };

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($carrier);

        $storage = $this->createStub(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn($token);

        $ctx = new TenantContext();
        $middleware = new TenantContextMiddleware($storage, $ctx);

        $middleware->onKernelRequest($this->mainRequestEvent());

        self::assertFalse($ctx->isInitialized());
    }

    #[Test]
    public function itDoesNothingWhenUserDoesNotImplementTenantCarrier(): void
    {
        $user = $this->createStub(UserInterface::class);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $storage = $this->createStub(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn($token);

        $ctx = new TenantContext();
        $middleware = new TenantContextMiddleware($storage, $ctx);

        $middleware->onKernelRequest($this->mainRequestEvent());

        self::assertFalse($ctx->isInitialized());
    }

    #[Test]
    public function itDoesNothingWhenNoTokenExists(): void
    {
        $storage = $this->createStub(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn(null);

        $ctx = new TenantContext();
        $middleware = new TenantContextMiddleware($storage, $ctx);

        $middleware->onKernelRequest($this->mainRequestEvent());

        self::assertFalse($ctx->isInitialized());
    }

    #[Test]
    public function itSubscribesToKernelRequestEvent(): void
    {
        self::assertArrayHasKey(KernelEvents::REQUEST, TenantContextMiddleware::getSubscribedEvents());
    }

    private function mainRequestEvent(): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST);
    }
}

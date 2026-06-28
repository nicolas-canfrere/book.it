<?php

declare(strict_types=1);

namespace App\Tests\Shared\Application;

use App\Shared\Application\TenantContext;
use App\Shared\Domain\Exception\TenantContextNotInitializedException;
use App\Shared\Domain\ValueObject\OrganizationId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class TenantContextTest extends TestCase
{
    #[Test]
    public function itIsNotInitializedByDefault(): void
    {
        $ctx = new TenantContext();
        self::assertFalse($ctx->isInitialized());
    }

    #[Test]
    public function itThrowsWhenAccessedBeforeInitialization(): void
    {
        $ctx = new TenantContext();
        $this->expectException(TenantContextNotInitializedException::class);
        $ctx->getOrganizationId();
    }

    #[Test]
    public function itReturnsOrganizationIdAfterSet(): void
    {
        $ctx = new TenantContext();
        $id = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $ctx->set($id);

        self::assertTrue($ctx->isInitialized());
        self::assertTrue($id->equals($ctx->getOrganizationId()));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\OrganizationId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class OrganizationIdTest extends TestCase
{
    #[Test]
    public function itWrapsAStringValue(): void
    {
        $id = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $id->value);
    }

    #[Test]
    public function itCastsToString(): void
    {
        $id = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $id);
    }

    #[Test]
    public function itComparesEquality(): void
    {
        $a = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $b = new OrganizationId('550e8400-e29b-41d4-a716-446655440000');
        $c = new OrganizationId('660e8400-e29b-41d4-a716-446655440000');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}

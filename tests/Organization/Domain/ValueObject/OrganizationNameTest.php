<?php

declare(strict_types=1);

namespace App\Tests\Organization\Domain\ValueObject;

use App\Organization\Domain\ValueObject\OrganizationName;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class OrganizationNameTest extends TestCase
{
    #[Test]
    public function itAcceptsAValidName(): void
    {
        $name = new OrganizationName('Hôtel du Palais');
        self::assertSame('Hôtel du Palais', $name->value);
    }

    #[Test]
    public function itRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Organization name cannot be empty');
        new OrganizationName('');
    }

    #[Test]
    public function itRejectsANameExceeding255Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Organization name cannot exceed 255 characters');
        new OrganizationName(str_repeat('a', 256));
    }

    #[Test]
    public function itAccepts255CharacterName(): void
    {
        $name = new OrganizationName(str_repeat('a', 255));
        self::assertSame(255, strlen($name->value));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Organization\Domain\ValueObject;

use App\Organization\Domain\ValueObject\OrganizationEmail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class OrganizationEmailTest extends TestCase
{
    #[Test]
    public function itAcceptsAValidEmail(): void
    {
        $email = new OrganizationEmail('contact@hotel.fr');
        self::assertSame('contact@hotel.fr', $email->value);
    }

    #[Test]
    public function itRejectsAnInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid organization email');
        new OrganizationEmail('not-an-email');
    }

    #[Test]
    public function itRejectsAnEmptyEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OrganizationEmail('');
    }
}

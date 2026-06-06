<?php

declare(strict_types=1);

namespace App\Tests\Translation\Domain\ValueObject;

use App\Translation\Domain\ValueObject\Locale;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class LocaleTest extends TestCase
{
    #[Test]
    public function itAcceptsLanguageWithRegion(): void
    {
        $locale = new Locale('fr_FR');
        self::assertSame('fr_FR', $locale->value);
    }

    #[Test]
    public function itAcceptsLanguageOnly(): void
    {
        $locale = new Locale('en');
        self::assertSame('en', $locale->value);
    }

    #[Test]
    public function itRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Locale('');
    }

    #[Test]
    public function itRejectsStringExceedingTenCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Locale('fr_FR_EXTRA1');
    }

    #[Test]
    public function itRejectsUppercaseLanguageCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Locale('FR_FR');
    }

    #[Test]
    public function itRejectsLowercaseRegionCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Locale('fr_fr');
    }
}

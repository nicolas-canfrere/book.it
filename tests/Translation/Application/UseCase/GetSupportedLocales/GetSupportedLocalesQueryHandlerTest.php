<?php

declare(strict_types=1);

namespace App\Tests\Translation\Application\UseCase\GetSupportedLocales;

use App\Translation\Application\UseCase\GetSupportedLocales\GetSupportedLocalesQuery;
use App\Translation\Application\UseCase\GetSupportedLocales\GetSupportedLocalesQueryHandler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class GetSupportedLocalesQueryHandlerTest extends TestCase
{
    #[Test]
    public function itReturnsSupportedLocalesAndDefault(): void
    {
        $handler = new GetSupportedLocalesQueryHandler(['fr_FR', 'en_GB', 'de_DE'], 'en_GB');

        $view = ($handler)(new GetSupportedLocalesQuery());

        self::assertSame(['fr_FR', 'en_GB', 'de_DE'], $view->supported);
        self::assertSame('en_GB', $view->default);
    }
}

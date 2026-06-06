<?php

declare(strict_types=1);

namespace App\Tests\Translation\UI\Http\Controller\GetSupportedLocales;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class GetSupportedLocalesControllerTest extends WebTestCase
{
    #[Test]
    public function itReturns200WithSupportedLocalesAndDefault(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/translations/locales');

        self::assertResponseStatusCodeSame(200);
        $body = json_decode((string) $client->getResponse()->getContent(), associative: true);
        self::assertIsArray($body);
        self::assertArrayHasKey('supported', $body);
        self::assertArrayHasKey('default', $body);
        self::assertIsArray($body['supported']);
        self::assertNotEmpty($body['supported']);
        self::assertContains($body['default'], $body['supported']);
    }
}

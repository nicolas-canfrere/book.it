<?php

declare(strict_types=1);

namespace App\Tests\Translation\UI\Http\Controller\GetTranslation;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[Group('functional')]
final class GetTranslationControllerTest extends AuthenticatedWebTestCase
{
    #[Test]
    public function itReturns200WithLocaleAndText(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = '660e8400-e29b-41d4-a716-446655440000';
        self::put($client, 'hotel', $hotelId, 'fr_FR', 'Bel hôtel parisien.');

        self::get($client, 'hotel', $hotelId, 'fr_FR');

        self::assertResponseStatusCodeSame(200);
        $body = json_decode((string) $client->getResponse()->getContent(), associative: true);
        self::assertIsArray($body);
        self::assertSame('fr_FR', $body['locale']);
        self::assertSame('Bel hôtel parisien.', $body['text']);
    }

    #[Test]
    public function itReturnsFallbackLocaleWithActualLocaleInResponse(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = '660e8400-e29b-41d4-a716-446655440001';
        self::put($client, 'hotel', $hotelId, 'en_GB', 'Nice hotel.');

        self::get($client, 'hotel', $hotelId, 'de_DE');

        self::assertResponseStatusCodeSame(200);
        $body = json_decode((string) $client->getResponse()->getContent(), associative: true);
        self::assertIsArray($body);
        self::assertSame('en_GB', $body['locale']); // fallback locale returned
        self::assertSame('Nice hotel.', $body['text']);
    }

    #[Test]
    public function itReturns404WhenNoTranslationFound(): void
    {
        $client = static::createAuthenticatedClient();
        self::get($client, 'hotel', '660e8400-e29b-41d4-a716-446655440099', 'fr_FR');

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function itReturnsRoomTypeTranslation(): void
    {
        $client = static::createAuthenticatedClient();
        $roomTypeId = '660e8400-e29b-41d4-a716-446655440002';
        self::put($client, 'room_type', $roomTypeId, 'en_GB', 'Cosy room with sea view.');

        self::get($client, 'room_type', $roomTypeId, 'en_GB');

        self::assertResponseStatusCodeSame(200);
        $body = json_decode((string) $client->getResponse()->getContent(), associative: true);
        self::assertIsArray($body);
        self::assertSame('en_GB', $body['locale']);
        self::assertSame('Cosy room with sea view.', $body['text']);
    }

    private static function put(KernelBrowser $client, string $subjectType, string $subjectId, string $locale, string $text): void
    {
        $client->request(
            'PUT',
            "/api/v1/translations/{$subjectType}/{$subjectId}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['locale' => $locale, 'text' => $text]),
        );
    }

    private static function get(KernelBrowser $client, string $subjectType, string $subjectId, string $locale): void
    {
        $client->request('GET', "/api/v1/translations/{$subjectType}/{$subjectId}?locale={$locale}");
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Translation\UI\Http\Controller\SetTranslation;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[Group('functional')]
final class SetTranslationControllerTest extends AuthenticatedWebTestCase
{
    #[Test]
    public function itSetsHotelTranslationAndReturns204(): void
    {
        $client = static::createAuthenticatedClient();
        self::request($client, 'hotel', '550e8400-e29b-41d4-a716-446655440000', [
            'locale' => 'fr_FR',
            'text' => 'Un magnifique hôtel au cœur de Paris.',
        ]);

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function itSetsRoomTypeTranslationAndReturns204(): void
    {
        $client = static::createAuthenticatedClient();
        self::request($client, 'room_type', '550e8400-e29b-41d4-a716-446655440001', [
            'locale' => 'en_GB',
            'text' => 'A cosy room with a sea view.',
        ]);

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function itReturns422WhenLocaleIsNotSupported(): void
    {
        $client = static::createAuthenticatedClient();
        self::request($client, 'hotel', '550e8400-e29b-41d4-a716-446655440000', [
            'locale' => 'ja_JP',
            'text' => 'テキスト',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns422WhenTextIsBlank(): void
    {
        $client = static::createAuthenticatedClient();
        self::request($client, 'hotel', '550e8400-e29b-41d4-a716-446655440000', [
            'locale' => 'fr_FR',
            'text' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns404ForUnknownSubjectType(): void
    {
        $client = static::createAuthenticatedClient();
        self::request($client, 'unknown', '550e8400-e29b-41d4-a716-446655440000', [
            'locale' => 'fr_FR',
            'text' => 'Some text',
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function itUpsertOverwritesExistingTranslation(): void
    {
        $client = static::createAuthenticatedClient();
        $hotelId = '550e8400-e29b-41d4-a716-446655440002';

        self::request($client, 'hotel', $hotelId, ['locale' => 'fr_FR', 'text' => 'Premier texte']);
        self::assertResponseStatusCodeSame(204);

        self::request($client, 'hotel', $hotelId, ['locale' => 'fr_FR', 'text' => 'Texte mis à jour']);
        self::assertResponseStatusCodeSame(204);
    }

    /**
     * @param array<string, string> $body
     */
    private static function request(KernelBrowser $client, string $subjectType, string $subjectId, array $body): void
    {
        $client->request(
            'PUT',
            "/api/v1/translations/{$subjectType}/{$subjectId}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($body),
        );
    }
}

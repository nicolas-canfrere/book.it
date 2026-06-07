<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\GetRoomTypeAmenities;

use App\Room\Domain\ValueObject\RoomAmenity;
use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetRoomTypeAmenitiesControllerTest extends AuthenticatedWebTestCase
{
    #[Test]
    public function itReturnsAllRoomTypeAmenities(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request('GET', '/api/v1/room-type-amenities');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));

        /** @var array{amenities: string[]} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertEqualsCanonicalizing(RoomAmenity::values(), $body['amenities']);
    }
}

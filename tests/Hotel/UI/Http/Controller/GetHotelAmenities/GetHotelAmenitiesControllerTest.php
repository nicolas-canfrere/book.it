<?php

declare(strict_types=1);

namespace App\Tests\Hotel\UI\Http\Controller\GetHotelAmenities;

use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetHotelAmenitiesControllerTest extends AuthenticatedWebTestCase
{
    #[Test]
    public function itReturnsAllHotelAmenities(): void
    {
        $client = static::createAuthenticatedClient();

        $client->request('GET', '/api/v1/hotel-amenities');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));

        /** @var array{amenities: string[]} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertEqualsCanonicalizing(HotelAmenity::values(), $body['amenities']);
    }
}

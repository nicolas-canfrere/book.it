<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Functional\Controller\CheckOut;

use App\Tests\Shared\AuthenticatedWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[Group('functional')]
final class CheckOutControllerTest extends AuthenticatedWebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createAuthenticatedClient();
    }

    #[Test]
    public function itReturns422WhenActualDepartureDateIsMissing(): void
    {
        $this->client->request(
            method: 'POST',
            uri: '/api/v1/reservations/550e8400-e29b-41d4-a716-446655440000/check-out',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function itReturns404WhenReservationDoesNotExist(): void
    {
        $this->client->request(
            method: 'POST',
            uri: '/api/v1/reservations/550e8400-e29b-41d4-a716-446655440000/check-out',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['actualDepartureDate' => '2025-06-13'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function itReturns415WhenContentTypeIsNotJson(): void
    {
        $this->client->request(
            method: 'POST',
            uri: '/api/v1/reservations/550e8400-e29b-41d4-a716-446655440000/check-out',
            server: ['CONTENT_TYPE' => 'text/plain'],
            content: 'actualDepartureDate=2025-06-13',
        );

        self::assertResponseStatusCodeSame(415);
    }
}

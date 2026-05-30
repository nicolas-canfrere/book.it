<?php

declare(strict_types=1);

namespace App\Tests\Reservation\Functional\Controller\CheckOut;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class CheckOutControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_returns_422_when_actual_departure_date_is_missing(): void
    {
        $this->client->request(
            method: 'POST',
            uri: '/api/v1/reservations/550e8400-e29b-41d4-a716-446655440000/check-out',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function test_returns_404_when_reservation_does_not_exist(): void
    {
        $this->client->request(
            method: 'POST',
            uri: '/api/v1/reservations/550e8400-e29b-41d4-a716-446655440000/check-out',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['actualDepartureDate' => '2025-06-13'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
    }
}

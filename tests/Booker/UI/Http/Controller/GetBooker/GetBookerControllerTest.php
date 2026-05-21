<?php

declare(strict_types=1);

namespace App\Tests\Booker\UI\Http\Controller\GetBooker;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class GetBookerControllerTest extends WebTestCase
{
    private const array VALID_PAYLOAD = [
        'firstName' => 'Jean',
        'lastName' => 'Dupont',
        'email' => 'jean.dupont@example.com',
        'phone' => '+33612345678',
        'dateOfBirth' => '1990-05-15',
    ];

    #[Test]
    public function itReturns200WithCorrectBookerShape(): void
    {
        $client = static::createClient();
        $id = $this->registerBookerAndGetId($client);

        $client->request('GET', "/api/v1/bookers/{$id}");

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{id: string, firstName: string, lastName: string, email: string, phone: string, dateOfBirth: string, registeredAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($id, $body['id']);
        self::assertSame('Jean', $body['firstName']);
        self::assertSame('Dupont', $body['lastName']);
        self::assertSame('jean.dupont@example.com', $body['email']);
        self::assertSame('+33612345678', $body['phone']);
        self::assertSame('1990-05-15', $body['dateOfBirth']);
        self::assertGreaterThan(0, $body['registeredAt']);
    }

    #[Test]
    public function itReturns404WhenBookerDoesNotExist(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/bookers/00000000-0000-0000-0000-000000000000');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns404WhenIdIsNotAValidUuidV4(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/bookers/not-a-uuid');

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    private function registerBookerAndGetId(KernelBrowser $client): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }
}

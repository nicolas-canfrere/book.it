<?php

declare(strict_types=1);

namespace App\Tests\Booker\UI\Http\Controller\RegisterBooker;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class RegisterBookerControllerTest extends WebTestCase
{
    private const array VALID_PAYLOAD = [
        'firstName' => 'Jean',
        'lastName' => 'Dupont',
        'email' => 'jean.dupont@example.com',
        'phone' => '+33612345678',
        'dateOfBirth' => '1990-05-15',
    ];

    #[Test]
    public function itRegistersABookerAndReturns201(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var array{id: string, firstName: string, lastName: string, email: string, phone: string, dateOfBirth: string, registeredAt: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['id']);
        self::assertSame('Jean', $body['firstName']);
        self::assertSame('Dupont', $body['lastName']);
        self::assertSame('jean.dupont@example.com', $body['email']);
        self::assertSame('+33612345678', $body['phone']);
        self::assertSame('1990-05-15', $body['dateOfBirth']);
        self::assertGreaterThan(0, $body['registeredAt']);
    }

    #[Test]
    public function itReturns409WhenEmailAlreadyExists(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request(
            method: 'POST',
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::VALID_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int, detail: string} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/booker-already-exists', $body['type']);
        self::assertSame('Booker Already Exists', $body['title']);
        self::assertSame(Response::HTTP_CONFLICT, $body['status']);
    }

    #[Test]
    public function itReturns422WhenBookerIsUnderage(): void
    {
        $client = static::createClient();

        $underageDate = (new \DateTimeImmutable())->modify('-17 years +1 day')->format('Y-m-d');
        $payload = array_merge(self::VALID_PAYLOAD, ['dateOfBirth' => $underageDate, 'email' => 'underage@example.com']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/booker-underage', $body['type']);
        self::assertSame('Booker Underage', $body['title']);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $body['status']);
    }

    #[Test]
    public function itReturns422AsAProblemDetailWithViolationsWhenFieldIsMissing(): void
    {
        $client = static::createClient();

        $payload = self::VALID_PAYLOAD;
        unset($payload['email']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{violations: list<array{field: string, message: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('email', $fields);
    }

    #[Test]
    public function itReturns422WhenEmailIsInvalid(): void
    {
        $client = static::createClient();

        $payload = array_merge(self::VALID_PAYLOAD, ['email' => 'not-an-email']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WhenDateOfBirthIsInvalidFormat(): void
    {
        $client = static::createClient();

        $payload = array_merge(self::VALID_PAYLOAD, ['dateOfBirth' => 'not-a-date', 'email' => 'other@example.com']);

        $client->request(
            method: 'POST',
            uri: '/api/v1/bookers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }
}

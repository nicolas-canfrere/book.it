<?php

declare(strict_types=1);

namespace App\Tests\Room\UI\Http\Controller\BatchRegisterRooms;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class BatchRegisterRoomsControllerTest extends WebTestCase
{
    private const array HOTEL_PAYLOAD = [
        'name' => 'Hotel Test',
        'streetAddress' => '1 rue de la Paix',
        'postalCode' => '75001',
        'city' => 'Paris',
        'country' => 'FR',
    ];

    #[Test]
    public function itImportsBatchAndReturns201(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $csv = $this->makeCsvFile("number\n101\n102\n2A\n");
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var list<array{id: string, hotelId: string, number: string, createdAt: int}> $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(3, $body);
        $numbers = array_column($body, 'number');
        self::assertContains('101', $numbers);
        self::assertContains('102', $numbers);
        self::assertContains('2A', $numbers);
        foreach ($body as $room) {
            self::assertNotEmpty($room['id']);
            self::assertSame($hotelId, $room['hotelId']);
            self::assertGreaterThan(0, $room['createdAt']);
        }
    }

    #[Test]
    public function itReturns201WithEmptyArrayForHeaderOnlyCsv(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $csv = $this->makeCsvFile("number\n");
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        /** @var list<mixed> $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([], $body);
    }

    #[Test]
    public function itReturns404WhenHotelDoesNotExist(): void
    {
        $client = static::createClient();

        $csv = $this->makeCsvFile("number\n101\n");
        $client->request(
            method: 'POST',
            uri: '/api/hotels/00000000-0000-4000-8000-000000000000/rooms/batch',
            files: ['csv' => $csv],
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns404WhenHotelIdIsNotUuidV4(): void
    {
        $client = static::createClient();

        $csv = $this->makeCsvFile("number\n101\n");
        $client->request(
            method: 'POST',
            uri: '/api/hotels/not-a-uuid/rooms/batch',
            files: ['csv' => $csv],
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itReturns422WithViolationsWhenDuplicateInBatch(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $csv = $this->makeCsvFile("number\n101\n101\n");
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{type: string, violations: list<array{field: string, message: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://book.it/problems/room-batch-invalid', $body['type']);
        self::assertCount(1, $body['violations']);
        self::assertSame('line[3]', $body['violations'][0]['field']);
    }

    #[Test]
    public function itReturns422WhenNumberAlreadyExistsInHotel(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        // pre-register room 101
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['number' => '101'], \JSON_THROW_ON_ERROR),
        );

        $csv = $this->makeCsvFile("number\n101\n102\n");
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{violations: list<array{field: string, message: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(1, $body['violations']);
        self::assertSame('line[2]', $body['violations'][0]['field']);
    }

    #[Test]
    public function itReturns422WithAllViolationsAtOnce(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $csv = $this->makeCsvFile("number\n\n101\n101\n");
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array{violations: list<array{field: string, message: string}>} $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $body['violations']);
    }

    #[Test]
    public function itReturns422WhenCsvHeaderIsInvalid(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $csv = $this->makeCsvFile("room_number\n101\n");
        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
            files: ['csv' => $csv],
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function itReturns422WhenNoCsvFileProvided(): void
    {
        $client = static::createClient();
        $hotelId = $this->registerHotelAndGetId($client);

        $client->request(
            method: 'POST',
            uri: "/api/hotels/{$hotelId}/rooms/batch",
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    private function registerHotelAndGetId(KernelBrowser $client): string
    {
        $client->request(
            method: 'POST',
            uri: '/api/hotels',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(self::HOTEL_PAYLOAD, \JSON_THROW_ON_ERROR),
        );

        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    private function makeCsvFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rooms_') . '.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'rooms.csv', 'text/csv', null, true);
    }
}

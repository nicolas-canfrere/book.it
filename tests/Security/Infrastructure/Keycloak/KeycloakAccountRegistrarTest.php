<?php

declare(strict_types=1);

namespace App\Tests\Security\Infrastructure\Keycloak;

use App\Security\Application\Contract\AccountRegistrationFailedException;
use App\Security\Infrastructure\Keycloak\KeycloakAccountRegistrar;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[Group('unit')]
final class KeycloakAccountRegistrarTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private IdentityMappingRepository&MockObject $mappingRepository;
    private KeycloakAccountRegistrar $registrar;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->mappingRepository = $this->createMock(IdentityMappingRepository::class);
        $this->registrar = new KeycloakAccountRegistrar(
            $this->httpClient,
            $this->mappingRepository,
            'http://keycloak:8080',
            'bookit',
            'bookit-admin',
            'secret',
            new NullLogger(),
        );
    }

    #[Test]
    public function it_creates_account_and_saves_mapping(): void
    {
        $createResponse = $this->createMock(ResponseInterface::class);
        $createResponse->method('getStatusCode')->willReturn(201);
        $createResponse->method('getHeaders')->willReturn([
            'location' => ['http://keycloak:8080/admin/realms/bookit/users/keycloak-uuid'],
        ]);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($this->mockTokenResponse(), $createResponse);

        $this->mappingRepository->expects(self::once())
            ->method('save')
            ->with('booker-uuid', 'booker', 'keycloak-uuid');

        $this->registrar->register('booker-uuid', 'booker', 'test@example.com', 'password123');
    }

    #[Test]
    public function it_throws_on_non_201_response(): void
    {
        $createResponse = $this->createMock(ResponseInterface::class);
        $createResponse->method('getStatusCode')->willReturn(409);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($this->mockTokenResponse(), $createResponse);

        $this->expectException(AccountRegistrationFailedException::class);
        $this->registrar->register('booker-uuid', 'booker', 'test@example.com', 'password123');
    }

    #[Test]
    public function it_unregisters_account_and_removes_mapping(): void
    {
        $this->mappingRepository->expects(self::once())
            ->method('findExternalId')
            ->with('booker-uuid', 'booker')
            ->willReturn('keycloak-uuid');

        $deleteResponse = $this->createMock(ResponseInterface::class);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($this->mockTokenResponse(), $deleteResponse);

        $this->mappingRepository->expects(self::once())
            ->method('delete')
            ->with('booker-uuid', 'booker');

        $this->registrar->unregister('booker-uuid', 'booker');
    }

    #[Test]
    public function it_skips_unregister_when_mapping_not_found(): void
    {
        $this->mappingRepository->method('findExternalId')->willReturn(null);
        $this->httpClient->expects(self::never())->method('request');
        $this->mappingRepository->expects(self::never())->method('delete');

        $this->registrar->unregister('booker-uuid', 'booker');
    }

    private function mockTokenResponse(): ResponseInterface&MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['access_token' => 'test-token']);

        return $response;
    }
}

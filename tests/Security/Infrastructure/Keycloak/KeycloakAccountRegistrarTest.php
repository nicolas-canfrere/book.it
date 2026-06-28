<?php

declare(strict_types=1);

namespace App\Tests\Security\Infrastructure\Keycloak;

use App\Security\Application\Contract\AccountRegistrationFailedException;
use App\Security\Infrastructure\Keycloak\KeycloakAccountRegistrar;
use App\Security\Infrastructure\Keycloak\KeycloakHttpClientInterface;
use App\Security\Infrastructure\Keycloak\KeycloakUnavailableException;
use App\Security\Infrastructure\Persistence\IdentityMappingRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[Group('unit')]
final class KeycloakAccountRegistrarTest extends TestCase
{
    private KeycloakHttpClientInterface&MockObject $keycloakClient;
    private IdentityMappingRepository&MockObject $mappingRepository;
    private KeycloakAccountRegistrar $registrar;

    protected function setUp(): void
    {
        $this->keycloakClient = $this->createMock(KeycloakHttpClientInterface::class);
        $this->mappingRepository = $this->createMock(IdentityMappingRepository::class);
        $this->registrar = new KeycloakAccountRegistrar(
            $this->keycloakClient,
            $this->mappingRepository,
            new NullLogger(),
        );
    }

    #[Test]
    public function itCreatesAccountAndSavesMapping(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(201);
        $response->method('getHeaders')->willReturn([
            'location' => ['https://keycloak.test/admin/realms/test/users/keycloak-uuid'],
        ]);

        $this->keycloakClient->expects(self::once())
            ->method('createUser')
            ->with('test@example.com', 'password123')
            ->willReturn($response);

        $this->mappingRepository->expects(self::once())
            ->method('save')
            ->with('booker-uuid', 'booker', 'keycloak-uuid');

        $this->registrar->register('booker-uuid', 'booker', 'test@example.com', 'password123');
    }

    #[Test]
    public function itThrowsOnNon201Response(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(409);

        $this->keycloakClient->method('createUser')->willReturn($response);

        $this->expectException(AccountRegistrationFailedException::class);
        $this->registrar->register('booker-uuid', 'booker', 'test@example.com', 'password123');
    }

    #[Test]
    public function itPropagatesKeycloakUnavailableException(): void
    {
        $this->keycloakClient->method('createUser')
            ->willThrowException(new KeycloakUnavailableException('exhausted'));

        $this->expectException(KeycloakUnavailableException::class);
        $this->registrar->register('booker-uuid', 'booker', 'test@example.com', 'password123');
    }

    #[Test]
    public function itUnregistersAccountAndRemovesMapping(): void
    {
        $this->mappingRepository->expects(self::once())
            ->method('findExternalId')
            ->with('booker-uuid', 'booker')
            ->willReturn('keycloak-uuid');

        $this->keycloakClient->expects(self::once())
            ->method('deleteUser')
            ->with('keycloak-uuid');

        $this->mappingRepository->expects(self::once())
            ->method('delete')
            ->with('booker-uuid', 'booker');

        $this->registrar->unregister('booker-uuid', 'booker');
    }

    #[Test]
    public function itSkipsUnregisterWhenMappingNotFound(): void
    {
        $this->mappingRepository->method('findExternalId')->willReturn(null);

        $this->keycloakClient->expects(self::never())->method('deleteUser');
        $this->mappingRepository->expects(self::never())->method('delete');

        $this->registrar->unregister('booker-uuid', 'booker');
    }

    #[Test]
    public function itDeletesMappingEvenWhenDeleteUserFails(): void
    {
        $this->mappingRepository->method('findExternalId')->willReturn('keycloak-uuid');
        $this->keycloakClient->method('deleteUser')
            ->willThrowException(new KeycloakUnavailableException('exhausted'));

        $this->mappingRepository->expects(self::once())
            ->method('delete')
            ->with('booker-uuid', 'booker');

        $this->registrar->unregister('booker-uuid', 'booker');
    }

    #[Test]
    public function itAssignsRealmRole(): void
    {
        $this->mappingRepository->expects(self::once())
            ->method('findExternalId')
            ->with('operator-uuid', 'operator')
            ->willReturn('keycloak-uuid');

        $this->keycloakClient->expects(self::once())
            ->method('assignRealmRole')
            ->with('keycloak-uuid', 'ROLE_ADMIN');

        $this->registrar->assignRole('operator-uuid', 'operator', 'ROLE_ADMIN');
    }

    #[Test]
    public function itThrowsWhenNoMappingFoundForRoleAssignment(): void
    {
        $this->mappingRepository->method('findExternalId')->willReturn(null);

        $this->keycloakClient->expects(self::never())->method('assignRealmRole');

        $this->expectException(\RuntimeException::class);
        $this->registrar->assignRole('operator-uuid', 'operator', 'ROLE_ADMIN');
    }

    #[Test]
    public function itSetsOrganizationIdAttributeOnKeycloakUser(): void
    {
        $this->mappingRepository->expects(self::once())
            ->method('findExternalId')
            ->with('operator-uuid', 'operator')
            ->willReturn('keycloak-uuid');

        $this->keycloakClient->expects(self::once())
            ->method('setUserAttribute')
            ->with('keycloak-uuid', 'organization_id', 'org-uuid');

        $this->registrar->setOrganizationId('operator-uuid', 'operator', 'org-uuid');
    }

    #[Test]
    public function itThrowsWhenNoMappingFoundForSetOrganizationId(): void
    {
        $this->mappingRepository->method('findExternalId')->willReturn(null);

        $this->keycloakClient->expects(self::never())->method('setUserAttribute');

        $this->expectException(\RuntimeException::class);
        $this->registrar->setOrganizationId('operator-uuid', 'operator', 'org-uuid');
    }
}

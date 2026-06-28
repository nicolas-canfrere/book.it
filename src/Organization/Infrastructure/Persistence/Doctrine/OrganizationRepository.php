<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine;

use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationStatus;
use App\Organization\Domain\Port\OrganizationRepositoryInterface;
use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Domain\ValueObject\OrganizationId;
use Doctrine\DBAL\Connection;

final readonly class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function __construct(private Connection $organizationConnection)
    {
    }

    public function add(Organization $organization): void
    {
        $this->organizationConnection->insert('organizations', [
            'id' => $organization->id->value,
            'name' => $organization->name->value,
            'contact_email' => $organization->contactEmail->value,
            'status' => $organization->status->value,
            'registered_at' => $organization->registeredAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function save(Organization $organization): void
    {
        $this->organizationConnection->update('organizations', [
            'status' => $organization->status->value,
        ], ['id' => $organization->id->value]);
    }

    public function get(OrganizationId $id): ?Organization
    {
        /** @var array{id: string, name: string, contact_email: string, status: string, registered_at: string}|false $row */
        $row = $this->organizationConnection->fetchAssociative(
            'SELECT id, name, contact_email, status, registered_at FROM organizations WHERE id = :id',
            ['id' => $id->value],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function existsByContactEmail(string $email): bool
    {
        /** @var int|string $count */
        $count = $this->organizationConnection->fetchOne(
            'SELECT COUNT(*) FROM organizations WHERE LOWER(contact_email) = LOWER(:email)',
            ['email' => $email],
        );

        return (int) $count > 0;
    }

    /**
     * @param array{id: string, name: string, contact_email: string, status: string, registered_at: string} $row
     */
    private function hydrate(array $row): Organization
    {
        return Organization::reconstitute(
            id: new OrganizationId($row['id']),
            name: new OrganizationName($row['name']),
            contactEmail: new OrganizationEmail($row['contact_email']),
            status: OrganizationStatus::from($row['status']),
            registeredAt: new \DateTimeImmutable($row['registered_at']),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Domain\Event\OrganizationRegistered;
use App\Shared\Domain\Event\OrganizationSuspended;
use App\Shared\Domain\ValueObject\OrganizationId;

final class Organization
{
    /** @var list<object> */
    private array $events = [];

    private function __construct(
        public readonly OrganizationId $id,
        public readonly OrganizationName $name,
        public readonly OrganizationEmail $contactEmail,
        public OrganizationStatus $status,
        public readonly \DateTimeImmutable $registeredAt,
    ) {
    }

    public static function register(
        OrganizationId $id,
        OrganizationName $name,
        OrganizationEmail $contactEmail,
        \DateTimeImmutable $registeredAt,
    ): self {
        $org = new self($id, $name, $contactEmail, OrganizationStatus::Pending, $registeredAt);
        $org->events[] = new OrganizationRegistered(
            organizationId: $id->value,
            contactEmail: $contactEmail->value,
            registeredAt: $registeredAt,
        );

        return $org;
    }

    public static function reconstitute(
        OrganizationId $id,
        OrganizationName $name,
        OrganizationEmail $contactEmail,
        OrganizationStatus $status,
        \DateTimeImmutable $registeredAt,
    ): self {
        return new self($id, $name, $contactEmail, $status, $registeredAt);
    }

    public function activate(): void
    {
        $this->status = OrganizationStatus::Active;
    }

    public function suspend(\DateTimeImmutable $at): void
    {
        $this->status = OrganizationStatus::Suspended;
        $this->events[] = new OrganizationSuspended(
            organizationId: $this->id->value,
            suspendedAt: $at,
        );
    }

    /** @return list<object> */
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}

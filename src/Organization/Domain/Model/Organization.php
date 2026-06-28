<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Domain\ValueObject\OrganizationId;

final class Organization
{
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
        return new self($id, $name, $contactEmail, OrganizationStatus::Pending, $registeredAt);
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

    public function suspend(): void
    {
        $this->status = OrganizationStatus::Suspended;
    }
}

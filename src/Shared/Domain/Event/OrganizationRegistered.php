<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class OrganizationRegistered
{
    public function __construct(
        public string $organizationId,
        public string $contactEmail,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}

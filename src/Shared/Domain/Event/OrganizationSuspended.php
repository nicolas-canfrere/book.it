<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final readonly class OrganizationSuspended
{
    public function __construct(
        public string $organizationId,
        public \DateTimeImmutable $suspendedAt,
    ) {
    }
}

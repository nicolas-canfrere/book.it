<?php

declare(strict_types=1);

namespace App\Onboarding\Application\UseCase\OnboardOrganization;

use App\Shared\Application\Bus\SyncCommandInterface;

final readonly class OnboardOrganizationCommand implements SyncCommandInterface
{
    public function __construct(
        public string $organizationId,
        public string $operatorId,
        public string $organizationName,
        public string $contactEmail,
        public string $ownerFirstName,
        public string $ownerLastName,
        public string $ownerPhone,
        public string $password,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}

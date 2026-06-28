<?php

declare(strict_types=1);

namespace App\Organization\Application\UseCase\RegisterOrganization;

use App\Organization\Domain\ValueObject\OrganizationEmail;
use App\Organization\Domain\ValueObject\OrganizationName;
use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\OrganizationId;

final readonly class RegisterOrganizationCommand implements SyncCommandInterface
{
    public function __construct(
        public OrganizationId $id,
        public OrganizationName $name,
        public OrganizationEmail $contactEmail,
        public \DateTimeImmutable $registeredAt,
    ) {
    }
}

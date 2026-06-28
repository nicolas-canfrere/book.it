<?php

declare(strict_types=1);

namespace App\Organization\Application\UseCase\ActivateOrganization;

use App\Shared\Application\Bus\SyncCommandInterface;
use App\Shared\Domain\ValueObject\OrganizationId;

final readonly class ActivateOrganizationCommand implements SyncCommandInterface
{
    public function __construct(public OrganizationId $id)
    {
    }
}

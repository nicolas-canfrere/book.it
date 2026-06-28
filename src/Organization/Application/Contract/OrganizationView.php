<?php

declare(strict_types=1);

namespace App\Organization\Application\Contract;

final readonly class OrganizationView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $contactEmail,
        public string $status,
    ) {
    }
}

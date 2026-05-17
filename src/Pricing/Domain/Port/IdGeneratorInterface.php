<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Port;

interface IdGeneratorInterface
{
    public function generate(): string;
}

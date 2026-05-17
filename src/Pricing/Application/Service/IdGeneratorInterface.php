<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

interface IdGeneratorInterface
{
    public function generate(): string;
}

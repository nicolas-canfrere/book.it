<?php

declare(strict_types=1);

namespace App\Booker\Application\Service;

interface BookerIdGeneratorInterface
{
    public function generate(): string;
}

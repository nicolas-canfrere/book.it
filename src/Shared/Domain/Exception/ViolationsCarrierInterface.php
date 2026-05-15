<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

interface ViolationsCarrierInterface
{
    /** @return list<array{field: string, message: string}> */
    public function getViolations(): array;
}

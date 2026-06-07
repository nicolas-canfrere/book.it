<?php

declare(strict_types=1);

namespace App\Operator\Application\Contract;

final readonly class OperatorView
{
    public function __construct(
        public string $id,
        public string $email,
    ) {
    }
}

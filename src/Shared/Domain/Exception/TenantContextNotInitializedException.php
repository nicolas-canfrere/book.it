<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class TenantContextNotInitializedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('TenantContext has not been initialized for this request.');
    }
}

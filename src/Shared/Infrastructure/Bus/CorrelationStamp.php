<?php

// src/Shared/Infrastructure/Bus/CorrelationStamp.php
declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use Symfony\Component\Messenger\Stamp\StampInterface;

final readonly class CorrelationStamp implements StampInterface
{
    public function __construct(private string $requestId)
    {
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }
}

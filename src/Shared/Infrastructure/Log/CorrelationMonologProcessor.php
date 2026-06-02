<?php

// src/Shared/Infrastructure/Log/CorrelationMonologProcessor.php
declare(strict_types=1);

namespace App\Shared\Infrastructure\Log;

use App\Shared\Infrastructure\Correlation\RequestCorrelationContext;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final readonly class CorrelationMonologProcessor implements ProcessorInterface
{
    public function __construct(private RequestCorrelationContext $context)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(extra: [...$record->extra, 'request_id' => $this->context->getId()]);
    }
}

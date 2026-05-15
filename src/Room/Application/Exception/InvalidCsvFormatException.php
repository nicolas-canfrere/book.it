<?php

declare(strict_types=1);

namespace App\Room\Application\Exception;

final class InvalidCsvFormatException extends \InvalidArgumentException
{
    public function __construct(string $detail)
    {
        parent::__construct($detail);
    }
}

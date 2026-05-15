<?php

declare(strict_types=1);

namespace App\Room\Domain\Exception;

final class RoomBatchInvalidException extends \DomainException
{
    /**
     * @param list<array{field: string, message: string}> $violations
     */
    public function __construct(public readonly array $violations)
    {
        parent::__construct('Room batch import failed due to validation errors.');
    }
}

<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Exception;

use App\Shared\Domain\ValueObject\RoomId;

final class BaseRateNotFoundException extends \DomainException
{
    public function __construct(RoomId $roomId)
    {
        parent::__construct(sprintf('Base rate for room "%s" not found.', $roomId->value));
    }
}

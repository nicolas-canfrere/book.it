<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Exception;

use App\Shared\Domain\ValueObject\RoomId;

final class CancellationPolicyNotFoundException extends \DomainException
{
    public function __construct(RoomId $roomId)
    {
        parent::__construct(sprintf('Cancellation policy not found for room "%s".', $roomId->value));
    }
}

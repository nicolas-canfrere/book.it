<?php

declare(strict_types=1);

namespace App\Reservation\Domain\Exception;

final class RoomNotBookableException extends \DomainException
{
    public function __construct(string $roomId)
    {
        parent::__construct(sprintf('Room "%s" has no pricing configured.', $roomId));
    }
}

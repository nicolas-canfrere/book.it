<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Exception;

final class RoomHasNoBaseRateException extends \RuntimeException
{
    public function __construct(string $roomId)
    {
        parent::__construct(sprintf('Room "%s" has no base rate configured.', $roomId));
    }
}

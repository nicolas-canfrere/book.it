<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Exception;

final class RoomNotFoundException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Room "%s" not found.', $id));
    }
}

<?php

declare(strict_types=1);

namespace App\Booker\Infrastructure\Service;

use App\Booker\Application\Service\BookerIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class BookerIdGenerator implements BookerIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}

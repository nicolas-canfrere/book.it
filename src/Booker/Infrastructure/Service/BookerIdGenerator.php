<?php

declare(strict_types=1);

namespace App\Booker\Infrastructure\Service;

use App\Booker\Domain\Port\BookerIdGeneratorInterface;
use App\Shared\Domain\ValueObject\BookerId;
use Symfony\Component\Uid\Uuid;

final class BookerIdGenerator implements BookerIdGeneratorInterface
{
    public function generate(): BookerId
    {
        return new BookerId(Uuid::v4()->toString());
    }
}

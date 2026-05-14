<?php

declare(strict_types=1);

namespace App\Hotel\Infrastructure\Service;

use App\Hotel\Application\Service\HotelIdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class HotelIdGenerator implements HotelIdGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}

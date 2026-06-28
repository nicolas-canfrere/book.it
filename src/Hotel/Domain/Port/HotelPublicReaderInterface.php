<?php

declare(strict_types=1);

namespace App\Hotel\Domain\Port;

use App\Hotel\Domain\Model\Hotel;
use App\Shared\Domain\ValueObject\HotelId;

interface HotelPublicReaderInterface
{
    public function get(HotelId $id): ?Hotel;
}

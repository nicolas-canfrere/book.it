<?php

declare(strict_types=1);

namespace App\Geo\Domain\Port;

use App\Shared\Domain\ValueObject\GeoPlaceId;

interface GeoPlaceWriterInterface
{
    public function upsert(
        GeoPlaceId $id,
        string $name,
        string $asciiName,
        string $countryCode,
        ?string $admin1Code,
    ): void;
}

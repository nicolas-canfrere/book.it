<?php

declare(strict_types=1);

namespace App\Geo\Domain;

use App\Shared\Domain\ValueObject\GeoPlaceId;

final readonly class GeoPlace
{
    public function __construct(
        public GeoPlaceId $id,
        public string $name,
        public string $countryCode,
        public ?string $admin1Code,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Geo\UI\Http\Controller;

use App\Geo\Domain\GeoPlace;

final class GeoPlaceSerializer
{
    /** @return array{id: int, name: string, countryCode: string, admin1Code: string|null} */
    public function serialize(GeoPlace $geoPlace): array
    {
        return [
            'id' => (int) $geoPlace->id->value,
            'name' => $geoPlace->name,
            'countryCode' => $geoPlace->countryCode,
            'admin1Code' => $geoPlace->admin1Code,
        ];
    }
}

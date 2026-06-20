<?php

declare(strict_types=1);

namespace App\Geo\Infrastructure\Finder;

use App\Geo\Domain\GeoPlace;
use App\Geo\Domain\Port\GeoPlaceFinderInterface;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use Doctrine\DBAL\Connection;

final readonly class DbalGeoPlaceFinder implements GeoPlaceFinderInterface
{
    public function __construct(private Connection $geoConnection)
    {
    }

    /** @return list<GeoPlace> */
    public function search(string $query, int $limit): array
    {
        $rows = $this->geoConnection->fetchAllAssociative(
            <<<'SQL'
            SELECT geoname_id, name, country_code, admin1_code
            FROM geo_place
            WHERE name % :query OR ascii_name % :query
            ORDER BY GREATEST(similarity(name, :query), similarity(ascii_name, :query)) DESC
            LIMIT :limit
            SQL,
            ['query' => $query, 'limit' => $limit],
        );

        $results = [];
        foreach ($rows as $row) {
            /** @var array{geoname_id: string|int, name: string, country_code: string, admin1_code: string|null} $row */
            $results[] = new GeoPlace(
                id: new GeoPlaceId((string) $row['geoname_id']),
                name: (string) $row['name'],
                countryCode: (string) $row['country_code'],
                admin1Code: $row['admin1_code'],
            );
        }

        return $results;
    }
}

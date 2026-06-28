<?php

declare(strict_types=1);

namespace App\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\ValueObject\GeoPlaceId;
use App\Shared\Domain\ValueObject\HotelId;
use App\Shared\Domain\ValueObject\OrganizationId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\String\Slugger\SluggerInterface;

final class HotelRepository implements HotelRepositoryInterface
{
    private readonly TenantContext $tenantContext;

    public function __construct(
        private readonly Connection $hotelConnection,
        private readonly SluggerInterface $slugger,
        TenantContext $tenantContext,
    ) {
        $this->tenantContext = $tenantContext;
    }

    public function add(Hotel $hotel): void
    {
        $this->hotelConnection->insert('hotel', [
            'id' => $hotel->id->value,
            'name' => $hotel->name,
            'street_address' => $hotel->address->streetAddress,
            'postal_code' => $hotel->address->postalCode,
            'city' => $hotel->address->city,
            'country' => $hotel->address->country,
            'geo_place_id' => $hotel->address->geoPlaceId?->value,
            'search_key' => $this->buildSearchKey($hotel->name, $hotel->address),
            'created_at' => $hotel->createdAt->format('Y-m-d H:i:s'),
            'stars' => $hotel->starRating?->stars,
            'superior' => null !== $hotel->starRating ? $hotel->starRating->superior : false,
            'amenities' => $this->serializeAmenities($hotel->amenities),
            'organization_id' => $hotel->organizationId->value,
        ], [
            'superior' => Types::BOOLEAN,
        ]);
    }

    public function save(Hotel $hotel): void
    {
        $this->hotelConnection->update('hotel', [
            'stars' => $hotel->starRating?->stars,
            'superior' => null !== $hotel->starRating ? $hotel->starRating->superior : false,
            'amenities' => $this->serializeAmenities($hotel->amenities),
        ], [
            'id' => $hotel->id->value,
            'organization_id' => $this->tenantContext->getOrganizationId()->value,
        ], [
            'superior' => Types::BOOLEAN,
        ]);
    }

    public function get(HotelId $id): ?Hotel
    {
        $qb = $this->hotelConnection->createQueryBuilder()
            ->select('h.id, h.name, h.street_address, h.postal_code, h.city, h.country, h.geo_place_id, h.created_at, h.stars, h.superior, h.amenities, h.organization_id')
            ->from('hotel', 'h')
            ->where('h.id = :id')
            ->setParameter('id', $id->value);

        $this->applyTenantScope($qb, 'h');

        /** @var array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, geo_place_id: string|null, created_at: string, stars: int|null, superior: string|bool, amenities: string, organization_id: string}|false $row */
        $row = $qb->fetchAssociative();

        return false === $row ? null : $this->hydrate($row);
    }

    public function existsByNameAndAddress(string $name, Address $address): bool
    {
        $qb = $this->hotelConnection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('hotel', 'h')
            ->where('h.search_key = :key')
            ->setParameter('key', $this->buildSearchKey($name, $address));

        $this->applyTenantScope($qb, 'h');

        /** @var int|string $count */
        $count = $qb->fetchOne();

        return (int) $count > 0;
    }

    /**
     * @param array<HotelAmenity>|null $amenities
     */
    public function list(int $page, int $limit, ?string $city, ?string $country, ?int $minStars = null, ?array $amenities = null): HotelPage
    {
        $qb = $this->hotelConnection->createQueryBuilder()
            ->select('h.id, h.name, h.street_address, h.postal_code, h.city, h.country, h.geo_place_id, h.created_at, h.stars, h.superior, h.amenities, h.organization_id')
            ->from('hotel', 'h');

        $this->applyTenantScope($qb, 'h');

        if (null !== $city) {
            $qb->andWhere('h.city = :city')->setParameter('city', $city);
        }
        if (null !== $country) {
            $qb->andWhere('h.country = :country')->setParameter('country', $country);
        }
        if (null !== $minStars) {
            $qb->andWhere('h.stars >= :minStars')->setParameter('minStars', $minStars);
        }
        if (null !== $amenities && [] !== $amenities) {
            $qb->andWhere('h.amenities @> :amenities::text[]')
               ->setParameter('amenities', $this->serializeAmenities($amenities));
        }

        $countQb = clone $qb;
        $countQb->select('COUNT(*)');
        /** @var int|string $count */
        $count = $countQb->fetchOne();
        $total = (int) $count;

        $qb->orderBy('h.name', 'ASC')
           ->setMaxResults($limit)
           ->setFirstResult(($page - 1) * $limit);

        /** @var list<array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, geo_place_id: string|null, created_at: string, stars: int|null, superior: string|bool, amenities: string, organization_id: string}> $rows */
        $rows = $qb->fetchAllAssociative();

        return new HotelPage(array_map($this->hydrate(...), $rows), $total);
    }

    /**
     * @param array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, geo_place_id: string|null, created_at: string, stars: int|null, superior: string|bool, amenities: string, organization_id: string} $row
     */
    private function hydrate(array $row): Hotel
    {
        $starRating = null !== $row['stars']
            ? new StarRating((int) $row['stars'], 't' === $row['superior'] || true === $row['superior'])
            : null;

        return new Hotel(
            new HotelId($row['id']),
            $row['name'],
            new Address(
                $row['street_address'],
                $row['postal_code'],
                $row['city'],
                $row['country'],
                null !== $row['geo_place_id'] ? new GeoPlaceId((string) $row['geo_place_id']) : null,
            ),
            new \DateTimeImmutable($row['created_at']),
            new OrganizationId($row['organization_id']),
            $starRating,
            $this->parseAmenities($row['amenities']),
        );
    }

    private function applyTenantScope(QueryBuilder $qb, string $tableAlias = 't'): void
    {
        $qb->andWhere("{$tableAlias}.organization_id = :tenant_id")
           ->setParameter('tenant_id', $this->tenantContext->getOrganizationId()->value);
    }

    private function buildSearchKey(string $name, Address $address): string
    {
        return implode('|', [
            $this->slugger->slug($name)->lower()->toString(),
            $this->slugger->slug($address->streetAddress)->lower()->toString(),
            strtolower($address->postalCode),
            $this->slugger->slug($address->city)->lower()->toString(),
            strtolower($address->country),
        ]);
    }

    /** @return array<HotelAmenity> */
    private function parseAmenities(string $raw): array
    {
        if ('{}' === $raw) {
            return [];
        }

        preg_match_all('/"([^"]+)"|([^,{}]+)/', $raw, $matches);
        $values = array_map(
            static fn(string $quoted, string $plain): string => '' !== $quoted ? $quoted : $plain,
            $matches[1],
            $matches[2],
        );

        return array_map(HotelAmenity::from(...), $values);
    }

    /** @param array<HotelAmenity> $amenities */
    private function serializeAmenities(array $amenities): string
    {
        if ([] === $amenities) {
            return '{}';
        }

        return '{' . implode(',', array_map(static fn(HotelAmenity $a) => $a->value, $amenities)) . '}';
    }
}

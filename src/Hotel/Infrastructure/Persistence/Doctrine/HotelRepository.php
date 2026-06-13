<?php

declare(strict_types=1);

namespace App\Hotel\Infrastructure\Persistence\Doctrine;

use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Model\HotelPage;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Domain\ValueObject\HotelAmenity;
use App\Hotel\Domain\ValueObject\StarRating;
use App\Shared\Domain\ValueObject\HotelId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class HotelRepository implements HotelRepositoryInterface
{
    public function __construct(
        private Connection $hotelConnection,
        private SluggerInterface $slugger,
    ) {
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
            'search_key' => $this->buildSearchKey($hotel->name, $hotel->address),
            'created_at' => $hotel->createdAt->format('Y-m-d H:i:s'),
            'stars' => $hotel->starRating?->stars,
            'superior' => null !== $hotel->starRating ? $hotel->starRating->superior : false,
            'amenities' => $this->serializeAmenities($hotel->amenities),
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
        ], ['id' => $hotel->id->value], [
            'superior' => Types::BOOLEAN,
        ]);
    }

    public function get(HotelId $id): ?Hotel
    {
        /** @var array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string, stars: int|null, superior: string|bool, amenities: string}|false $row */
        $row = $this->hotelConnection->fetchAssociative(
            'SELECT id, name, street_address, postal_code, city, country, created_at, stars, superior, amenities FROM hotel WHERE id = :id',
            ['id' => $id->value],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function existsByNameAndAddress(string $name, Address $address): bool
    {
        $count = $this->hotelConnection->fetchOne(
            'SELECT COUNT(*) FROM hotel WHERE search_key = :key',
            ['key' => $this->buildSearchKey($name, $address)],
        );

        return $count > 0;
    }

    /**
     * @param array<HotelAmenity>|null $amenities
     */
    public function list(int $page, int $limit, ?string $city, ?string $country, ?int $minStars = null, ?array $amenities = null): HotelPage
    {
        $conditions = [];
        $params = [];

        if (null !== $city) {
            $conditions[] = 'city = :city';
            $params['city'] = $city;
        }

        if (null !== $country) {
            $conditions[] = 'country = :country';
            $params['country'] = $country;
        }

        if (null !== $minStars) {
            $conditions[] = 'stars >= :minStars';
            $params['minStars'] = $minStars;
        }

        if (null !== $amenities && [] !== $amenities) {
            $conditions[] = 'amenities @> :amenities::text[]';
            $params['amenities'] = $this->serializeAmenities($amenities);
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        /** @var int|string $count */
        $count = $this->hotelConnection->fetchOne(
            "SELECT COUNT(*) FROM hotel {$where}",
            $params,
        );
        $total = (int) $count;

        $params['limit'] = $limit;
        $params['offset'] = ($page - 1) * $limit;

        /** @var list<array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string, stars: int|null, superior: string|bool, amenities: string}> $rows */
        $rows = $this->hotelConnection->fetchAllAssociative(
            "SELECT id, name, street_address, postal_code, city, country, created_at, stars, superior, amenities FROM hotel {$where} ORDER BY name ASC LIMIT :limit OFFSET :offset",
            $params,
        );

        return new HotelPage(array_map($this->hydrate(...), $rows), $total);
    }

    /**
     * @param array{id: string, name: string, street_address: string, postal_code: string, city: string, country: string, created_at: string, stars: int|null, superior: string|bool, amenities: string} $row
     */
    private function hydrate(array $row): Hotel
    {
        $starRating = null !== $row['stars']
            ? new StarRating((int) $row['stars'], 't' === $row['superior'] || true === $row['superior'])
            : null;

        return new Hotel(
            new HotelId($row['id']),
            $row['name'],
            new Address($row['street_address'], $row['postal_code'], $row['city'], $row['country']),
            new \DateTimeImmutable($row['created_at']),
            $starRating,
            $this->parseAmenities($row['amenities']),
        );
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

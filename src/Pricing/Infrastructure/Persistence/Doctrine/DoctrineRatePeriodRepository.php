<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine;

use App\Pricing\Domain\Model\RatePeriod;
use App\Pricing\Domain\Port\RatePeriodRepositoryInterface;
use App\Pricing\Domain\ValueObject\DatePeriod;
use Doctrine\DBAL\Connection;

final readonly class DoctrineRatePeriodRepository implements RatePeriodRepositoryInterface
{
    public function __construct(private Connection $bookit)
    {
    }

    public function save(RatePeriod $ratePeriod): void
    {
        $this->bookit->executeStatement(
            'INSERT INTO pricing_rate_period (id, room_id, check_in, check_out, amount_cents, created_at, updated_at)
             VALUES (:id, :roomId, :checkIn, :checkOut, :amountCents, :createdAt, :updatedAt)
             ON CONFLICT (id) DO UPDATE SET check_in = :checkIn, check_out = :checkOut, amount_cents = :amountCents, updated_at = :updatedAt',
            [
                'id' => $ratePeriod->id,
                'roomId' => $ratePeriod->roomId,
                'checkIn' => $ratePeriod->checkIn->format('Y-m-d'),
                'checkOut' => $ratePeriod->checkOut->format('Y-m-d'),
                'amountCents' => $ratePeriod->amountCents,
                'createdAt' => $ratePeriod->createdAt->format('Y-m-d H:i:s'),
                'updatedAt' => $ratePeriod->updatedAt->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function findById(string $id): ?RatePeriod
    {
        /** @var array{id: string, room_id: string, check_in: string, check_out: string, amount_cents: int, created_at: string, updated_at: string}|false $row */
        $row = $this->bookit->fetchAssociative(
            'SELECT id, room_id, check_in, check_out, amount_cents, created_at, updated_at FROM pricing_rate_period WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    /** @return list<RatePeriod> */
    public function findByRoomId(string $roomId): array
    {
        /** @var list<array{id: string, room_id: string, check_in: string, check_out: string, amount_cents: int, created_at: string, updated_at: string}> $rows */
        $rows = $this->bookit->fetchAllAssociative(
            'SELECT id, room_id, check_in, check_out, amount_cents, created_at, updated_at FROM pricing_rate_period
             WHERE room_id = :roomId
             ORDER BY check_in ASC',
            ['roomId' => $roomId],
        );

        return array_map($this->hydrate(...), $rows);
    }

    /** @return list<RatePeriod> */
    public function findOverlappingByRoomId(string $roomId, DatePeriod $period): array
    {
        /** @var list<array{id: string, room_id: string, check_in: string, check_out: string, amount_cents: int, created_at: string, updated_at: string}> $rows */
        $rows = $this->bookit->fetchAllAssociative(
            'SELECT id, room_id, check_in, check_out, amount_cents, created_at, updated_at FROM pricing_rate_period
             WHERE room_id = :roomId
               AND check_in < :checkOut
               AND check_out > :checkIn
             ORDER BY check_in ASC',
            [
                'roomId' => $roomId,
                'checkIn' => $period->checkIn->format('Y-m-d'),
                'checkOut' => $period->checkOut->format('Y-m-d'),
            ],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function hasOverlap(string $roomId, DatePeriod $period, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM pricing_rate_period
                WHERE room_id = :roomId
                  AND check_in < :checkOut
                  AND check_out > :checkIn';

        $params = [
            'roomId' => $roomId,
            'checkIn' => $period->checkIn->format('Y-m-d'),
            'checkOut' => $period->checkOut->format('Y-m-d'),
        ];

        if (null !== $excludeId) {
            $sql .= ' AND id != :excludeId';
            $params['excludeId'] = $excludeId;
        }

        $count = $this->bookit->fetchOne($sql, $params);

        return $count > 0;
    }

    public function delete(RatePeriod $ratePeriod): void
    {
        $this->bookit->delete('pricing_rate_period', ['id' => $ratePeriod->id]);
    }

    /**
     * @param array{id: string, room_id: string, check_in: string, check_out: string, amount_cents: int, created_at: string, updated_at: string} $row
     */
    private function hydrate(array $row): RatePeriod
    {
        return new RatePeriod(
            $row['id'],
            $row['room_id'],
            new \DateTimeImmutable($row['check_in']),
            new \DateTimeImmutable($row['check_out']),
            $row['amount_cents'],
            new \DateTimeImmutable($row['created_at']),
            new \DateTimeImmutable($row['updated_at']),
        );
    }
}
